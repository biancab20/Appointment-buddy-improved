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
        title VARCHAR(255) NOT NULL,
        description TEXT,
        duration_minutes INT NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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
        status ENUM('pending','approved','cancelled', 'declined') NOT NULL DEFAULT 'pending',
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

$insertService = $pdo->prepare("
    INSERT INTO services (title, description, duration_minutes, is_active)
    VALUES (:title, :description, :duration_minutes, :is_active)
");

$insertService->execute([
    ':title' => 'Mathematics Tutoring',
    ':description' => 'Personalized math sessions tailored to your needs.',
    ':duration_minutes' => 60,
    ':is_active' => 1,
]);
$mathId = (int) $pdo->lastInsertId();

$insertService->execute([
    ':title' => 'English Tutoring',
    ':description' => 'Grammar, writing, and comprehension help.',
    ':duration_minutes' => 60,
    ':is_active' => 1,
]);
$englishId = (int) $pdo->lastInsertId();

$insertService->execute([
    ':title' => 'Science Tutoring',
    ':description' => 'Support for physics, chemistry, and biology.',
    ':duration_minutes' => 60,
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
