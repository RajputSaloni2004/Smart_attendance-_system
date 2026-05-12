<?php
/**
 * Auto-Absent Marker
 * Automatically marks students absent for scheduled classes they missed
 * Should be run daily after class hours (e.g., 6 PM)
 */
require_once 'db.php';

function markAutoAbsents($conn) {
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $absents_marked = 0;

    // Get all active schedules
    $schedule_stmt = $conn->prepare('SELECT id, department, year FROM schedules WHERE is_active = TRUE');
    $schedule_stmt->execute();
    $schedule_result = $schedule_stmt->get_result();

    while ($schedule = $schedule_result->fetch_assoc()) {
        // Get all slots for yesterday
        $yesterday_day = strtolower(date('l', strtotime($yesterday)));

        $slot_stmt = $conn->prepare('
            SELECT id, start_time, end_time, subject_name
            FROM schedule_slots
            WHERE schedule_id = ? AND day_of_week = ? AND is_active = TRUE
        ');
        $slot_stmt->bind_param('is', $schedule['id'], $yesterday_day);
        $slot_stmt->execute();
        $slot_result = $slot_stmt->get_result();

        while ($slot = $slot_result->fetch_assoc()) {
            // Get all students in this department/year who didn't attend this slot
            $absent_stmt = $conn->prepare('
                SELECT s.id, s.name
                FROM students s
                LEFT JOIN attendance a ON s.id = a.student_id
                    AND a.attendance_date = ?
                    AND a.attendance_time BETWEEN ? AND ?
                WHERE s.department = ? AND s.year = ?
                AND a.id IS NULL
            ');
            $absent_stmt->bind_param('sssss', $yesterday, $slot['start_time'], $slot['end_time'],
                                   $schedule['department'], $schedule['year']);
            $absent_stmt->execute();
            $absent_result = $absent_stmt->get_result();

            while ($student = $absent_result->fetch_assoc()) {
                // Mark as absent by creating an alert (we don't add attendance record for absent)
                $check_alert = $conn->prepare('
                    SELECT id FROM attendance_alerts
                    WHERE student_id = ? AND alert_type = "daily_absent"
                    AND DATE(created_at) = CURDATE()
                ');
                $check_alert->bind_param('i', $student['id']);
                $check_alert->execute();

                if ($check_alert->get_result()->num_rows === 0) {
                    $message = "Absent for " . $slot['subject_name'] . " on " . date('M d, Y', strtotime($yesterday)) .
                              " (" . date('H:i', strtotime($slot['start_time'])) . " - " . date('H:i', strtotime($slot['end_time'])) . ")";

                    $insert_alert = $conn->prepare('
                        INSERT INTO attendance_alerts (student_id, alert_type, message, status)
                        VALUES (?, "daily_absent", ?, "pending")
                    ');
                    $insert_alert->bind_param('is', $student['id'], $message);
                    $insert_alert->execute();
                    $absents_marked++;
                    $insert_alert->close();
                }
                $check_alert->close();
            }
            $absent_stmt->close();
        }
        $slot_stmt->close();
    }
    $schedule_stmt->close();

    return $absents_marked;
}

// Run auto-absent marking if called directly
if (isset($_GET['action']) && $_GET['action'] === 'mark_absents') {
    $marked = markAutoAbsents($conn);
    echo json_encode(['success' => true, 'absents_marked' => $marked]);
} else {
    // Can be called from cron: php auto_absent_marker.php?action=mark_absents
    echo "Auto-absent marker loaded. Call with ?action=mark_absents to mark yesterday's absents.";
}

$conn->close();
?>