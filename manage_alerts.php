<?php
session_start();
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: admin_login.php');
    exit;
}
require_once 'db.php';
require_once 'twilio_config.php';

$message = '';
$error = '';

// Detect whether the students table has an email column.
$emailColumnExists = false;
$columnCheck = $conn->query("SHOW COLUMNS FROM students LIKE 'email'");
if ($columnCheck && $columnCheck->num_rows > 0) {
    $emailColumnExists = true;
}
$studentEmailSelect = $emailColumnExists ? 's.email,' : 'NULL AS email,';

$action = $_POST['action'] ?? '';

// Generate new alerts
if ($action === 'generate_alerts') {
    require_once 'check_attendance_alerts.php';
    $alertsGenerated = generateLowAttendanceAlerts($conn);
    $message = $alertsGenerated . " new low attendance alerts generated.";
}

// Send SMS notification
if ($action === 'send_sms') {
    $alert_id = (int)$_POST['alert_id'];

    $stmt = $conn->prepare(
        "SELECT a.id, a.message, s.mobile, s.name " .
        "FROM attendance_alerts a " .
        "JOIN students s ON a.student_id = s.id " .
        "WHERE a.id = ?"
    );
    $stmt->bind_param('i', $alert_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $alert = $result->fetch_assoc();
    $stmt->close();

    if ($alert && !empty($alert['mobile'])) {
        // Format phone number (add country code if missing)
        $phone = $alert['mobile'];
        if (!preg_match('/^\+/', $phone)) {
            $phone = '+' . DEFAULT_COUNTRY_CODE . $phone;
        }

        $sms_content = "Dear " . htmlspecialchars($alert['name']) . ". " . $alert['message'];

        // TEST MODE or PRODUCTION
        if (TWILIO_TEST_MODE) {
            // TEST MODE: Log SMS instead of sending
            $log_entry = date('Y-m-d H:i:s') . " | TO: $phone | FROM: " . TWILIO_PHONE_NUMBER . " | MSG: " . $sms_content . PHP_EOL;
            file_put_contents('sms_log.txt', $log_entry, FILE_APPEND);

            // Mark as sent in database
            $updateStmt = $conn->prepare('UPDATE attendance_alerts SET status = "sent", sms_sent = TRUE WHERE id = ?');
            $updateStmt->bind_param('i', $alert_id);
            $updateStmt->execute();
            $updateStmt->close();

            $message = "✓ SMS LOGGED (TEST MODE) to " . htmlspecialchars($alert['name']) . " (" . htmlspecialchars($phone) . "). Check sms_log.txt for details.";
        } else {
            // PRODUCTION: Twilio SMS Integration
            $url = "https://api.twilio.com/2010-04-01/Accounts/" . TWILIO_ACCOUNT_SID . "/Messages.json";

            $data = [
                'From' => TWILIO_PHONE_NUMBER,
                'To' => $phone,
                'Body' => $sms_content
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_USERPWD, TWILIO_ACCOUNT_SID . ':' . TWILIO_AUTH_TOKEN);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For development only

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code == 201) {
                // SMS sent successfully
                $updateStmt = $conn->prepare('UPDATE attendance_alerts SET status = "sent", sms_sent = TRUE WHERE id = ?');
                $updateStmt->bind_param('i', $alert_id);
                $updateStmt->execute();
                $updateStmt->close();

                $message = "SMS sent successfully to " . htmlspecialchars($alert['name']) . " (" . htmlspecialchars($phone) . ")";
            } else {
                // SMS failed
                $error_response = json_decode($response, true);
                $error = "SMS failed: " . ($error_response['message'] ?? 'Unknown error') . " (HTTP $http_code)";

                // Log the error for debugging
                error_log("Twilio SMS Error: " . $response);
            }
        }
    } else {
        $error = "Alert not found or student has no mobile number.";
    }
}

// Mark alert as read
if ($action === 'mark_read') {
    $alert_id = (int)$_POST['alert_id'];
    $stmt = $conn->prepare('UPDATE attendance_alerts SET status = "read" WHERE id = ?');
    $stmt->bind_param('i', $alert_id);
    $stmt->execute();
    $stmt->close();
    $message = "Alert marked as read.";
}

// Delete alert
if ($action === 'delete') {
    $alert_id = (int)$_POST['alert_id'];
    $stmt = $conn->prepare('DELETE FROM attendance_alerts WHERE id = ?');
    $stmt->bind_param('i', $alert_id);
    $stmt->execute();
    $stmt->close();
    $message = "Alert deleted.";
}

// Get all alerts
$alerts = [];
$stmt = $conn->prepare(
    "SELECT a.id, a.student_id, a.alert_type, a.attendance_percentage, " .
    "a.message, a.status, a.sms_sent, a.created_at, " .
    "s.name, s.mobile, s.roll_no, s.department " .
    "FROM attendance_alerts a " .
    "JOIN students s ON a.student_id = s.id " .
    "ORDER BY a.created_at DESC " .
    "LIMIT 100"
);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $alerts[] = $row;
}
$stmt->close();

// Get statistics
$stats = [];
$countStmt = $conn->prepare('
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = "sent" THEN 1 ELSE 0 END) as sent,
        SUM(CASE WHEN status = "read" THEN 1 ELSE 0 END) as read_status
    FROM attendance_alerts 
    WHERE DATE(created_at) = CURDATE()
');
$countStmt->execute();
$statsResult = $countStmt->get_result();
$stats = $statsResult->fetch_assoc();
$countStmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Manage Alerts | Smart Attendance</title>
    <link rel="stylesheet" href="assets/css/style.css" />
    <style>
        .alert-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            margin-right: 8px;
        }
        .badge-pending { background-color: #fbbf24; color: #78350f; }
        .badge-sent { background-color: #60a5fa; color: #1e3a8a; }
        .badge-read { background-color: #34d399; color: #065f46; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            padding: 20px;
            border-radius: 12px;
            color: white;
            text-align: center;
        }
        .stat-number { font-size: 2rem; font-weight: bold; }
        .stat-label { font-size: 0.9rem; opacity: 0.9; margin-top: 8px; }
        
        .action-buttons { display: flex; gap: 8px; }
        .action-buttons button { padding: 6px 12px; font-size: 0.85rem; }
    </style>
</head>
<body>
    <main class="container">
        <header class="page-header">
            <h1>Attendance Alerts Management</h1>
            <p>Monitor and manage low attendance notifications</p>
        </header>

        <?php if ($message): ?>
            <div class="alert success" style="margin-bottom: 20px;"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert error" style="margin-bottom: 20px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $stats['total'] ?? 0 ?></div>
                <div class="stat-label">Total Alerts (Today)</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['pending'] ?? 0 ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['sent'] ?? 0 ?></div>
                <div class="stat-label">Sent</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['read'] ?? 0 ?></div>
                <div class="stat-label">Read</div>
            </div>
        </div>

        <!-- Generate Alerts Button -->
        <div style="margin-bottom: 24px;">
            <form method="post" style="display: inline;">
                <input type="hidden" name="action" value="generate_alerts" />
                <button class="button primary" type="submit">Generate New Alerts</button>
            </form>
            <p style="font-size: 0.9rem; color: var(--text-muted); margin-top: 8px;">
                Click to scan all students and generate alerts for attendance below 75%
            </p>
        </div>

        <!-- Alerts Table -->
        <section class="data-section">
            <h2>Recent Alerts</h2>
            <?php if (count($alerts) === 0): ?>
                <p class="empty-state">No alerts found.</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Student</th>
                                <th>Roll No</th>
                                <th>Attendance %</th>
                                <th>Status</th>
                                <th>Message</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($alerts as $alert): ?>
                                <tr>
                                    <td><?= date('M d, Y', strtotime($alert['created_at'])) ?></td>
                                    <td><strong><?= htmlspecialchars($alert['name']) ?></strong></td>
                                    <td><?= htmlspecialchars($alert['roll_no']) ?></td>
                                    <td><?= $alert['attendance_percentage'] ?>%</td>
                                    <td>
                                        <span class="alert-badge badge-<?= strtolower($alert['status']) ?>">
                                            <?= ucfirst($alert['status']) ?>
                                        </span>
                                        <?php if ($alert['sms_sent']): ?>
                                            <span style="font-size: 0.8rem; color: var(--text-muted);">✓ SMS Sent</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size: 0.9rem; max-width: 200px; white-space: normal;">
                                        <?= htmlspecialchars(substr($alert['message'], 0, 80)) ?>...
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if ($alert['status'] !== 'sent'): ?>
                                                <form method="post" style="display: inline;">
                                                    <input type="hidden" name="action" value="send_sms" />
                                                    <input type="hidden" name="alert_id" value="<?= $alert['id'] ?>" />
                                                    <button class="button small" type="submit" 
                                                            <?php if (!$alert['mobile']): ?>disabled<?php endif; ?>>
                                                        Send SMS
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="action" value="mark_read" />
                                                <input type="hidden" name="alert_id" value="<?= $alert['id'] ?>" />
                                                <button class="button small secondary" type="submit">Mark Read</button>
                                            </form>
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="action" value="delete" />
                                                <input type="hidden" name="alert_id" value="<?= $alert['id'] ?>" />
                                                <button class="button small danger" type="submit" 
                                                        onclick="return confirm('Delete this alert?')">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <div style="margin-top: 24px; padding: 16px; background-color: var(--card-bg); border-radius: 8px; border-left: 4px solid var(--primary);">
            <strong>📱 Twilio SMS Integration:</strong>
            <p style="margin-top: 8px; font-size: 0.9rem;">
                <strong>Setup Required:</strong> Configure your Twilio credentials in the code above.<br>
                <strong>Phone Format:</strong> Numbers are automatically formatted with +91 prefix (India).<br>
                <strong>Status:</strong> Ready to send real SMS messages to student mobile numbers.
            </p>
        </div>

        <div style="margin-top: 24px;">
            <a href="admin_dashboard.php" class="button secondary">← Back to Dashboard</a>
        </div>
    </main>

    <footer class="footer-bar">
        <p>&copy; 2026 <strong>Smart Attendance System</strong>. All rights reserved.</p>
    </footer>
</body>
</html>
