<?php
session_start();
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: admin_login.php');
    exit;
}
require_once 'db.php';

$message = '';
$error = '';
$action = $_POST['action'] ?? '';

// Create new schedule
if ($action === 'create_schedule') {
    $name = trim($_POST['name']);
    $department = trim($_POST['department']);
    $year = trim($_POST['year']);
    $description = trim($_POST['description']);

    if (empty($name) || empty($department) || empty($year)) {
        $error = "Please fill in all required fields.";
    } else {
        $stmt = $conn->prepare('INSERT INTO schedules (name, department, year, description) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('ssss', $name, $department, $year, $description);
        if ($stmt->execute()) {
            $message = "Schedule created successfully.";
        } else {
            $error = "Failed to create schedule.";
        }
        $stmt->close();
    }
}

// Add slot to schedule
if ($action === 'add_slot') {
    $schedule_id = (int)$_POST['schedule_id'];
    $day_of_week = $_POST['day_of_week'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $subject_name = trim($_POST['subject_name']);
    $room_number = trim($_POST['room_number']);

    if (empty($subject_name) || empty($start_time) || empty($end_time)) {
        $error = "Please fill in all required fields.";
    } elseif ($start_time >= $end_time) {
        $error = "End time must be after start time.";
    } else {
        $stmt = $conn->prepare('INSERT INTO schedule_slots (schedule_id, day_of_week, start_time, end_time, subject_name, room_number) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('isssss', $schedule_id, $day_of_week, $start_time, $end_time, $subject_name, $room_number);
        if ($stmt->execute()) {
            $message = "Schedule slot added successfully.";
        } else {
            $error = "Failed to add schedule slot.";
        }
        $stmt->close();
    }
}

// Delete schedule
if ($action === 'delete_schedule') {
    $schedule_id = (int)$_POST['schedule_id'];
    $stmt = $conn->prepare('DELETE FROM schedules WHERE id = ?');
    $stmt->bind_param('i', $schedule_id);
    if ($stmt->execute()) {
        $message = "Schedule deleted successfully.";
    } else {
        $error = "Failed to delete schedule.";
    }
    $stmt->close();
}

// Delete slot
if ($action === 'delete_slot') {
    $slot_id = (int)$_POST['slot_id'];
    $stmt = $conn->prepare('DELETE FROM schedule_slots WHERE id = ?');
    $stmt->bind_param('i', $slot_id);
    if ($stmt->execute()) {
        $message = "Schedule slot deleted successfully.";
    } else {
        $error = "Failed to delete schedule slot.";
    }
    $stmt->close();
}

// Get all schedules with their slots
$schedules = [];
$stmt = $conn->prepare('SELECT * FROM schedules ORDER BY department, year, name');
$stmt->execute();
$result = $stmt->get_result();
while ($schedule = $result->fetch_assoc()) {
    $schedule_id = $schedule['id'];

    // Get slots for this schedule
    $slotsStmt = $conn->prepare('SELECT * FROM schedule_slots WHERE schedule_id = ? ORDER BY FIELD(day_of_week, "monday", "tuesday", "wednesday", "thursday", "friday", "saturday", "sunday"), start_time');
    $slotsStmt->bind_param('i', $schedule_id);
    $slotsStmt->execute();
    $slotsResult = $slotsStmt->get_result();
    $slots = [];
    while ($slot = $slotsResult->fetch_assoc()) {
        $slots[] = $slot;
    }
    $slotsStmt->close();

    $schedule['slots'] = $slots;
    $schedules[] = $schedule;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Schedule Management | Smart Attendance</title>
    <link rel="stylesheet" href="assets/css/style.css" />
    <style>
        .schedule-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            border: 1px solid var(--border);
        }
        .schedule-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        .schedule-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        .schedule-meta {
            font-size: 0.9rem;
            color: var(--text-muted);
        }
        .slots-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            margin-top: 16px;
        }
        .slot-item {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            padding: 12px;
            border-radius: 8px;
            border-left: 3px solid var(--primary);
        }
        .slot-time {
            font-weight: 600;
            color: var(--primary-strong);
        }
        .slot-subject {
            margin-top: 4px;
            font-size: 0.9rem;
        }
        .slot-room {
            font-size: 0.8rem;
            color: var(--text-muted);
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }
        .day-selector {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 8px;
            margin-bottom: 16px;
        }
        .day-checkbox {
            display: flex;
            align-items: center;
            gap: 4px;
        }
    </style>
</head>
<body>
    <main class="container">
        <header class="page-header">
            <h1>Class Schedule Management</h1>
            <p>Create and manage class timetables for attendance tracking</p>
        </header>

        <?php if ($message): ?>
            <div class="alert success" style="margin-bottom: 20px;"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert error" style="margin-bottom: 20px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Create New Schedule -->
        <section class="data-section">
            <h2>Create New Schedule</h2>
            <form method="post">
                <input type="hidden" name="action" value="create_schedule" />
                <div class="form-grid">
                    <label>
                        <span>Schedule Name</span>
                        <input type="text" name="name" placeholder="e.g., Computer Science 2026" required />
                    </label>
                    <label>
                        <span>Department</span>
                        <input type="text" name="department" placeholder="e.g., Computer Science" required />
                    </label>
                    <label>
                        <span>Year</span>
                        <select name="year" required>
                            <option value="">Select year</option>
                            <option value="1st Year">1st Year</option>
                            <option value="2nd Year">2nd Year</option>
                            <option value="3rd Year">3rd Year</option>
                            <option value="Final Year">Final Year</option>
                        </select>
                    </label>
                </div>
                <label>
                    <span>Description (Optional)</span>
                    <textarea name="description" rows="2" placeholder="Additional notes about this schedule"></textarea>
                </label>
                <button class="button primary" type="submit">Create Schedule</button>
            </form>
        </section>

        <!-- Existing Schedules -->
        <section class="data-section">
            <h2>Existing Schedules</h2>
            <?php if (count($schedules) === 0): ?>
                <p class="empty-state">No schedules created yet.</p>
            <?php else: ?>
                <?php foreach ($schedules as $schedule): ?>
                    <div class="schedule-card">
                        <div class="schedule-header">
                            <div>
                                <div class="schedule-title"><?= htmlspecialchars($schedule['name']) ?></div>
                                <div class="schedule-meta">
                                    <?= htmlspecialchars($schedule['department']) ?> • <?= htmlspecialchars($schedule['year']) ?>
                                    <?php if ($schedule['description']): ?>
                                        • <?= htmlspecialchars($schedule['description']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="action" value="delete_schedule" />
                                    <input type="hidden" name="schedule_id" value="<?= $schedule['id'] ?>" />
                                    <button class="button small danger" type="submit"
                                            onclick="return confirm('Delete this schedule and all its slots?')">Delete Schedule</button>
                                </form>
                            </div>
                        </div>

                        <!-- Add Slot Form -->
                        <details style="margin-bottom: 16px;">
                            <summary style="cursor: pointer; font-weight: 500; margin-bottom: 12px;">+ Add Schedule Slot</summary>
                            <form method="post" style="background: #f8fafc; padding: 16px; border-radius: 8px;">
                                <input type="hidden" name="action" value="add_slot" />
                                <input type="hidden" name="schedule_id" value="<?= $schedule['id'] ?>" />
                                <div class="form-grid">
                                    <label>
                                        <span>Day of Week</span>
                                        <select name="day_of_week" required>
                                            <option value="">Select day</option>
                                            <option value="monday">Monday</option>
                                            <option value="tuesday">Tuesday</option>
                                            <option value="wednesday">Wednesday</option>
                                            <option value="thursday">Thursday</option>
                                            <option value="friday">Friday</option>
                                            <option value="saturday">Saturday</option>
                                            <option value="sunday">Sunday</option>
                                        </select>
                                    </label>
                                    <label>
                                        <span>Start Time</span>
                                        <input type="time" name="start_time" required />
                                    </label>
                                    <label>
                                        <span>End Time</span>
                                        <input type="time" name="end_time" required />
                                    </label>
                                    <label>
                                        <span>Subject Name</span>
                                        <input type="text" name="subject_name" placeholder="e.g., Data Structures" required />
                                    </label>
                                    <label>
                                        <span>Room Number (Optional)</span>
                                        <input type="text" name="room_number" placeholder="e.g., Room 101" />
                                    </label>
                                </div>
                                <button class="button primary" type="submit">Add Slot</button>
                            </form>
                        </details>

                        <!-- Schedule Slots -->
                        <?php if (count($schedule['slots']) > 0): ?>
                            <div class="slots-grid">
                                <?php foreach ($schedule['slots'] as $slot): ?>
                                    <div class="slot-item">
                                        <div class="slot-time">
                                            <?= ucfirst($slot['day_of_week']) ?> •
                                            <?= date('H:i', strtotime($slot['start_time'])) ?> -
                                            <?= date('H:i', strtotime($slot['end_time'])) ?>
                                        </div>
                                        <div class="slot-subject">
                                            <strong><?= htmlspecialchars($slot['subject_name']) ?></strong>
                                        </div>
                                        <?php if ($slot['room_number']): ?>
                                            <div class="slot-room">Room: <?= htmlspecialchars($slot['room_number']) ?></div>
                                        <?php endif; ?>
                                        <form method="post" style="margin-top: 8px;">
                                            <input type="hidden" name="action" value="delete_slot" />
                                            <input type="hidden" name="slot_id" value="<?= $slot['id'] ?>" />
                                            <button class="button small danger" type="submit"
                                                    onclick="return confirm('Delete this slot?')">Delete</button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p style="color: var(--text-muted); font-style: italic;">No schedule slots added yet.</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <div style="margin-top: 24px;">
            <a href="admin_dashboard.php" class="button secondary">← Back to Dashboard</a>
        </div>
    </main>

    <footer class="footer-bar">
        <p>&copy; 2026 <strong>Smart Attendance System</strong>. All rights reserved.</p>
    </footer>
</body>
</html>
