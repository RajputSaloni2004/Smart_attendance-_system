<?php
session_start();
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: admin_login.php');
    exit;
}
require_once 'db.php';

$date = $_GET['date'] ?? date('Y-m-d');
$date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : date('Y-m-d');
$search = trim($_GET['search'] ?? '');
$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');

$results = [];
$stmt = $conn->prepare('SELECT a.attendance_time, s.name, s.department, s.year, s.mobile FROM attendance a JOIN students s ON a.student_id = s.id WHERE a.attendance_date = ? ORDER BY a.attendance_time DESC');
$stmt->bind_param('s', $date);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $results[] = $row;
}
$stmt->close();
$total = count($results);

$students = [];
$searchTerm = '%' . $search . '%';
if (ctype_digit($search)) {
    $searchId = (int)$search;
    $stmt = $conn->prepare('SELECT id, name, roll_no, department, year FROM students WHERE (name LIKE ? OR roll_no LIKE ? OR id = ?) ORDER BY name LIMIT 200');
    $stmt->bind_param('ssi', $searchTerm, $searchTerm, $searchId);
} else {
    $stmt = $conn->prepare('SELECT id, name, roll_no, department, year FROM students WHERE name LIKE ? OR roll_no LIKE ? ORDER BY name LIMIT 200');
    $stmt->bind_param('ss', $searchTerm, $searchTerm);
}
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $students[] = $row;
}
$stmt->close();

$searchError = '';
if ($search !== '' && count($students) === 0) {
    $searchError = 'The student does not exist.';
}

if (!$student_id && count($students) === 1) {
    $student_id = (int)$students[0]['id'];
}

$selectedStudent = null;
$monthlyDays = [];
$presentCount = 0;
$absentCount = 0;
$reportMonth = preg_match('/^\d{2}$/', $month) ? (int)$month : (int)date('m');
$reportYear = preg_match('/^\d{4}$/', $year) ? (int)$year : (int)date('Y');
if ($reportMonth < 1 || $reportMonth > 12) {
    $reportMonth = (int)date('m');
}
if ($reportYear < 2000 || $reportYear > (int)date('Y') + 1) {
    $reportYear = (int)date('Y');
}
$month = sprintf('%02d', $reportMonth);
$year = (string)$reportYear;

if ($student_id > 0) {
    $stmt = $conn->prepare('SELECT id, name, roll_no, department, year, mobile FROM students WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $selectedStudent = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($selectedStudent) {
        $startDate = sprintf('%04d-%02d-01', $reportYear, $reportMonth);
        $endDate = date('Y-m-t', strtotime($startDate));
        $stmt = $conn->prepare('SELECT attendance_date FROM attendance WHERE student_id = ? AND attendance_date BETWEEN ? AND ?');
        $stmt->bind_param('iss', $student_id, $startDate, $endDate);
        $stmt->execute();
        $res = $stmt->get_result();
        $presentDates = [];
        while ($row = $res->fetch_assoc()) {
            $presentDates[$row['attendance_date']] = true;
        }
        $stmt->close();

        $today = date('Y-m-d');
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $reportMonth, $reportYear);
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $currentDate = sprintf('%04d-%02d-%02d', $reportYear, $reportMonth, $day);
            $dayLabel = date('l', strtotime($currentDate));
            if ($currentDate > $today) {
                $monthlyDays[] = [
                    'date' => $currentDate,
                    'day' => $dayLabel,
                    'status' => '',
                    'status_class' => ''
                ];
                continue;
            }

            $present = isset($presentDates[$currentDate]);
            if ($present) {
                $presentCount++;
            } else {
                $absentCount++;
            }
            $monthlyDays[] = [
                'date' => $currentDate,
                'day' => $dayLabel,
                'status' => $present ? 'Present' : 'Absent',
                'status_class' => $present ? 'status-present' : 'status-absent'
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Attendance Records | Smart Attendance</title>
    <link rel="stylesheet" href="assets/css/style.css" />
</head>
<body>
    <header class="app-header">
        <div class="brand">Attendance Records</div>
        <div class="top-actions">
            <a class="button secondary" href="admin_dashboard.php">Dashboard</a>
            <a class="button tertiary" href="logout.php">Logout</a>
        </div>
    </header>
    <main class="page-shell">
        <?php if ($searchError): ?>
            <div class="alert error" role="alert" id="searchErrorToast"><?= htmlspecialchars($searchError) ?></div>
            <script>
                const toast = document.getElementById('searchErrorToast');
                if (toast) {
                    setTimeout(() => {
                        toast.style.transition = 'opacity 0.4s ease';
                        toast.style.opacity = '0';
                        setTimeout(() => toast.remove(), 400);
                    }, 5000);
                }
            </script>
        <?php endif; ?>
        <section class="report-panel">
            <div class="report-header">
                <div>
                    <h1>Attendance on <?= htmlspecialchars($date) ?></h1>
                    <p class="subtitle">Total marked: <strong><?= $total ?></strong></p>
                </div>
                <form method="get" class="date-filter">
                    <label for="date">Choose date</label>
                    <input type="date" name="date" id="date" value="<?= htmlspecialchars($date) ?>" />
                    <button class="button secondary" type="submit">Load</button>
                </form>
            </div>
            <?php if ($total === 0): ?>
                <div class="empty-state">No attendance has been recorded for this date yet.</div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Department</th>
                                <th>Year</th>
                                <th>Mobile</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['name']) ?></td>
                                    <td><?= htmlspecialchars($row['department']) ?></td>
                                    <td><?= htmlspecialchars($row['year']) ?></td>
                                    <td><?= htmlspecialchars($row['mobile']) ?></td>
                                    <td><?= htmlspecialchars($row['attendance_time']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="report-panel" id="monthlyReport">
            <div class="report-header">
                <div>
                    <h1>Monthly Attendance</h1>
                    <p class="subtitle">Search students, select month and year, then review presence by day.</p>
                </div>
                <form method="get" class="date-filter">
                    <label for="search">Student name or roll no.</label>
                    <input type="search" name="search" id="search" placeholder="Search a student" value="<?= htmlspecialchars($search) ?>" />
                    <label for="student_id">Select student</label>
                    <select name="student_id" id="student_id">
                        <option value="">-- Select student --</option>
                        <?php foreach ($students as $student): ?>
                            <option value="<?= $student['id'] ?>" <?= $student_id === (int)$student['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($student['name'] . ' (' . $student['roll_no'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label for="month">Month</label>
                    <select name="month" id="month">
                        <?php foreach (range(1, 12) as $m): ?>
                            <option value="<?= sprintf('%02d', $m) ?>" <?= $month === sprintf('%02d', $m) ? 'selected' : '' ?>>
                                <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label for="year">Year</label>
                    <select name="year" id="year">
                        <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                            <option value="<?= $y ?>" <?= $year === (string)$y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                    <button class="button secondary" type="submit">Load monthly report</button>
                </form>
            </div>

            <?php if (!$selectedStudent): ?>
                <div class="empty-state">Search or select a student to view monthly attendance.</div>
            <?php else: ?>
                <div class="report-header" style="justify-content: space-between; gap: 18px;">
                    <div>
                        <h2><?= htmlspecialchars($selectedStudent['name']) ?> <span style="font-size:0.95rem;color:var(--text-muted);">(<?= htmlspecialchars($selectedStudent['roll_no']) ?>)</span></h2>
                        <p class="subtitle">Showing attendance for <?= htmlspecialchars(date('F Y', strtotime("{$year}-{$month}-01"))) ?>.</p>
                        <p class="subtitle">Present: <strong><?= $presentCount ?></strong> · Absent: <strong><?= $absentCount ?></strong></p>
                    </div>
                    <button type="button" class="button primary" id="downloadCsvBtn" onclick="downloadMonthlyCSV()">Download CSV</button>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Day</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($monthlyDays as $day): ?>
                                <tr>
                                    <td><?= htmlspecialchars($day['date']) ?></td>
                                    <td><?= htmlspecialchars($day['day']) ?></td>
                                    <td class="<?= htmlspecialchars($day['status_class']) ?>"><?= htmlspecialchars($day['status']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <footer class="footer-bar">
        <p>&copy; 2026 <strong>Smart Attendance System</strong>. All rights reserved.</p>
    </footer>

    <script>
        function downloadMonthlyCSV() {
            const table = document.querySelector('#monthlyReport table');
            if (!table) {
                alert('No attendance data to download.');
                return;
            }

            let csv = 'Date,Day,Status\n';
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length >= 3) {
                    const date = cells[0].textContent.trim();
                    const day = cells[1].textContent.trim();
                    const status = cells[2].textContent.trim() || '';
                    csv += `"${date}","${day}","${status}"\n`;
                }
            });

            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', 'monthly-attendance-<?= htmlspecialchars($year . '-' . $month) ?>.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</body>
</html>
