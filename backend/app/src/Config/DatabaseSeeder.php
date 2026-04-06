<?php
// This script is run from the PHP container at startup.

require __DIR__ . '/../../vendor/autoload.php';

use App\Config\Database;

echo "Running DB initialization...\n";

$pdo = Database::getConnection();

echo "Creating tables if missing...\n";

$queries = [
    "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        role ENUM('admin','tutor','student') NOT NULL DEFAULT 'student',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    "CREATE TABLE IF NOT EXISTS refresh_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token_hash CHAR(64) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        revoked_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_refresh_tokens_user_id (user_id),
        INDEX idx_refresh_tokens_expires_at (expires_at),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",

    "CREATE TABLE IF NOT EXISTS services (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tutor_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        duration_minutes INT NOT NULL,
        price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_services_tutor_id (tutor_id),
        CONSTRAINT fk_services_tutor_id FOREIGN KEY (tutor_id) REFERENCES users(id) ON DELETE RESTRICT
    )",

    "CREATE TABLE IF NOT EXISTS timeslots (
        id INT AUTO_INCREMENT PRIMARY KEY,
        service_id INT NOT NULL,
        start_time DATETIME NOT NULL,
        end_time DATETIME NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (service_id) REFERENCES services(id)
    )",

    "CREATE TABLE IF NOT EXISTS bookings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        timeslot_id INT NOT NULL,
        status ENUM('paid','cancelled') NOT NULL DEFAULT 'paid',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES users(id),
        FOREIGN KEY (timeslot_id) REFERENCES timeslots(id)
    )",
];

foreach ($queries as $sql) {
    $pdo->exec($sql);
}

// Keep role enum in sync for existing databases.
$pdo->exec("
    ALTER TABLE users
    MODIFY COLUMN role ENUM('admin','tutor','student') NOT NULL DEFAULT 'student'
");

// Keep services.price in sync for existing databases.
$priceColumnExists = (int) $pdo->query("
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'services'
      AND COLUMN_NAME = 'price'
")->fetchColumn();

if ($priceColumnExists === 0) {
    $pdo->exec("
        ALTER TABLE services
        ADD COLUMN price DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER duration_minutes
    ");
}

$pdo->exec("
    ALTER TABLE services
    MODIFY COLUMN price DECIMAL(10,2) NOT NULL DEFAULT 0.00
");

// Keep services.tutor_id in sync for existing databases.
$tutorIdColumnExists = (int) $pdo->query("
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'services'
      AND COLUMN_NAME = 'tutor_id'
")->fetchColumn();

if ($tutorIdColumnExists === 0) {
    $pdo->exec("
        ALTER TABLE services
        ADD COLUMN tutor_id INT NULL AFTER id
    ");
}

$fallbackOwnerId = (int) $pdo->query("
    SELECT id
    FROM users
    WHERE role = 'tutor'
    ORDER BY id ASC
    LIMIT 1
")->fetchColumn();

if ($fallbackOwnerId <= 0) {
    $fallbackOwnerId = (int) $pdo->query("
        SELECT id
        FROM users
        ORDER BY id ASC
        LIMIT 1
    ")->fetchColumn();
}

if ($fallbackOwnerId > 0) {
    $stmt = $pdo->prepare("
        UPDATE services
        SET tutor_id = :tutor_id
        WHERE tutor_id IS NULL
    ");
    $stmt->execute([':tutor_id' => $fallbackOwnerId]);
}

$nullTutorOwnerCount = (int) $pdo->query("
    SELECT COUNT(*)
    FROM services
    WHERE tutor_id IS NULL
")->fetchColumn();

if ($nullTutorOwnerCount === 0) {
    $pdo->exec("
        ALTER TABLE services
        MODIFY COLUMN tutor_id INT NOT NULL
    ");

    $servicesTutorIndexExists = (int) $pdo->query("
        SELECT COUNT(*)
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'services'
          AND INDEX_NAME = 'idx_services_tutor_id'
    ")->fetchColumn();

    if ($servicesTutorIndexExists === 0) {
        $pdo->exec("
            ALTER TABLE services
            ADD INDEX idx_services_tutor_id (tutor_id)
        ");
    }

    $servicesTutorFkExists = (int) $pdo->query("
        SELECT COUNT(*)
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'services'
          AND COLUMN_NAME = 'tutor_id'
          AND REFERENCED_TABLE_NAME = 'users'
          AND REFERENCED_COLUMN_NAME = 'id'
    ")->fetchColumn();

    if ($servicesTutorFkExists === 0) {
        $pdo->exec("
            ALTER TABLE services
            ADD CONSTRAINT fk_services_tutor_id
            FOREIGN KEY (tutor_id) REFERENCES users(id) ON DELETE RESTRICT
        ");
    }
}

// Keep bookings.status in sync for existing databases.
$pdo->exec("
    ALTER TABLE bookings
    MODIFY COLUMN status ENUM('pending','approved','cancelled','declined','paid') NOT NULL DEFAULT 'pending'
");

$pdo->exec("
    UPDATE bookings
    SET status = CASE
        WHEN status = 'approved' THEN 'paid'
        WHEN status = 'pending' THEN 'paid'
        WHEN status = 'declined' THEN 'cancelled'
        ELSE status
    END
");

$pdo->exec("
    ALTER TABLE bookings
    MODIFY COLUMN status ENUM('paid','cancelled') NOT NULL DEFAULT 'paid'
");

echo "Tables ensured.\n";

$userCount = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

if ($userCount > 0) {
    echo "Database already seeded, skipping seeding.\n";
    return;
}

echo "Seeding initial data...\n";

$insertUser = $pdo->prepare("
    INSERT INTO users (name, email, password_hash, role)
    VALUES (:name, :email, :password_hash, :role)
");

$insertUser->execute([
    ':name' => 'Admin User',
    ':email' => 'admin@example.com',
    ':password_hash' => password_hash('password', PASSWORD_DEFAULT),
    ':role' => 'admin',
]);

$insertUser->execute([
    ':name' => 'Test Tutor',
    ':email' => 'tutor@example.com',
    ':password_hash' => password_hash('password', PASSWORD_DEFAULT),
    ':role' => 'tutor',
]);
$tutorId = (int) $pdo->lastInsertId();

$insertService = $pdo->prepare("
    INSERT INTO services (tutor_id, title, description, duration_minutes, price, is_active)
    VALUES (:tutor_id, :title, :description, :duration_minutes, :price, :is_active)
");

$insertService->execute([
    ':tutor_id' => $tutorId,
    ':title' => 'Mathematics Tutoring',
    ':description' => 'Personalized math sessions tailored to your needs.',
    ':duration_minutes' => 60,
    ':price' => 32.50,
    ':is_active' => 1,
]);
$mathId = (int) $pdo->lastInsertId();

$insertService->execute([
    ':tutor_id' => $tutorId,
    ':title' => 'English Tutoring',
    ':description' => 'Grammar, writing, and comprehension help.',
    ':duration_minutes' => 60,
    ':price' => 29.99,
    ':is_active' => 1,
]);
$englishId = (int) $pdo->lastInsertId();

$insertService->execute([
    ':tutor_id' => $tutorId,
    ':title' => 'Science Tutoring',
    ':description' => 'Support for physics, chemistry, and biology.',
    ':duration_minutes' => 60,
    ':price' => 35.00,
    ':is_active' => 1,
]);
$scienceId = (int) $pdo->lastInsertId();

$insertSlot = $pdo->prepare("
    INSERT INTO timeslots (service_id, start_time, end_time, is_active)
    VALUES (:service_id, :start_time, :end_time, :is_active)
");

$insertSlot->execute([
    ':service_id' => $mathId,
    ':start_time' => date('Y-m-d 10:00:00', strtotime('+1 day')),
    ':end_time' => date('Y-m-d 11:00:00', strtotime('+1 day')),
    ':is_active' => 1,
]);

$insertSlot->execute([
    ':service_id' => $englishId,
    ':start_time' => date('Y-m-d 13:00:00', strtotime('+1 day')),
    ':end_time' => date('Y-m-d 14:00:00', strtotime('+1 day')),
    ':is_active' => 1,
]);

$insertSlot->execute([
    ':service_id' => $scienceId,
    ':start_time' => date('Y-m-d 15:00:00', strtotime('+1 day')),
    ':end_time' => date('Y-m-d 16:00:00', strtotime('+1 day')),
    ':is_active' => 1,
]);

$insertSlot->execute([
    ':service_id' => $mathId,
    ':start_time' => date('Y-m-d 10:00:00', strtotime('-3 day')),
    ':end_time' => date('Y-m-d 11:00:00', strtotime('-3 day')),
    ':is_active' => 1,
]);

$insertSlot->execute([
    ':service_id' => $englishId,
    ':start_time' => date('Y-m-d 13:00:00', strtotime('+2 day')),
    ':end_time' => date('Y-m-d 14:00:00', strtotime('+2 day')),
    ':is_active' => 1,
]);

$insertSlot->execute([
    ':service_id' => $scienceId,
    ':start_time' => date('Y-m-d 15:00:00', strtotime('+7 day')),
    ':end_time' => date('Y-m-d 16:00:00', strtotime('+7 day')),
    ':is_active' => 1,
]);

$insertSlot->execute([
    ':service_id' => $mathId,
    ':start_time' => date('Y-m-d 10:00:00', strtotime('-5 day')),
    ':end_time' => date('Y-m-d 11:00:00', strtotime('-5 day')),
    ':is_active' => 1,
]);

echo "Database initialized and seeded.\n";
