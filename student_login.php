<?php
session_start();
if (isset($_SESSION['student_id'])) {
    header('Location: student_dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'db.php';

    $roll_no = trim($_POST['roll_no'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');

    if (empty($roll_no) || empty($mobile)) {
        $error = 'Please enter both roll number and mobile number.';
    } else {
        $stmt = $conn->prepare('SELECT id, name FROM students WHERE roll_no = ? AND mobile = ?');
        $stmt->bind_param('ss', $roll_no, $mobile);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $student = $result->fetch_assoc();
            $_SESSION['student_id'] = $student['id'];
            $_SESSION['student_name'] = $student['name'];
            header('Location: student_dashboard.php');
            exit;
        } else {
            $error = 'Invalid roll number or mobile number. Please try again.';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Student Login | Smart Attendance</title>
    <link rel="stylesheet" href="assets/css/style.css" />
</head>
<body class="auth-page">
    <div class="auth-card">
        <h1>Student Portal</h1>
        <p class="subtitle">Access your attendance records and view your progress.</p>
        <?php if ($error): ?>
            <div class="alert error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post">
            <label for="roll_no">Roll Number</label>
            <input type="text" name="roll_no" id="roll_no" placeholder="Enter your roll number" required />

            <label for="mobile">Mobile Number</label>
            <input type="tel" name="mobile" id="mobile" placeholder="Enter your registered mobile number" required />

            <button class="button primary" type="submit">Login to Portal</button>
        </form>
        <div style="text-align: center; margin-top: 20px;">
            <a class="link-button" href="index.php">← Back to Home</a>
            <br>
            <a class="link-button" href="student_attendance.php" style="margin-top: 10px;">Mark Attendance Instead</a>
        </div>
    </div>

    <footer class="footer-bar" style="position: fixed; bottom: 0; width: 100%; left: 0;">
        <p>&copy; 2026 <strong>Smart Attendance System</strong>. All rights reserved.</p>
    </footer>
</body>
</html>
