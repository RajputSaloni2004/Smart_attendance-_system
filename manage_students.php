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
    if (isset($_POST['delete'])) {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare('DELETE FROM students WHERE id = ?');
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            $message = 'Student deleted successfully.';
        } else {
            $error = 'Failed to delete student.';
        }
        $stmt->close();
    } elseif (isset($_POST['update'])) {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name']);
        $dob = $_POST['dob'];
        $department = trim($_POST['department']);
        $roll_no = trim($_POST['roll_no']);
        $year = trim($_POST['year']);
        $mobile = trim($_POST['mobile']);
        if ($name && $dob && $department && $roll_no && $year && $mobile) {
            $stmt = $conn->prepare('UPDATE students SET name = ?, dob = ?, department = ?, roll_no = ?, year = ?, mobile = ? WHERE id = ?');
            $stmt->bind_param('ssssssi', $name, $dob, $department, $roll_no, $year, $mobile, $id);
            if ($stmt->execute()) {
                $message = 'Student updated successfully.';
            } else {
                $error = 'Failed to update student.';
            }
            $stmt->close();
        } else {
            $error = 'All fields are required.';
        }
    }
}

$students = [];
$result = $conn->query('SELECT * FROM students ORDER BY created_at DESC');
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
    <title>Manage Students | Smart Attendance</title>
    <link rel="stylesheet" href="assets/css/style.css" />
</head>
<body>
    <header class="app-header">
        <div class="brand">Manage Students</div>
        <div class="top-actions">
            <a class="button secondary" href="admin_dashboard.php">Dashboard</a>
            <a class="button tertiary" href="logout.php">Logout</a>
        </div>
    </header>

    <main class="container">
        <h1>Manage Students</h1>
        <?php if ($message): ?>
            <div class="alert success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>DOB</th>
                        <th>Department</th>
                        <th>Roll No</th>
                        <th>Year</th>
                        <th>Mobile</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td><?= htmlspecialchars($student['id']) ?></td>
                            <td>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="id" value="<?= $student['id'] ?>" />
                                    <input type="text" name="name" value="<?= htmlspecialchars($student['name']) ?>" required />
                            </td>
                            <td>
                                    <input type="date" name="dob" value="<?= htmlspecialchars($student['dob']) ?>" required />
                            </td>
                            <td>
                                    <input type="text" name="department" value="<?= htmlspecialchars($student['department']) ?>" required />
                            </td>
                            <td>
                                    <input type="text" name="roll_no" value="<?= htmlspecialchars($student['roll_no']) ?>" required />
                            </td>
                            <td>
                                    <input type="text" name="year" value="<?= htmlspecialchars($student['year']) ?>" required />
                            </td>
                            <td>
                                    <input type="text" name="mobile" value="<?= htmlspecialchars($student['mobile']) ?>" required />
                            </td>
                            <td>
                                    <button type="submit" name="update" class="button small primary">Update</button>
                                </form>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this student?')">
                                    <input type="hidden" name="id" value="<?= $student['id'] ?>" />
                                    <button type="submit" name="delete" class="button small danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <footer class="footer-bar">
        <p>&copy; 2026 <strong>Smart Attendance System</strong>. All rights reserved.</p>
    </footer>
</body>
</html>