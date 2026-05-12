CREATE DATABASE IF NOT EXISTS smart_attendance CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE smart_attendance;

CREATE TABLE IF NOT EXISTS students (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  dob DATE NOT NULL,
  department VARCHAR(100) NOT NULL,
  roll_no VARCHAR(50) NOT NULL,
  year VARCHAR(50) NOT NULL,
  mobile VARCHAR(20) NOT NULL,
  face_descriptor TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS attendance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  attendance_date DATE NOT NULL,
  attendance_time TIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_daily_attendance (student_id, attendance_date),
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS attendance_alerts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  alert_type ENUM('low_attendance', 'daily_absent', 'manual') NOT NULL,
  attendance_percentage DECIMAL(5,2),
  message TEXT,
  status ENUM('pending', 'sent', 'read') DEFAULT 'pending',
  email_sent BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

