<?php
session_start();
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: admin_login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Dashboard | Smart Attendance</title>
    <link rel="stylesheet" href="assets/css/style.css" />
</head>
<body>
    <header class="app-header">
        <div class="brand">Admin Dashboard</div>
        <div class="top-actions">
            <a class="button secondary" href="index.php">Home</a>
            <a class="button tertiary" href="logout.php">Logout</a>
        </div>
    </header>

    <main class="dashboard-grid">
        <section class="dashboard-card">
            <h2>Register Student</h2>
            <p>Easily add student profiles and store face data for attendance recognition.</p>
            <a class="button primary" href="register_student.php">Add Student</a>
        </section>
        <section class="dashboard-card">
            <h2>Bulk Import Students</h2>
            <p>Import multiple students at once from CSV file for efficient registration.</p>
            <a class="button primary" href="bulk_import.php">Import CSV</a>
        </section>
        <section class="dashboard-card">
            <h2>Manage Students</h2>
            <p>Update student information or remove students from the system.</p>
            <a class="button primary" href="manage_students.php">Manage Students</a>
        </section>
        <section class="dashboard-card">
            <h2>Attendance Records</h2>
            <p>View attendance by date and student with a polished professional report layout.</p>
            <a class="button primary" href="view_attendance.php">View Attendance</a>
        </section>
        <section class="dashboard-card">
            <h2>Manual Override</h2>
            <p>Add or remove attendance records manually, and bulk-mark students absent.</p>
            <a class="button primary" href="manual_override.php">Override Attendance</a>
        </section>
        <section class="dashboard-card">
            <h2>Attendance Alerts</h2>
            <p>Monitor low attendance alerts and send email notifications to students.</p>
            <a class="button primary" href="manage_alerts.php">Manage Alerts</a>
        </section>
        <section class="dashboard-card">
            <h2>Class Schedules</h2>
            <p>Define class timetables and manage attendance slots for different departments.</p>
            <a class="button primary" href="manage_schedules.php">Manage Schedules</a>
        </section>
        <section class="dashboard-card">
            <h2>Analytics Dashboard</h2>
            <p>View comprehensive attendance statistics, trends, and performance insights.</p>
            <a class="button primary" href="analytics_dashboard.php">View Analytics</a>
        </section>
    </main>

    <footer class="footer-bar">
        <p>&copy; 2026 <strong>Smart Attendance System</strong>. All rights reserved.</p>
    </footer>
</body>
</html>
