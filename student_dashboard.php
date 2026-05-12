<?php
session_start();

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    header('Location: student_login.php');
    exit;
}

require_once 'db.php';

// Get student info
$student_id = $_SESSION['student_id'];
$stmt = $conn->prepare('SELECT name, roll_no, department, year, mobile FROM students WHERE id = ?');
$stmt->bind_param('i', $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

// Get attendance statistics
$current_month = date('Y-m-01');
$today = date('Y-m-d');

// Total attendance this month
$stmt = $conn->prepare('
    SELECT COUNT(*) as present_days 
    FROM attendance 
    WHERE student_id = ? AND attendance_date >= ? AND attendance_date <= ?
');
$stmt->bind_param('iss', $student_id, $current_month, $today);
$stmt->execute();
$result = $stmt->get_result();
$present_days = $result->fetch_assoc()['present_days'];
$stmt->close();

// Calculate business days this month
$business_days = 0;
$start_date = strtotime($current_month);
$end_date = strtotime($today);
for ($date = $start_date; $date <= $end_date; $date = strtotime('+1 day', $date)) {
    $day_of_week = date('N', $date); // 1=Monday, 7=Sunday
    if ($day_of_week < 6) { // Monday to Friday
        $business_days++;
    }
}

$attendance_percentage = $business_days > 0 ? round(($present_days / $business_days) * 100, 1) : 0;

// Get recent attendance records
$stmt = $conn->prepare('
    SELECT attendance_date, attendance_time 
    FROM attendance 
    WHERE student_id = ? 
    ORDER BY attendance_date DESC, attendance_time DESC 
    LIMIT 30
');
$stmt->bind_param('i', $student_id);
$stmt->execute();
$result = $stmt->get_result();
$recent_attendance = [];
while ($row = $result->fetch_assoc()) {
    $recent_attendance[] = $row;
}
$stmt->close();

// Get monthly attendance data for chart
$monthly_data = [];
for ($i = 5; $i >= 0; $i--) {
    $month_start = date('Y-m-01', strtotime("-$i months"));
    $month_end = date('Y-m-t', strtotime("-$i months"));
    $month_name = date('M Y', strtotime("-$i months"));
    
    $stmt = $conn->prepare('
        SELECT COUNT(*) as days 
        FROM attendance 
        WHERE student_id = ? AND attendance_date BETWEEN ? AND ?
    ');
    $stmt->bind_param('iss', $student_id, $month_start, $month_end);
    $stmt->execute();
    $result = $stmt->get_result();
    $days = $result->fetch_assoc()['days'];
    $stmt->close();
    
    $monthly_data[] = [
        'month' => $month_name,
        'days' => $days
    ];
}

// Get alerts for this student
$stmt = $conn->prepare('
    SELECT message, status, created_at 
    FROM attendance_alerts 
    WHERE student_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
');
$stmt->bind_param('i', $student_id);
$stmt->execute();
$result = $stmt->get_result();
$alerts = [];
while ($row = $result->fetch_assoc()) {
    $alerts[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>My Attendance | Smart Attendance System</title>
    <link rel="stylesheet" href="assets/css/style.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            padding: 24px;
            border-radius: 12px;
            color: white;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 8px;
        }
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        .chart-container {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            border: 1px solid var(--border);
        }
        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .attendance-table th,
        .attendance-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        .attendance-table th {
            background-color: var(--primary);
            color: white;
            font-weight: 600;
        }
        .alert-item {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 12px;
        }
        .alert-item.unread {
            background: #dbeafe;
            border-color: #3b82f6;
        }
        .alert-date {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-bottom: 4px;
        }
        .student-info {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            border: 1px solid var(--border);
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
        }
        .info-item {
            text-align: center;
        }
        .info-label {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-bottom: 4px;
        }
        .info-value {
            font-weight: 600;
            color: var(--text-primary);
        }
    </style>
</head>
<body>
    <header class="app-header">
        <div class="brand">My Attendance Portal</div>
        <div class="top-actions">
            <span>Welcome, <?= htmlspecialchars($student['name']) ?></span>
            <a class="button tertiary" href="student_logout.php">Logout</a>
        </div>
    </header>

    <main class="container">
        <!-- Student Info -->
        <div class="student-info">
            <h2>My Information</h2>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Name</div>
                    <div class="info-value"><?= htmlspecialchars($student['name']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Roll No</div>
                    <div class="info-value"><?= htmlspecialchars($student['roll_no']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Department</div>
                    <div class="info-value"><?= htmlspecialchars($student['department']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Year</div>
                    <div class="info-value"><?= htmlspecialchars($student['year']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Mobile</div>
                    <div class="info-value"><?= htmlspecialchars($student['mobile']) ?></div>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $present_days ?></div>
                <div class="stat-label">Present Days (This Month)</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $business_days ?></div>
                <div class="stat-label">Total Business Days</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $attendance_percentage ?>%</div>
                <div class="stat-label">Attendance Percentage</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: <?= $attendance_percentage >= 75 ? '#10b981' : '#f59e0b' ?>">
                    <?= $attendance_percentage >= 75 ? 'Good' : 'Low' ?>
                </div>
                <div class="stat-label">Status</div>
            </div>
        </div>

        <!-- Monthly Trend Chart -->
        <div class="chart-container">
            <h2>Monthly Attendance Trend</h2>
            <canvas id="attendanceChart" width="400" height="200"></canvas>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
            <!-- Recent Attendance -->
            <section class="data-section">
                <h2>Recent Attendance</h2>
                <?php if (count($recent_attendance) === 0): ?>
                    <p class="empty-state">No attendance records found.</p>
                <?php else: ?>
                    <table class="attendance-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Day</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_attendance as $record): ?>
                                <tr>
                                    <td><?= date('M d, Y', strtotime($record['attendance_date'])) ?></td>
                                    <td><?= date('H:i', strtotime($record['attendance_time'])) ?></td>
                                    <td><?= date('l', strtotime($record['attendance_date'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>

            <!-- Alerts & Notifications -->
            <section class="data-section">
                <h2>Alerts & Notifications</h2>
                <?php if (count($alerts) === 0): ?>
                    <p class="empty-state">No alerts at this time.</p>
                <?php else: ?>
                    <?php foreach ($alerts as $alert): ?>
                        <div class="alert-item <?= $alert['status'] === 'pending' ? 'unread' : '' ?>">
                            <div class="alert-date">
                                <?= date('M d, Y H:i', strtotime($alert['created_at'])) ?>
                                <?php if ($alert['status'] === 'pending'): ?>
                                    <span style="color: #3b82f6; font-weight: 500;">• Unread</span>
                                <?php endif; ?>
                            </div>
                            <div><?= htmlspecialchars($alert['message']) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        </div>

        <div style="margin-top: 30px; text-align: center;">
            <a href="student_attendance.php" class="button primary">Mark Today's Attendance</a>
        </div>
    </main>

    <footer class="footer-bar">
        <p>&copy; 2026 <strong>Smart Attendance System</strong>. All rights reserved.</p>
    </footer>

    <script>
        // Monthly attendance chart
        const ctx = document.getElementById('attendanceChart').getContext('2d');
        const monthlyData = <?= json_encode($monthly_data) ?>;

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: monthlyData.map(item => item.month),
                datasets: [{
                    label: 'Present Days',
                    data: monthlyData.map(item => item.days),
                    borderColor: 'var(--primary)',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
