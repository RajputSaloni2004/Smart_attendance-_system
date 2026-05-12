<?php
session_start();
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: admin_login.php');
    exit;
}
require_once 'db.php';
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $dob = $_POST['dob'] ?? '';
    $department = trim($_POST['department'] ?? '');
    $roll_no = trim($_POST['roll_no'] ?? '');
    $year = trim($_POST['year'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $face_descriptor = trim($_POST['face_descriptor'] ?? '');

    if (!$name || !$dob || !$department || !$roll_no || !$year || !$mobile || !$face_descriptor) {
        $error = 'Please fill all fields and capture a student face before submitting.';
    } elseif ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $stmt = $conn->prepare('INSERT INTO students (name,dob,department,roll_no,year,mobile,email,face_descriptor) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('ssssssss', $name, $dob, $department, $roll_no, $year, $mobile, $email, $face_descriptor);
        if ($stmt->execute()) {
            $message = 'Student registered successfully. You can now use the student attendance module.';
        } else {
            $error = 'Unable to register student. Please try again.';
        }
        $stmt->close();
    }
}
$students = [];
$result = $conn->query('SELECT id, name, department, year FROM students ORDER BY created_at DESC LIMIT 12');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Register Student | Smart Attendance</title>
    <link rel="stylesheet" href="assets/css/style.css" />
    <script src="assets/js/face-api.min.js"></script>
    <script src="assets/js/register-student.js"></script>
</head>
<body>
    <header class="app-header">
        <div class="brand">Register Student</div>
        <div class="top-actions">
            <a class="button secondary" href="admin_dashboard.php">Dashboard</a>
            <a class="button tertiary" href="logout.php">Logout</a>
        </div>
    </header>
    <main class="page-shell">
        <section class="form-panel">
            <h1>Student Registration</h1>
            <p class="subtitle">Add new students and capture their facial profile for AI attendance.</p>
            <?php if ($message): ?>
                <div class="alert success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="post" id="studentForm">
                <div class="field-grid">
                    <label><span>Name</span><input type="text" name="name" required /></label>
                    <label><span>Date of Birth</span><input type="date" name="dob" required /></label>
                    <label><span>Department</span><input type="text" name="department" required /></label>
                    <label><span>Roll No</span><input type="text" name="roll_no" required placeholder="Student roll number" /></label>
                    <label><span>Year</span>
                        <select name="year" required>
                            <option value="">Select academic year</option>
                            <option value="1st Year">1st Year</option>
                            <option value="2nd Year">2nd Year</option>
                            <option value="3rd Year">3rd Year</option>
                            <option value="Final Year">Final Year</option>
                        </select>
                    </label>
                    <label><span>Mobile Number</span><input type="tel" name="mobile" required placeholder="Mobile number" /></label>
                    <label><span>Email Address</span><input type="email" name="email" placeholder="Optional email for alerts" /></label>
                </div>
                <div class="face-panel">
                    <div class="camera-card">
                        <h3>Face Capture</h3>
                        <p id="modelStatus">Loading face recognition models...</p>
                        <div style="position:relative;">
                            <video id="registerVideo" muted playsinline width="640" height="480"></video>
                            <canvas id="overlayCanvas" style="position:absolute; top:0; left:0; pointer-events:none;"></canvas>
                        </div>
                        <div class="video-actions">
                            <button type="button" class="button tertiary" id="startCameraBtn">Start Camera</button>
                            <button type="button" class="button secondary" id="captureFaceBtn" disabled>Capture Face</button>
                            <span id="faceStatus">Waiting for camera...</span>
                        </div>
                    </div>
                    <div class="textarea-panel">
                        <label><span>Face Descriptor</span>
                            <textarea id="faceDescriptor" name="face_descriptor" readonly rows="8" placeholder="Capture a face to encode descriptor"></textarea>
                        </label>
                    </div>
                </div>
                <div class="preview-panel">
                    <h3>Preview</h3>
                    <div class="preview-card">
                        <img id="facePreview" alt="Face preview will appear here" src="" />
                        <p id="previewHint">When the circle turns green, your captured face preview will display here.</p>
                    </div>
                </div>
                <div class="form-actions">
                    <button class="button primary" type="submit">Register Student</button>
                </div>
            </form>
        </section>

        <section class="data-panel">
            <h2>Recent Students</h2>
            <div class="data-grid">
                <?php if (count($students) === 0): ?>
                    <p class="empty-state">No students have been registered yet.</p>
                <?php endif; ?>
                <?php foreach ($students as $student): ?>
                    <article class="data-card">
                        <strong><?= htmlspecialchars($student['name']) ?></strong>
                        <p><?= htmlspecialchars($student['department']) ?> • <?= htmlspecialchars($student['year']) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    </main>

    <footer class="footer-bar">
        <p>&copy; 2026 <strong>Smart Attendance System</strong>. All rights reserved.</p>
    </footer>
</body>
</html>
