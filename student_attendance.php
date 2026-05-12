<?php
require_once 'db.php';
$studentData = [];
$result = $conn->query('SELECT id, name, department, year, mobile, face_descriptor FROM students');
while ($row = $result->fetch_assoc()) {
    $studentData[] = [
        'id' => (int)$row['id'],
        'name' => $row['name'],
        'department' => $row['department'],
        'year' => $row['year'],
        'mobile' => $row['mobile'],
        'descriptor' => json_decode($row['face_descriptor'], true),
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Student Attendance | Smart Attendance</title>
    <link rel="stylesheet" href="assets/css/style.css" />
    <script src="assets/js/face-api.min.js"></script>
</head>
<body>
    <header class="app-header">
        <div class="brand">Student Attendance</div>
        <div class="top-actions">
            <a class="button secondary" href="index.php">Home</a>
        </div>
    </header>
    <main class="page-shell">
        <section class="attendance-panel">
            <div class="hero-card">
                <h1>Mark Attendance with Face Recognition</h1>
                <p class="subtitle">Allow the system to identify you and record your attendance instantly.</p>
                <span class="status" id="statusMessage">Press start to activate the camera.</span>
            </div>
            <div class="face-panel">
                <div class="camera-card">
                    <p id="modelStatus">Loading face recognition models...</p>
                    <video id="attendanceVideo" autoplay muted playsinline width="640" height="480"></video>
                    <div class="video-actions">
                        <button class="button primary" id="startRecognitionBtn" disabled>Start Scan</button>
                        <button class="button secondary" id="stopRecognitionBtn" type="button">Stop</button>
                    </div>
                </div>
                <div class="details-card">
                    <div class="record-card">
                        <h2>Recognition Result</h2>
                        <div id="recognitionResult" class="info-block">
                            <p>No face scanned yet.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <script>
        const knownStudents = <?= json_encode($studentData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    </script>
    <script src="assets/js/face-recognition.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', async () => {
            const modelStatus = document.getElementById('modelStatus');
            const startBtn = document.getElementById('startRecognitionBtn');
            try {
                await initializeFaceRecognition();
                modelStatus.textContent = 'Models loaded. Ready to scan.';
                startBtn.disabled = false;
            } catch (err) {
                modelStatus.textContent = 'Failed to load models. Please refresh.';
            }
            const video = document.getElementById('attendanceVideo');
            const stopBtn = document.getElementById('stopRecognitionBtn');
            const status = document.getElementById('statusMessage');
            const resultBox = document.getElementById('recognitionResult');
            let scanning = false;

            startBtn.addEventListener('click', async () => {
                scanning = true;
                status.textContent = 'Camera active. Looking for your face...';
                await startVideo(video);
                scanLoop();
            });
            stopBtn.addEventListener('click', () => {
                scanning = false;
                stopVideo(video);
                status.textContent = 'Scan stopped. Press start to scan again.';
            });

            async function scanLoop() {
                while (scanning) {
                    const result = await captureFaceDescriptor('attendanceVideo');
                    if (result && result.descriptor) {
                        const descriptor = result.descriptor;
                        const isCentered = result.isCentered;
                        const match = findBestMatch(descriptor, knownStudents);
                        if (match && isCentered) {
                            scanning = false;
                            stopVideo(video);
                            status.textContent = 'Face recognized. Recording attendance...';
                            resultBox.innerHTML = `<p><strong>${match.name}</strong><br>${match.department} • ${match.year}<br>${match.mobile}</p>`;
                            const response = await fetch('mark_attendance.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ student_id: match.id })
                            });
                            const text = await response.text();
                            let result;
                            try {
                                result = JSON.parse(text);
                            } catch (jsonError) {
                                console.error('Invalid JSON response from mark_attendance.php:', text);
                                result = { success: false, message: 'Server error: invalid response. Check console.' };
                            }
                            resultBox.innerHTML += `<div class="alert ${result.success ? 'success' : 'error'}">${result.message}</div>`;
                            status.classList.toggle('success', result.success);
                            status.textContent = result.success ? '✓ Attendance recorded successfully!' : '✗ Attendance recording failed.';
                            return;
                        } else {
                            resultBox.innerHTML = '<p>No matching student found. Please register first or try again.</p>';
                        }
                    }
                    await new Promise(r => setTimeout(r, 1800));
                }
            }
        });
    </script>

    <footer class="footer-bar">
        <p>&copy; 2026 <strong>Smart Attendance System</strong>. All rights reserved.</p>
    </footer>
</body>
</html>
