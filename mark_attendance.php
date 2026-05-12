<?php
header('Content-Type: application/json');
require_once 'db.php';
$data = json_decode(file_get_contents('php://input'), true);
$student_id = isset($data['student_id']) ? (int)$data['student_id'] : 0;
if (!$student_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid student selection.']);
    exit;
}
$stmt = $conn->prepare('SELECT id, department, year FROM students WHERE id = ?');
$stmt->bind_param('i', $student_id);
$stmt->execute();
$result = $stmt->get_result();
if (!$result || $result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Student record not found.']);
    $stmt->close();
    exit;
}
$student = $result->fetch_assoc();
$stmt->close();

// Check if attendance is allowed based on schedule (informational only, doesn't block)
$current_time = date('H:i:s');
$current_day = strtolower(date('l')); // monday, tuesday, etc.
$current_date = date('Y-m-d');

// Find active schedule for this student
$schedule_stmt = $conn->prepare('
    SELECT id FROM schedules 
    WHERE department = ? AND year = ? AND is_active = TRUE 
    LIMIT 1
');
$schedule_stmt->bind_param('ss', $student['department'], $student['year']);
$schedule_stmt->execute();
$schedule_result = $schedule_stmt->get_result();

$attendance_allowed = true;  // Allow by default
$schedule_message = '';

if ($schedule_result->num_rows > 0) {
    $schedule = $schedule_result->fetch_assoc();
    
    // Check if there's an active slot for current day/time
    $slot_stmt = $conn->prepare('
        SELECT subject_name, room_number, start_time, end_time 
        FROM schedule_slots 
        WHERE schedule_id = ? AND day_of_week = ? AND is_active = TRUE 
        AND ? BETWEEN start_time AND end_time
        LIMIT 1
    ');
    $slot_stmt->bind_param('iss', $schedule['id'], $current_day, $current_time);
    $slot_stmt->execute();
    $slot_result = $slot_stmt->get_result();
    
    if ($slot_result->num_rows > 0) {
        $slot = $slot_result->fetch_assoc();
        $schedule_message = " Class: " . $slot['subject_name'] . 
                           ($slot['room_number'] ? " (Room: " . $slot['room_number'] . ")" : "");
    } else {
        $schedule_message = " ⚠️ Outside scheduled class time.";
    }
    $slot_stmt->close();
} else {
    $schedule_message = " (No schedule assigned)";
}
$schedule_stmt->close();

// Note: We allow attendance even outside scheduled hours - schedule is just informational

$today = date('Y-m-d');
$time = date('H:i:s');

// Check if attendance already marked today
$check_stmt = $conn->prepare('SELECT id, attendance_time FROM attendance WHERE student_id = ? AND attendance_date = ?');
$check_stmt->bind_param('is', $student_id, $today);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result && $check_result->num_rows > 0) {
    $existingRecord = $check_result->fetch_assoc();
    $check_stmt->close();
    $conn->close();
    echo json_encode(['success' => false, 'message' => '⚠️ Attendance already marked for today at ' . $existingRecord['attendance_time'] . '. You can only mark attendance once per day.']);
    exit;
}
$check_stmt->close();

try {
    $stmt = $conn->prepare('INSERT INTO attendance (student_id, attendance_date, attendance_time) VALUES (?, ?, ?)');
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param('iss', $student_id, $today, $time);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => '✓ Attendance recorded at ' . $time . '.' . $schedule_message]);
    } else {
        echo json_encode(['success' => false, 'message' => '✗ Failed to record attendance. Please try again.']);
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Attendance Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '✗ Error: ' . $e->getMessage()]);
}
$conn->close();
