<?php
// Test database setup
require_once 'db.php';

echo "Testing database connection...\n";

if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

echo "Connected successfully!\n";

// Check existing tables
$result = $conn->query("SHOW TABLES");
$tables = [];
while ($row = $result->fetch_array()) {
    $tables[] = $row[0];
}

echo "\nExisting tables:\n";
foreach ($tables as $table) {
    echo "- $table\n";
}

// Check if required tables exist
$required_tables = ['students', 'attendance', 'attendance_alerts', 'schedules', 'schedule_slots'];
$missing_tables = [];

foreach ($required_tables as $table) {
    if (!in_array($table, $tables)) {
        $missing_tables[] = $table;
    }
}

if (empty($missing_tables)) {
    echo "\n✅ All required tables exist!\n";
} else {
    echo "\n❌ Missing tables: " . implode(', ', $missing_tables) . "\n";
    echo "Please run setup_db.php to create missing tables.\n";
}

$conn->close();
?>