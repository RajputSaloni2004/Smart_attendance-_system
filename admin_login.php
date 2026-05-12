<?php
session_start();
if (isset($_SESSION['admin']) && $_SESSION['admin'] === true) {
    header('Location: admin_dashboard.php');
    exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    if ($username === 'admin' && $password === 'admin123') {
        $_SESSION['admin'] = true;
        header('Location: admin_dashboard.php');
        exit;
    }
    $error = 'Invalid username or password. Please try again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Login | Smart Attendance</title>
    <link rel="stylesheet" href="assets/css/style.css" />
</head>
<body class="auth-page">
    <div class="auth-card">
        <h1>Admin Login</h1>
        <p class="subtitle">Secure access to register students and review attendance.</p>
        <?php if ($error): ?>
            <div class="alert error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post">
            <label for="username">Username</label>
            <input type="text" name="username" id="username" placeholder="Enter admin username" required />
            <label for="password">Password</label>
            <input type="password" name="password" id="password" placeholder="Enter admin password" required />
            <button class="button primary" type="submit">Sign In</button>
        </form>
        <a class="link-button" href="index.php">Return to Home</a>
    </div>

    <footer class="footer-bar" style="position: fixed; bottom: 0; width: 100%; left: 0;">
        <p>&copy; 2026 <strong>Smart Attendance System</strong>. All rights reserved.</p>
    </footer>
</body>
</html>
