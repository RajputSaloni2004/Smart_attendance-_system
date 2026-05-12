<?php
session_start();
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: admin_login.php');
    exit;
}
require_once 'db.php';

// Get overall statistics
$stats = [];

// Total students
$stmt = $conn->prepare('SELECT COUNT(*) as total FROM students');
$stmt->execute();
$stats['total_students'] = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Today's attendance
$today = date('Y-m-d');
$stmt = $conn->prepare('SELECT COUNT(DISTINCT student_id) as today_attendance FROM attendance WHERE attendance_date = ?');
$stmt->bind_param('s', $today);
$stmt->execute();
$stats['today_attendance'] = $stmt->get_result()->fetch_assoc()['today_attendance'];
$stmt->close();

// This month's attendance
$current_month = date('Y-m-01');
$stmt = $conn->prepare('SELECT COUNT(DISTINCT student_id) as month_attendance FROM attendance WHERE attendance_date >= ?');
$stmt->bind_param('s', $current_month);
$stmt->execute();
$stats['month_attendance'] = $stmt->get_result()->fetch_assoc()['month_attendance'];
$stmt->close();

// Department-wise statistics
$dept_stats = [];
$stmt = $conn->prepare('
    SELECT department, COUNT(*) as count 
    FROM students 
    GROUP BY department 
    ORDER BY count DESC
');
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $dept_stats[] = $row;
}
$stmt->close();

// Year-wise statistics
$year_stats = [];
$stmt = $conn->prepare('
    SELECT year, COUNT(*) as count 
    FROM students 
    GROUP BY year 
    ORDER BY year
');
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $year_stats[] = $row;
}
$stmt->close();

// Daily attendance trend (last 30 days)
$daily_trend = [];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $stmt = $conn->prepare('SELECT COUNT(DISTINCT student_id) as count FROM attendance WHERE attendance_date = ?');
    $stmt->bind_param('s', $date);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();

    $daily_trend[] = [
        'date' => date('M d', strtotime($date)),
        'count' => $count
    ];
}

// Top performers (highest attendance this month)
$top_performers = [];
$stmt = $conn->prepare('
    SELECT s.name, s.roll_no, s.department, COUNT(a.id) as attendance_count
    FROM students s
    LEFT JOIN attendance a ON s.id = a.student_id AND a.attendance_date >= ?
    GROUP BY s.id
    ORDER BY attendance_count DESC
    LIMIT 10
');
$stmt->bind_param('s', $current_month);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $top_performers[] = $row;
}
$stmt->close();

// Low performers (lowest attendance this month)
$low_performers = [];
$stmt = $conn->prepare('
    SELECT s.name, s.roll_no, s.department, COUNT(a.id) as attendance_count
    FROM students s
    LEFT JOIN attendance a ON s.id = a.student_id AND a.attendance_date >= ?
    GROUP BY s.id
    ORDER BY attendance_count ASC
    LIMIT 10
');
$stmt->bind_param('s', $current_month);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $low_performers[] = $row;
}
$stmt->close();

// Attendance alerts summary
$alert_stats = [];
$stmt = $conn->prepare('
    SELECT 
        COUNT(*) as total_alerts,
        SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = "sent" THEN 1 ELSE 0 END) as sent,
        SUM(CASE WHEN alert_type = "low_attendance" THEN 1 ELSE 0 END) as low_attendance
    FROM attendance_alerts
');
$stmt->execute();
$alert_stats = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Analytics Dashboard | Smart Attendance</title>
    <link rel="stylesheet" href="assets/css/style.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stats-overview {
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
        .chart-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        .chart-container {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            border: 1px solid var(--border);
        }
        .performers-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        .performer-list {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            border: 1px solid var(--border);
        }
        .performer-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }
        .performer-item:last-child {
            border-bottom: none;
        }
        .performer-name {
            font-weight: 500;
        }
        .performer-details {
            font-size: 0.9rem;
            color: var(--text-muted);
        }
        .performer-count {
            font-weight: bold;
            color: var(--primary);
        }
        .alerts-summary {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            border: 1px solid var(--border);
        }
        .alerts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
        }
        .alert-stat {
            text-align: center;
            padding: 16px;
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border-radius: 8px;
            border: 1px solid #f59e0b;
        }
        .alert-stat.sent {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            border-color: #3b82f6;
        }
        .alert-number {
            font-size: 1.8rem;
            font-weight: bold;
            color: #92400e;
        }
        .alert-stat.sent .alert-number {
            color: #1e40af;
        }
        .alert-label {
            font-size: 0.9rem;
            color: #92400e;
            margin-top: 4px;
        }
        .alert-stat.sent .alert-label {
            color: #1e40af;
        }
    </style>
</head>
<body>
    <main class="container">
        <header class="page-header">
            <h1>Analytics Dashboard</h1>
            <p>Comprehensive attendance statistics and insights</p>
        </header>

        <!-- Overview Statistics -->
        <div class="stats-overview">
            <div class="stat-card">
                <div class="stat-number"><?= $stats['total_students'] ?></div>
                <div class="stat-label">Total Students</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['today_attendance'] ?></div>
                <div class="stat-label">Today's Attendance</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['month_attendance'] ?></div>
                <div class="stat-label">This Month's Attendance</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= round(($stats['month_attendance'] / $stats['total_students']) * 100, 1) ?>%</div>
                <div class="stat-label">Monthly Attendance Rate</div>
            </div>
        </div>

        <!-- Charts -->
        <div class="chart-grid">
            <!-- Department Distribution -->
            <div class="chart-container">
                <h2>Students by Department</h2>
                <canvas id="deptChart" width="300" height="200"></canvas>
            </div>

            <!-- Daily Attendance Trend -->
            <div class="chart-container">
                <h2>Daily Attendance Trend (30 Days)</h2>
                <canvas id="dailyChart" width="300" height="200"></canvas>
            </div>
        </div>

        <!-- Top and Low Performers -->
        <div class="performers-section">
            <div class="performer-list">
                <h2>🏆 Top Performers (This Month)</h2>
                <?php if (count($top_performers) === 0): ?>
                    <p class="empty-state">No attendance data available.</p>
                <?php else: ?>
                    <?php foreach ($top_performers as $performer): ?>
                        <div class="performer-item">
                            <div>
                                <div class="performer-name"><?= htmlspecialchars($performer['name']) ?></div>
                                <div class="performer-details">
                                    <?= htmlspecialchars($performer['roll_no']) ?> • 
                                    <?= htmlspecialchars($performer['department']) ?>
                                </div>
                            </div>
                            <div class="performer-count"><?= $performer['attendance_count'] ?> days</div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="performer-list">
                <h2>⚠️ Low Performers (This Month)</h2>
                <?php if (count($low_performers) === 0): ?>
                    <p class="empty-state">No attendance data available.</p>
                <?php else: ?>
                    <?php foreach ($low_performers as $performer): ?>
                        <div class="performer-item">
                            <div>
                                <div class="performer-name"><?= htmlspecialchars($performer['name']) ?></div>
                                <div class="performer-details">
                                    <?= htmlspecialchars($performer['roll_no']) ?> • 
                                    <?= htmlspecialchars($performer['department']) ?>
                                </div>
                            </div>
                            <div class="performer-count"><?= $performer['attendance_count'] ?> days</div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Alerts Summary -->
        <div class="alerts-summary">
            <h2>📢 Attendance Alerts Summary</h2>
            <div class="alerts-grid">
                <div class="alert-stat">
                    <div class="alert-number"><?= $alert_stats['total_alerts'] ?? 0 ?></div>
                    <div class="alert-label">Total Alerts</div>
                </div>
                <div class="alert-stat">
                    <div class="alert-number"><?= $alert_stats['pending'] ?? 0 ?></div>
                    <div class="alert-label">Pending</div>
                </div>
                <div class="alert-stat sent">
                    <div class="alert-number"><?= $alert_stats['sent'] ?? 0 ?></div>
                    <div class="alert-label">Sent</div>
                </div>
                <div class="alert-stat">
                    <div class="alert-number"><?= $alert_stats['low_attendance'] ?? 0 ?></div>
                    <div class="alert-label">Low Attendance</div>
                </div>
            </div>
        </div>

        <div style="margin-top: 30px;">
            <a href="admin_dashboard.php" class="button secondary">← Back to Dashboard</a>
            <a href="manage_alerts.php" class="button primary" style="margin-left: 12px;">Manage Alerts</a>
        </div>
    </main>

    <footer class="footer-bar">
        <p>&copy; 2026 <strong>Smart Attendance System</strong>. All rights reserved.</p>
    </footer>

    <script>
        // Department Chart
        const deptCtx = document.getElementById('deptChart').getContext('2d');
        const deptData = <?= json_encode($dept_stats) ?>;

        new Chart(deptCtx, {
            type: 'doughnut',
            data: {
                labels: deptData.map(item => item.department),
                datasets: [{
                    data: deptData.map(item => item.count),
                    backgroundColor: [
                        '#6366f1', '#8b5cf6', '#06b6d4', '#10b981', '#f59e0b',
                        '#ef4444', '#ec4899', '#84cc16', '#f97316', '#64748b'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    }
                }
            }
        });

        // Daily Trend Chart
        const dailyCtx = document.getElementById('dailyChart').getContext('2d');
        const dailyData = <?= json_encode($daily_trend) ?>;

        new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: dailyData.map(item => item.date),
                datasets: [{
                    label: 'Students Present',
                    data: dailyData.map(item => item.count),
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
