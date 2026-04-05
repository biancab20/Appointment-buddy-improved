<?php
// This script is run from the PHP container at startup:
// command: sh -c "php src/Config/Init.php && php-fpm"

require __DIR__ . '/../../vendor/autoload.php';
use App\Config\Database;

echo "🚀 Running DB initialization...\n";

$pdo = Database::getConnection();

// --- 2. Create tables if they don't exist ---

echo "🛠 Creating tables if missing...\n";

$queries = [

    "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        role ENUM('student','admin') NOT NULL DEFAULT 'student',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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

echo "✅ Tables ensured.\n";

// --- 3. Check if already seeded ---

$userCount = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

if ($userCount > 0) {
    echo "✔ Database already seeded, skipping seeding.\n";
    return;
}

echo "🌱 Seeding initial data...\n";

// --- 4. Seed data ---

// Users
$insertUser = $pdo->prepare("
    INSERT INTO users (name, email, password_hash, role)
    VALUES (:name, :email, :password_hash, :role)
");

$insertUser->execute([
    ':name'          => 'Admin User',
    ':email'         => 'admin@example.com',
    ':password_hash' => password_hash('password', PASSWORD_DEFAULT),
    ':role'          => 'admin',
]);
$adminId = (int)$pdo->lastInsertId();

$insertUser->execute([
    ':name'          => 'Test Student',
    ':email'         => 'student@example.com',
    ':password_hash' => password_hash('password', PASSWORD_DEFAULT),
    ':role'          => 'student',
]);
$studentId = (int)$pdo->lastInsertId();

// Services
$insertService = $pdo->prepare("
    INSERT INTO services (title, description, duration_minutes, is_active)
    VALUES (:title, :description, :duration_minutes, :is_active)
");

$insertService->execute([
    ':title'            => 'Mathematics Tutoring',
    ':description'      => 'Personalized math sessions tailored to your needs.',
    ':duration_minutes' => 60,
    ':is_active'  => 1,
]);
$mathId = (int)$pdo->lastInsertId();

$insertService->execute([
    ':title'            => 'English Tutoring',
    ':description'      => 'Grammar, writing, and comprehension help.',
    ':duration_minutes' => 60,
    ':is_active'  => 1,
]);
$englishId = (int)$pdo->lastInsertId();

$insertService->execute([
    ':title'            => 'Science Tutoring',
    ':description'      => 'Support for physics, chemistry, and biology.',
    ':duration_minutes' => 60,
    ':is_active'  => 1,
]);
$scienceId = (int)$pdo->lastInsertId();

// Timeslots 
$insertSlot = $pdo->prepare("
    INSERT INTO timeslots (service_id, start_time, end_time, is_active)
    VALUES (:service_id, :start_time, :end_time, :is_active)
");

$insertSlot->execute([
    ':service_id' => $mathId,
    ':start_time' => date('Y-m-d 10:00:00', strtotime('+1 day')),
    ':end_time'   => date('Y-m-d 11:00:00', strtotime('+1 day')),
    ':is_active'  => 1,
]);
$slot1 = (int)$pdo->lastInsertId();

$insertSlot->execute([
    ':service_id' => $englishId,
    ':start_time' => date('Y-m-d 13:00:00', strtotime('+1 day')),
    ':end_time'   => date('Y-m-d 14:00:00', strtotime('+1 day')),
    ':is_active'  => 1,
]);
$slot2 = (int)$pdo->lastInsertId();

$insertSlot->execute([
    ':service_id' => $scienceId,
    ':start_time' => date('Y-m-d 15:00:00', strtotime('+1 day')),
    ':end_time'   => date('Y-m-d 16:00:00', strtotime('+1 day')),
    ':is_active'  => 1,
]);
$slot3 = (int)$pdo->lastInsertId();

$insertSlot->execute([
    ':service_id' => $mathId,
    ':start_time' => date('Y-m-d 10:00:00', strtotime('-3 day')),
    ':end_time'   => date('Y-m-d 11:00:00', strtotime('-3 day')),
    ':is_active'  => 1,
]);
$slot4 = (int)$pdo->lastInsertId();

$insertSlot->execute([
    ':service_id' => $englishId,
    ':start_time' => date('Y-m-d 13:00:00', strtotime('+2 day')),
    ':end_time'   => date('Y-m-d 14:00:00', strtotime('+2 day')),
    ':is_active'  => 1,
]);
$slot5 = (int)$pdo->lastInsertId();

$insertSlot->execute([
    ':service_id' => $scienceId,
    ':start_time' => date('Y-m-d 15:00:00', strtotime('+7 day')),
    ':end_time'   => date('Y-m-d 16:00:00', strtotime('+7 day')),
    ':is_active'  => 1,
]);
$slot6 = (int)$pdo->lastInsertId();

$insertSlot->execute([
    ':service_id' => $mathId,
    ':start_time' => date('Y-m-d 10:00:00', strtotime('-5 day')),
    ':end_time'   => date('Y-m-d 11:00:00', strtotime('-5 day')),
    ':is_active'  => 1,
]);
$slot7 = (int)$pdo->lastInsertId();

// Booking for demo student
$insertBooking = $pdo->prepare("
    INSERT INTO bookings (student_id, timeslot_id, status)
    VALUES (:student_id, :timeslot_id, :status)
");

$insertBooking->execute([
    ':student_id'  => $studentId,
    ':timeslot_id' => $slot5, 
    ':status'      => 'approved',
]);

$insertBooking->execute([
    ':student_id'  => $studentId,
    ':timeslot_id' => $slot4, 
    ':status'      => 'approved',
]);

$insertBooking->execute([
    ':student_id'  => $studentId,
    ':timeslot_id' => $slot7, 
    ':status'      => 'approved',
]);

$insertBooking->execute([
    ':student_id'  => $studentId,
    ':timeslot_id' => $slot6, 
    ':status'      => 'pending',
]);

echo "🎉 Database initialized and seeded!\n";
