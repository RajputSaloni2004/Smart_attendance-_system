<?php
session_start();
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: admin_login.php');
    exit;
}
require_once 'db.php';

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_attendance'])) {
        // Add manual attendance
        $student_id = (int)$_POST['student_id'];
        $attendance_date = $_POST['attendance_date'];
        $attendance_time = $_POST['attendance_time'];
        $reason = trim($_POST['reason']);

        if (empty($attendance_date) || empty($attendance_time)) {
            $error = "Please provide date and time.";
        } else {
            // Check if attendance already exists
            $check_stmt = $conn->prepare('
                SELECT id FROM attendance 
                WHERE student_id = ? AND attendance_date = ?
            ');
            $check_stmt->bind_param('is', $student_id, $attendance_date);
            $check_stmt->execute();
            $existing = $check_stmt->get_result();

            if ($existing->num_rows > 0) {
                $error = "Attendance already exists for this student on this date.";
            } else {
                $insert_stmt = $conn->prepare('
                    INSERT INTO attendance (student_id, attendance_date, attendance_time) 
                    VALUES (?, ?, ?)
                ');
                $insert_stmt->bind_param('iss', $student_id, $attendance_date, $attendance_time);

                if ($insert_stmt->execute()) {
                    // Log the manual entry
                    $alert_stmt = $conn->prepare('
                        INSERT INTO attendance_alerts (student_id, alert_type, message, status)
                        VALUES (?, "manual", ?, "sent")
                    ');
                    $message_text = "Manual attendance added for " . date('M d, Y', strtotime($attendance_date)) . 
                                  " at " . $attendance_time . ". Reason: " . ($reason ?: "Not specified");
                    $alert_stmt->bind_param('is', $student_id, $message_text);
                    $alert_stmt->execute();
                    $alert_stmt->close();

                    $message = "Manual attendance added successfully.";
                } else {
                    $error = "Failed to add attendance.";
                }
                $insert_stmt->close();
            }
            $check_stmt->close();
        }
    } elseif (isset($_POST['remove_attendance'])) {
        // Remove attendance
        $attendance_id = (int)$_POST['attendance_id'];
        $reason = trim($_POST['remove_reason']);

        $delete_stmt = $conn->prepare('DELETE FROM attendance WHERE id = ?');
        $delete_stmt->bind_param('i', $attendance_id);

        if ($delete_stmt->execute()) {
            if ($delete_stmt->affected_rows > 0) {
                $message = "Attendance record removed successfully.";
            } else {
                $error = "Attendance record not found.";
            }
        } else {
            $error = "Failed to remove attendance.";
        }
        $delete_stmt->close();
    }
}

// Get students for dropdown
$students = [];
$stmt = $conn->prepare('SELECT id, name, roll_no, department FROM students ORDER BY name');
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}
$stmt->close();

// Get recent manual attendance changes (last 50)
$recent_changes = [];
$stmt = $conn->prepare('
    SELECT a.id, a.student_id, a.attendance_date, a.attendance_time, a.created_at,
           s.name, s.roll_no, s.department
    FROM attendance a
    JOIN students s ON a.student_id = s.id
    ORDER BY a.created_at DESC
    LIMIT 50
');
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $recent_changes[] = $row;
}
$stmt->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Manual Attendance Override | Smart Attendance</title>
    <link rel="stylesheet" href="assets/css/style.css" />
    <style>
        .override-section {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid var(--border);
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }
        .recent-changes {
            margin-top: 30px;
        }
        .changes-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }
        .changes-table th,
        .changes-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        .changes-table th {
            background: var(--primary);
            color: white;
            font-weight: 600;
        }
        .student-selector {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 12px;
            background: white;
        }
        .student-checkbox {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            padding: 8px;
            border-radius: 4px;
            background: #f8fafc;
        }
        .student-checkbox input {
            margin-right: 12px;
        }
        .student-info {
            flex: 1;
        }
        .student-name {
            font-weight: 500;
        }
        .student-details {
            font-size: 0.9rem;
            color: var(--text-muted);
        }
    </style>
</head>
<body>
    <main class="container">
        <header class="page-header">
            <h1>Manual Attendance Override</h1>
            <p>Add or remove attendance records</p>
            <a href="admin_dashboard.php" class="button secondary" style="margin-top: 14px; display: inline-block;">← Back to Dashboard</a>
        </header>

        <?php if ($message): ?>
            <div class="alert success" style="margin-bottom: 20px;"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert error" style="margin-bottom: 20px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
            <!-- Add Manual Attendance -->
            <div class="override-section">
                <h2>➕ Add Manual Attendance</h2>
                <form method="post">
                    <input type="hidden" name="add_attendance" value="1" />
                    <div class="form-grid">
                        <label>
                            <span>Student</span>
                            <select name="student_id" required>
                                <option value="">Select student</option>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?= $student['id'] ?>">
                                        <?= htmlspecialchars($student['name']) ?> (<?= htmlspecialchars($student['roll_no']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            <span>Date</span>
                            <input type="date" name="attendance_date" value="<?= date('Y-m-d') ?>" required />
                        </label>
                        <label>
                            <span>Time</span>
                            <input type="time" name="attendance_time" value="<?= date('H:i') ?>" required />
                        </label>
                    </div>
                    <label>
                        <span>Reason (Optional)</span>
                        <textarea name="reason" rows="2" placeholder="Reason for manual attendance entry"></textarea>
                    </label>
                    <button class="button primary" type="submit">Add Attendance</button>
                </form>
            </div>

        </div>

        <!-- Recent Changes -->
        <div class="recent-changes">
            <h2>Recent Attendance Changes</h2>
            <div style="overflow-x: auto;">
                <table class="changes-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Added On</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_changes as $change): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($change['name']) ?></strong><br>
                                    <small style="color: var(--text-muted);">
                                        <?= htmlspecialchars($change['roll_no']) ?> • 
                                        <?= htmlspecialchars($change['department']) ?>
                                    </small>
                                </td>
                                <td><?= date('M d, Y', strtotime($change['attendance_date'])) ?></td>
                                <td><?= date('H:i', strtotime($change['attendance_time'])) ?></td>
                                <td><?= date('M d, Y H:i', strtotime($change['created_at'])) ?></td>
                                <td>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="remove_attendance" value="1" />
                                        <input type="hidden" name="attendance_id" value="<?= $change['id'] ?>" />
                                        <input type="hidden" name="remove_reason" value="Administrative removal" />
                                        <button class="button small danger" type="submit" 
                                                onclick="return confirm('Remove this attendance record?')">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div style="margin-top: 30px;">
            <a href="admin_dashboard.php" class="button secondary">← Back to Dashboard</a>
            <a href="view_attendance.php" class="button primary" style="margin-left: 12px;">View Attendance</a>
        </div>
    </main>

    <footer class="footer-bar">
        <p>&copy; 2026 <strong>Smart Attendance System</strong>. All rights reserved.</p>
    </footer>

</body>
</html>
