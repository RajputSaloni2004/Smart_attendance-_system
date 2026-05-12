<?php
$host = 'localhost';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

$sql = file_get_contents(__DIR__ . '/init.sql');
$statements = array_filter(array_map('trim', explode(';', $sql)));

foreach ($statements as $statement) {
    if (!empty($statement)) {
        if ($conn->query($statement) === TRUE) {
            echo "Executed: " . substr($statement, 0, 50) . "...\n";
        } else {
            echo "Error: " . $conn->error . "\n";
        }
    }
}

// Add roll_no column if the table already exists but the column is missing.
$result = $conn->query("SHOW COLUMNS FROM students LIKE 'roll_no'");
if ($result && $result->num_rows === 0) {
    if ($conn->query("ALTER TABLE students ADD COLUMN roll_no VARCHAR(50) NOT NULL AFTER department") === TRUE) {
        echo "Added missing roll_no column to students table.\n";
    } else {
        echo "Error adding roll_no column: " . $conn->error . "\n";
    }
}

// Add email column if the table already exists but the column is missing.
$result = $conn->query("SHOW COLUMNS FROM students LIKE 'email'");
if ($result && $result->num_rows === 0) {
    if ($conn->query("ALTER TABLE students ADD COLUMN email VARCHAR(150) DEFAULT NULL AFTER mobile") === TRUE) {
        echo "Added missing email column to students table.\n";
    } else {
        echo "Error adding email column: " . $conn->error . "\n";
    }
}

// Create attendance_alerts table if it doesn't exist
$checkAlertsTable = $conn->query("SHOW TABLES LIKE 'attendance_alerts'");
if ($checkAlertsTable && $checkAlertsTable->num_rows === 0) {
    $createAlertsTable = "CREATE TABLE attendance_alerts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        alert_type ENUM('low_attendance', 'daily_absent', 'manual') NOT NULL,
        attendance_percentage DECIMAL(5,2),
        message TEXT,
        status ENUM('pending', 'sent', 'read') DEFAULT 'pending',
        sms_sent BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($createAlertsTable) === TRUE) {
        echo "Created attendance_alerts table.\n";
    } else {
        echo "Error creating attendance_alerts table: " . $conn->error . "\n";
    }
} else {
    // Check if sms_sent column exists, add it if missing
    $result = $conn->query("SHOW COLUMNS FROM attendance_alerts LIKE 'sms_sent'");
    if ($result && $result->num_rows === 0) {
        if ($conn->query("ALTER TABLE attendance_alerts ADD COLUMN sms_sent BOOLEAN DEFAULT FALSE AFTER status") === TRUE) {
            echo "Added missing sms_sent column to attendance_alerts table.\n";
        } else {
            echo "Error adding sms_sent column: " . $conn->error . "\n";
        }
    }
}

// Create schedules table if it doesn't exist
$checkSchedulesTable = $conn->query("SHOW TABLES LIKE 'schedules'");
if ($checkSchedulesTable && $checkSchedulesTable->num_rows === 0) {
    $createSchedulesTable = "CREATE TABLE schedules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        department VARCHAR(100) NOT NULL,
        year VARCHAR(50) NOT NULL,
        description TEXT,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($createSchedulesTable) === TRUE) {
        echo "Created schedules table.\n";
    } else {
        echo "Error creating schedules table: " . $conn->error . "\n";
    }
}

// Create schedule_slots table if it doesn't exist
$checkSlotsTable = $conn->query("SHOW TABLES LIKE 'schedule_slots'");
if ($checkSlotsTable && $checkSlotsTable->num_rows === 0) {
    $createSlotsTable = "CREATE TABLE schedule_slots (
        id INT AUTO_INCREMENT PRIMARY KEY,
        schedule_id INT NOT NULL,
        day_of_week ENUM('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday') NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        subject_name VARCHAR(100) NOT NULL,
        room_number VARCHAR(50),
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (schedule_id) REFERENCES schedules(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($createSlotsTable) === TRUE) {
        echo "Created schedule_slots table.\n";
    } else {
        echo "Error creating schedule_slots table: " . $conn->error . "\n";
    }
}

$conn->close();
echo "Database setup complete.\n";
?>