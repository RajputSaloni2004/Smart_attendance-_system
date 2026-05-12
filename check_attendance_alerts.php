<?php
/**
 * Attendance Alert Generator
 * Calculates attendance percentage and triggers alerts for low attendance
 * Can be run manually or via cron job
 */
require_once 'db.php';

function calculateAttendancePercentage($student_id, $conn) {
    // Get total classes (attendance records) for the month
    $currentMonth = date('Y-m-01');
    $today = date('Y-m-d');
    
    $stmt = $conn->prepare('
        SELECT COUNT(DISTINCT attendance_date) as present_days 
        FROM attendance 
        WHERE student_id = ? AND attendance_date >= ? AND attendance_date <= ?
    ');
    $stmt->bind_param('iss', $student_id, $currentMonth, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $presentDays = $result->fetch_assoc()['present_days'];
    $stmt->close();
    
    // Get total business days in month
    $currentYear = date('Y');
    $currentMonthNum = date('m');
    $totalDays = cal_days_in_month(CAL_GREGORIAN, $currentMonthNum, $currentYear);
    
    // Count only weekdays up to today
    $businessDays = 0;
    for ($day = 1; $day <= $totalDays; $day++) {
        $date = date('Y-m-d', mktime(0, 0, 0, $currentMonthNum, $day, $currentYear));
        if ($date <= $today) {
            $dayOfWeek = date('N', strtotime($date)); // 1=Monday, 6=Saturday, 7=Sunday
            if ($dayOfWeek < 6) { // Monday to Friday
                $businessDays++;
            }
        }
    }
    
    $percentage = $businessDays > 0 ? ($presentDays / $businessDays) * 100 : 0;
    
    return [
        'present_days' => $presentDays,
        'business_days' => $businessDays,
        'percentage' => round($percentage, 2)
    ];
}

function generateLowAttendanceAlerts($conn) {
    $threshold = 75; // 75% threshold
    
    // Get all students
    $stmt = $conn->prepare('SELECT id, name, mobile FROM students ORDER BY id');
    $stmt->execute();
    $result = $stmt->get_result();
    
    $alertsGenerated = 0;
    
    while ($student = $result->fetch_assoc()) {
        $attendance = calculateAttendancePercentage($student['id'], $conn);
        
        if ($attendance['percentage'] < $threshold && $attendance['business_days'] > 0) {
            // Check if alert already exists for this student today
            $checkStmt = $conn->prepare('
                SELECT id FROM attendance_alerts 
                WHERE student_id = ? AND alert_type = "low_attendance" 
                AND DATE(created_at) = CURDATE()
            ');
            $checkStmt->bind_param('i', $student['id']);
            $checkStmt->execute();
            $existingAlert = $checkStmt->get_result();
            
            if ($existingAlert->num_rows === 0) {
                // Create new alert
                $message = "Your attendance is " . $attendance['percentage'] . "% (Present: " . 
                          $attendance['present_days'] . "/" . $attendance['business_days'] . " days). " .
                          "Maintain at least " . $threshold . "% to pass.";
                
                $insertStmt = $conn->prepare('
                    INSERT INTO attendance_alerts (student_id, alert_type, attendance_percentage, message, status)
                    VALUES (?, "low_attendance", ?, ?, "pending")
                ');
                $insertStmt->bind_param('ids', $student['id'], $attendance['percentage'], $message);
                $insertStmt->execute();
                $alertsGenerated++;
                $insertStmt->close();
            }
            $checkStmt->close();
        }
    }
    
    $stmt->close();
    return $alertsGenerated;
}

// Run alert generation if called directly
if (isset($_GET['action']) && $_GET['action'] === 'check') {
    $generated = generateLowAttendanceAlerts($conn);
    echo json_encode(['success' => true, 'alerts_generated' => $generated]);
    $conn->close(); // Only close when called directly
} else {
    // Can be called from cron: php check_attendance_alerts.php?action=check
    echo "Attendance alert checker loaded. Call with ?action=check to generate alerts.";
}
?>
