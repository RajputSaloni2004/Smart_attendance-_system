<?php
session_start();
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: admin_login.php');
    exit;
}
require_once 'db.php';

$message = '';
$error = '';
$preview_data = [];
$import_stats = ['success' => 0, 'errors' => 0, 'duplicates' => 0];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['preview'])) {
        // Handle CSV preview
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['csv_file']['tmp_name'];
            $handle = fopen($file, 'r');

            if ($handle !== false) {
                $header = fgetcsv($handle); // Skip header row
                $row_count = 0;

                while (($data = fgetcsv($handle)) !== false && $row_count < 10) { // Preview first 10 rows
                    if (count($data) >= 6) { // Ensure minimum required columns
                        $preview_data[] = [
                            'name' => trim($data[0] ?? ''),
                            'dob' => trim($data[1] ?? ''),
                            'department' => trim($data[2] ?? ''),
                            'roll_no' => trim($data[3] ?? ''),
                            'year' => trim($data[4] ?? ''),
                            'mobile' => trim($data[5] ?? ''),
                            'row_number' => $row_count + 2 // +2 because we skip header and start from 1
                        ];
                    }
                    $row_count++;
                }
                fclose($handle);
                $message = "CSV preview loaded. Showing first " . count($preview_data) . " rows.";
            } else {
                $error = "Failed to read CSV file.";
            }
        } else {
            $error = "Please select a valid CSV file.";
        }
    } elseif (isset($_POST['import'])) {
        // Handle actual import
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['csv_file']['tmp_name'];
            $handle = fopen($file, 'r');

            if ($handle !== false) {
                $header = fgetcsv($handle); // Skip header row
                $row_number = 2; // Start from row 2 (after header)

                while (($data = fgetcsv($handle)) !== false) {
                    if (count($data) < 6) {
                        $import_stats['errors']++;
                        $row_number++;
                        continue;
                    }

                    $name = trim($data[0] ?? '');
                    $dob = trim($data[1] ?? '');
                    $department = trim($data[2] ?? '');
                    $roll_no = trim($data[3] ?? '');
                    $year = trim($data[4] ?? '');
                    $mobile = trim($data[5] ?? '');

                    // Validate required fields
                    if (empty($name) || empty($dob) || empty($department) || empty($roll_no) || empty($year) || empty($mobile)) {
                        $import_stats['errors']++;
                        $row_number++;
                        continue;
                    }

                    // Check for duplicate roll_no
                    $check_stmt = $conn->prepare('SELECT id FROM students WHERE roll_no = ?');
                    $check_stmt->bind_param('s', $roll_no);
                    $check_stmt->execute();
                    $existing = $check_stmt->get_result();

                    if ($existing->num_rows > 0) {
                        $import_stats['duplicates']++;
                        $check_stmt->close();
                        $row_number++;
                        continue;
                    }
                    $check_stmt->close();

                    // Insert student
                    $insert_stmt = $conn->prepare('
                        INSERT INTO students (name, dob, department, roll_no, year, mobile, face_descriptor)
                        VALUES (?, ?, ?, ?, ?, ?, "")
                    ');
                    $insert_stmt->bind_param('ssssss', $name, $dob, $department, $roll_no, $year, $mobile);

                    if ($insert_stmt->execute()) {
                        $import_stats['success']++;
                    } else {
                        $import_stats['errors']++;
                    }
                    $insert_stmt->close();

                    $row_number++;
                }
                fclose($handle);

                $message = "Import completed! Success: {$import_stats['success']}, Errors: {$import_stats['errors']}, Duplicates: {$import_stats['duplicates']}";
            } else {
                $error = "Failed to read CSV file.";
            }
        } else {
            $error = "Please select a valid CSV file.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Bulk Student Import | Smart Attendance</title>
    <link rel="stylesheet" href="assets/css/style.css" />
    <style>
        .import-section {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid var(--border);
        }
        .file-upload {
            border: 2px dashed var(--border);
            border-radius: 8px;
            padding: 40px;
            text-align: center;
            margin: 20px 0;
            transition: border-color 0.3s;
        }
        .file-upload:hover {
            border-color: var(--primary);
        }
        .file-upload input[type="file"] {
            display: none;
        }
        .file-upload label {
            cursor: pointer;
            color: var(--primary);
            font-weight: 500;
        }
        .preview-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .preview-table th,
        .preview-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        .preview-table th {
            background: var(--primary);
            color: white;
            font-weight: 600;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin-top: 20px;
        }
        .stat-box {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            padding: 16px;
            border-radius: 8px;
            color: white;
            text-align: center;
        }
        .stat-box.error {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        }
        .stat-box.warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }
        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 4px;
        }
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        .csv-format {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px;
            margin: 20px 0;
        }
        .csv-format h3 {
            margin-top: 0;
            color: var(--text-primary);
        }
        .csv-format code {
            background: white;
            padding: 12px;
            border-radius: 4px;
            display: block;
            margin: 8px 0;
            border: 1px solid #d1d5db;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <main class="container">
        <header class="page-header">
            <h1>Bulk Student Import</h1>
            <p>Import multiple students from CSV file</p>
        </header>

        <?php if ($message): ?>
            <div class="alert success" style="margin-bottom: 20px;"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert error" style="margin-bottom: 20px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- CSV Format Instructions -->
        <div class="csv-format">
            <h3>📄 CSV File Format</h3>
            <p>Your CSV file should have the following columns in this exact order:</p>
            <code>Name,Date of Birth,Department,Roll No,Year,Mobile</code>
            <p><strong>Example:</strong></p>
            <code>John Doe,1995-05-15,Computer Science,CS001,3rd Year,9876543210</code>
            <code>Jane Smith,1996-08-20,Electrical,ELE045,2nd Year,9876543211</code>
            <p style="color: #dc2626; margin-top: 12px;">
                <strong>⚠️ Important:</strong> Roll numbers must be unique. Face registration will need to be done separately after import.
            </p>
        </div>

        <!-- Import Form -->
        <div class="import-section">
            <h2>Upload CSV File</h2>
            <form method="post" enctype="multipart/form-data">
                <div class="file-upload">
                    <input type="file" name="csv_file" id="csv_file" accept=".csv" required />
                    <label for="csv_file">
                        📁 Click to select CSV file<br>
                        <small>Maximum file size: 10MB</small>
                    </label>
                </div>

                <div style="display: flex; gap: 12px; justify-content: center;">
                    <button class="button secondary" type="submit" name="preview">Preview CSV</button>
                    <button class="button primary" type="submit" name="import">Import Students</button>
                </div>
            </form>
        </div>

        <!-- Import Statistics -->
        <?php if (isset($_POST['import']) && ($import_stats['success'] > 0 || $import_stats['errors'] > 0 || $import_stats['duplicates'] > 0)): ?>
            <div class="import-section">
                <h2>Import Results</h2>
                <div class="stats-grid">
                    <div class="stat-box">
                        <div class="stat-number"><?= $import_stats['success'] ?></div>
                        <div class="stat-label">Successfully Imported</div>
                    </div>
                    <div class="stat-box error">
                        <div class="stat-number"><?= $import_stats['errors'] ?></div>
                        <div class="stat-label">Errors</div>
                    </div>
                    <div class="stat-box warning">
                        <div class="stat-number"><?= $import_stats['duplicates'] ?></div>
                        <div class="stat-label">Duplicates Skipped</div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- CSV Preview -->
        <?php if (!empty($preview_data)): ?>
            <div class="import-section">
                <h2>CSV Preview (First 10 Rows)</h2>
                <div style="overflow-x: auto;">
                    <table class="preview-table">
                        <thead>
                            <tr>
                                <th>Row</th>
                                <th>Name</th>
                                <th>Date of Birth</th>
                                <th>Department</th>
                                <th>Roll No</th>
                                <th>Year</th>
                                <th>Mobile</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($preview_data as $row): ?>
                                <tr>
                                    <td><?= $row['row_number'] ?></td>
                                    <td><?= htmlspecialchars($row['name']) ?></td>
                                    <td><?= htmlspecialchars($row['dob']) ?></td>
                                    <td><?= htmlspecialchars($row['department']) ?></td>
                                    <td><?= htmlspecialchars($row['roll_no']) ?></td>
                                    <td><?= htmlspecialchars($row['year']) ?></td>
                                    <td><?= htmlspecialchars($row['mobile']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p style="margin-top: 16px; color: var(--text-muted);">
                    <strong>Note:</strong> This is a preview only. Click "Import Students" to actually import the data.
                </p>
            </div>
        <?php endif; ?>

        <div style="margin-top: 30px;">
            <a href="admin_dashboard.php" class="button secondary">← Back to Dashboard</a>
            <a href="register_student.php" class="button primary" style="margin-left: 12px;">Add Single Student</a>
        </div>
    </main>

    <footer class="footer-bar">
        <p>&copy; 2026 <strong>Smart Attendance System</strong>. All rights reserved.</p>
    </footer>

    <script>
        // File upload preview
        document.getElementById('csv_file').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const label = e.target.nextElementSibling;
                label.innerHTML = '📁 ' + file.name + '<br><small>' + (file.size / 1024 / 1024).toFixed(2) + ' MB</small>';
            }
        });
    </script>
</body>
</html>
