<?php
require_once '../config.php';
redirectIfNotLoggedIn();
redirectIfNotStudent();

$error = '';
$success = '';

try {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("SELECT DISTINCT i.id, i.name 
                          FROM institutes i 
                          JOIN student_teacher_relationships str ON i.id = str.institute_id 
                          WHERE str.student_id = ? AND str.is_active = 1 AND i.is_active = 1 
                          ORDER BY i.name");
    $stmt->execute([$_SESSION['user_id']]);
    $institutes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $subjects = [];
    $selected_institute_id = '';
    
    if (isset($_GET['institute_id']) || isset($_POST['institute_id'])) {
        $selected_institute_id = $_GET['institute_id'] ?? $_POST['institute_id'] ?? '';
        if ($selected_institute_id) {
            $stmt = $pdo->prepare("SELECT id, name FROM subjects WHERE institute_id = ? AND is_active = 1 ORDER BY name");
            $stmt->execute([$selected_institute_id]);
            $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
} catch(PDOException $e) {
    $error = "Failed to load data: " . $e->getMessage();
    $institutes = [];
    $subjects = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attendance_data'])) {
    $institute_id = $_POST['institute_id'];
    $subject_id = $_POST['subject_id'];
    $attendance_data = $_POST['attendance_data'];
    $notes = trim($_POST['notes'] ?? '');
    
    if (empty($institute_id) || empty($subject_id)) {
        $error = 'Please select both institute and subject.';
    } elseif (empty($attendance_data)) {
        $error = 'No selfie image data received.';
    } else {
        try {
            // Verify student is enrolled in this institute and subject
            $stmt = $pdo->prepare("SELECT str.id 
                                  FROM student_teacher_relationships str
                                  JOIN subjects s ON str.institute_id = s.institute_id
                                  WHERE str.student_id = ? AND str.institute_id = ? AND s.id = ? AND str.is_active = 1");
            $stmt->execute([$_SESSION['user_id'], $institute_id, $subject_id]);
            
            if ($stmt->rowCount() === 0) {
                $error = 'You are not enrolled in this institute or subject.';
            } else {
                $today = date('Y-m-d');
                $stmt = $pdo->prepare("SELECT id FROM attendance_records 
                                      WHERE user_id = ? AND institute_id = ? AND subject_id = ? AND attendance_date = ?");
                $stmt->execute([$_SESSION['user_id'], $institute_id, $subject_id, $today]);
                
                if ($stmt->rowCount() > 0) {
                    $error = 'Attendance already marked for today for this subject.';
                } else {
                    $image_data = $attendance_data;
                    
                    if (strpos($image_data, 'data:image') === 0) {
                        $image_data = preg_replace('#^data:image/\w+;base64,#i', '', $image_data);
                    }
                    
                    $decoded_image_data = base64_decode($image_data);
                    
                    if ($decoded_image_data === false) {
                        $error = 'Invalid image data.';
                    } else {
                        $filename = 'selfie_' . $_SESSION['user_id'] . '_' . date('Y-m-d_H-i-s') . '_' . uniqid() . '.jpg';
                        $upload_dir = '../uploads/selfies/';
                        $file_path = $upload_dir . $filename;
                        
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
                        if (file_put_contents($file_path, $decoded_image_data)) {
                            $stmt = $pdo->prepare("INSERT INTO attendance_records 
                                                  (user_id, institute_id, subject_id, attendance_date, selfie_image_path, notes, status) 
                                                  VALUES (?, ?, ?, ?, ?, ?, 'present')");
                            $stmt->execute([
                                $_SESSION['user_id'],
                                $institute_id,
                                $subject_id,
                                $today,
                                'uploads/selfies/' . $filename,
                                $notes
                            ]);
                            
                            $attendance_id = $pdo->lastInsertId();
                            
                            createAutomaticWarning($attendance_id);
                            
                            $success = 'Attendance marked successfully! Selfie saved and record created.';
                            
                            // Clear form
                            $selected_institute_id = '';
                            $subjects = [];
                            
                        } else {
                            $error = 'Failed to save selfie image. Please check file permissions.';
                        }
                    }
                }
            }
        } catch(PDOException $e) {
            $error = 'Failed to mark attendance: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mark Attendance - Student Attendance</title>
    <style>
    :root {
        --primary: #10b981;
        --primary-dark: #059669;
        --secondary: #06b6d4;
        --accent: #8b5cf6;
        --success: #22c55e;
        --warning: #f59e0b;
        --error: #ef4444;
        --text-primary: #f8fafc;
        --text-secondary: #cbd5e1;
        --text-muted: #94a3b8;
        --bg-primary: #0f172a;
        --bg-secondary: #1e293b;
        --bg-tertiary: #334155;
        --bg-gradient: linear-gradient(135deg, #10b981 0%, #06b6d4 100%);
        --bg-gradient-dark: linear-gradient(135deg, #059669 0%, #0891b2 100%);
        --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.3);
        --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.4), 0 2px 4px -1px rgba(0, 0, 0, 0.3);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.5), 0 4px 6px -2px rgba(0, 0, 0, 0.4);
        --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.6), 0 10px 10px -5px rgba(0, 0, 0, 0.5);
        --border-radius: 12px;
        --border-radius-lg: 16px;
        --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        --glass-bg: rgba(30, 41, 59, 0.8);
        --glass-border: rgba(255, 255, 255, 0.1);
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: var(--bg-primary);
        color: var(--text-primary);
        line-height: 1.6;
        -webkit-font-smoothing: antialiased;
        min-height: 100vh;
    }

    /* Modern Header with Dark Glass Morphism */
    .header {
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border-bottom: 1px solid var(--glass-border);
        padding: 1rem 2rem;
        box-shadow: var(--shadow-lg);
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        position: sticky;
        top: 0;
        z-index: 1000;
    }

    .header h1 {
        color: var(--text-primary);
        font-size: 1.5rem;
        font-weight: 700;
    }

    .nav-links {
        display: flex;
        gap: 0.5rem;
        list-style: none;
    }

    .nav-links a {
        color: var(--text-secondary);
        text-decoration: none;
        padding: 0.75rem 1.25rem;
        border-radius: var(--border-radius);
        transition: var(--transition);
        font-weight: 500;
        border: 1px solid transparent;
    }

    .nav-links a:hover {
        color: var(--primary);
        background: rgba(16, 185, 129, 0.1);
        border-color: var(--glass-border);
        transform: translateY(-1px);
    }

    .nav-links a.active {
        background: var(--bg-gradient);
        color: white;
        box-shadow: var(--shadow-md);
        border-color: transparent;
    }

    /* Modern Button Styles */
    .logout-btn {
        background: linear-gradient(135deg, var(--error) 0%, #dc2626 100%);
        color: white;
        padding: 0.75rem 1.5rem;
        text-decoration: none;
        border-radius: var(--border-radius);
        font-weight: 600;
        transition: var(--transition);
        box-shadow: var(--shadow-md);
        border: none;
        cursor: pointer;
    }

    .logout-btn:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
        background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
    }

    .container {
        max-width: 1000px;
        margin: 0 auto;
        padding: 0 2rem;
    }

    /* Card Styles */
    .card {
        background: var(--bg-secondary);
        padding: 2.5rem;
        border-radius: var(--border-radius-lg);
        box-shadow: var(--shadow-xl);
        margin-bottom: 2rem;
        position: relative;
        overflow: hidden;
        border: 1px solid var(--glass-border);
    }

    .card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--bg-gradient);
    }

    .page-title {
        color: var(--text-primary);
        margin-bottom: 0.5rem;
        font-size: 2rem;
        font-weight: 700;
    }

    .page-subtitle {
        color: var(--text-secondary);
        margin-bottom: 2rem;
        font-size: 1.1rem;
    }

    /* Form Styles */
    .form-group {
        margin-bottom: 1.5rem;
    }

    label {
        display: block;
        margin-bottom: 0.5rem;
        color: var(--text-primary);
        font-weight: 600;
    }

    select, textarea {
        width: 100%;
        padding: 0.75rem;
        background: var(--bg-tertiary);
        border: 2px solid var(--glass-border);
        border-radius: var(--border-radius);
        font-size: 1rem;
        transition: var(--transition);
        font-family: inherit;
        color: var(--text-primary);
    }

    select:focus, textarea:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
    }

    textarea {
        resize: vertical;
        min-height: 80px;
    }

    select option {
        background: var(--bg-secondary);
        color: var(--text-primary);
        padding: 0.5rem;
    }

    /* Button Styles */
    .btn {
        padding: 0.75rem 2rem;
        border: none;
        border-radius: var(--border-radius);
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
        text-decoration: none;
        display: inline-block;
        text-align: center;
    }

    .btn-primary {
        background: var(--bg-gradient);
        color: white;
        box-shadow: var(--shadow-md);
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }

    .btn-success {
        background: var(--bg-gradient);
        color: white;
        box-shadow: var(--shadow-md);
    }

    .btn-success:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }

    .btn-secondary {
        background: var(--bg-tertiary);
        color: var(--text-primary);
        border: 1px solid var(--glass-border);
    }

    .btn-secondary:hover {
        background: var(--bg-secondary);
        transform: translateY(-2px);
    }

    .btn-danger {
        background: linear-gradient(135deg, var(--error) 0%, #dc2626 100%);
        color: white;
        box-shadow: var(--shadow-md);
    }

    .btn-danger:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }

    .form-actions {
        display: flex;
        gap: 1rem;
        margin-top: 2rem;
    }

    /* Alert Styles */
    .error {
        background: rgba(239, 68, 68, 0.1);
        color: var(--error);
        padding: 1rem;
        border-radius: var(--border-radius);
        margin-bottom: 1.5rem;
        border-left: 4px solid var(--error);
        border: 1px solid rgba(239, 68, 68, 0.2);
    }

    .success {
        background: rgba(34, 197, 94, 0.1);
        color: var(--success);
        padding: 1rem;
        border-radius: var(--border-radius);
        margin-bottom: 1.5rem;
        border-left: 4px solid var(--success);
        border: 1px solid rgba(34, 197, 94, 0.2);
    }

    /* Camera Section */
    .camera-section {
        text-align: center;
        margin: 2rem 0;
        padding: 2rem;
        background: var(--bg-tertiary);
        border-radius: var(--border-radius);
        border: 1px solid var(--glass-border);
    }

    .camera-container {
        position: relative;
        max-width: 500px;
        margin: 0 auto;
    }

    #video {
        width: 100%;
        max-width: 500px;
        border-radius: var(--border-radius);
        background: #000;
        border: 2px solid var(--glass-border);
    }

    #canvas {
        display: none;
    }

    .captured-image {
        max-width: 100%;
        border-radius: var(--border-radius);
        border: 3px solid var(--primary);
        box-shadow: var(--shadow-lg);
    }

    .camera-controls {
        display: flex;
        gap: 1rem;
        justify-content: center;
        margin-top: 1rem;
        flex-wrap: wrap;
    }

    .upload-section {
        margin-top: 2rem;
        padding-top: 2rem;
        border-top: 1px solid var(--glass-border);
    }

    .preview-container {
        margin: 1rem 0;
        text-align: center;
    }

    .hidden {
        display: none;
    }

    /* Loading animation */
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .card {
        animation: fadeInUp 0.6s ease-out;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .header {
            padding: 1rem;
            flex-direction: column;
            gap: 1rem;
        }

        .nav-links {
            flex-wrap: wrap;
            justify-content: center;
        }

        .container {
            padding: 0 1rem;
        }

        .card {
            padding: 1.5rem;
        }

        .form-actions, .camera-controls {
            flex-direction: column;
        }

        .btn {
            width: 100%;
        }

        .camera-section {
            padding: 1.5rem;
        }
    }

    @media (max-width: 480px) {
        .nav-links {
            flex-direction: column;
            width: 100%;
        }

        .nav-links a {
            text-align: center;
        }

        .header h1 {
            font-size: 1.25rem;
        }

        .page-title {
            font-size: 1.5rem;
        }
    }
</style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h1>🎓 Student Portal</h1>
        <ul class="nav-links">
            <li><a href="../dashboard/student-dashboard.php">Dashboard</a></li>
            <li><a href="mark-attendance.php">Mark Attendance</a></li>
            <li><a href="attendance-history.php">My Attendance</a></li>
            <li><a href="../auth/logout.php" class="logout-btn">Logout (<?php echo htmlspecialchars($_SESSION['user_name']); ?>)</a></li>
        </ul>
    </div>
    
    <div class="container">
        <div class="card">
            <h2 class="page-title">Mark Attendance</h2>
            <p class="page-subtitle">Take a selfie to mark your attendance for today</p>
            
            <?php if ($error): ?>
                <div class="error">
                    <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success">
                    <strong>Success!</strong> <?php echo $success; ?>
                    <div style="margin-top: 0.5rem;">
                        <a href="attendance-history.php" class="btn btn-primary">View Attendance History</a>
                        <a href="mark-attendance.php" class="btn btn-secondary">Mark Another Attendance</a>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (empty($institutes)): ?>
                <div class="error">
                    <strong>No Enrolled Institutes</strong><br>
                    You are not enrolled in any institutes yet. Please contact your teacher.
                </div>
            <?php else: ?>
                <form method="POST" action="" id="attendanceForm">
                    <div class="form-group">
                        <label for="institute_id">Institute <span style="color: #c33;">*</span></label>
                        <select id="institute_id" name="institute_id" required onchange="loadSubjects()">
                            <option value="">Select an Institute</option>
                            <?php foreach ($institutes as $institute): ?>
                                <option value="<?php echo $institute['id']; ?>" 
                                    <?php echo ($selected_institute_id == $institute['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($institute['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="subject_id">Subject <span style="color: #c33;">*</span></label>
                        <select id="subject_id" name="subject_id" required>
                            <option value="">Select a Subject</option>
                            <?php foreach ($subjects as $subject): ?>
                                <option value="<?php echo $subject['id']; ?>">
                                    <?php echo htmlspecialchars($subject['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Notes <span style="color: #666; font-weight: normal;">(Optional)</span></label>
                        <textarea id="notes" name="notes" placeholder="Any additional notes about today's class..."><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
                    </div>
                    
                    <!-- Camera Section -->
                    <div class="camera-section">
                        <h3>Take Selfie</h3>
                        
                        <div class="camera-container">
                            <video id="video" autoplay playsinline></video>
                            <canvas id="canvas"></canvas>
                            <div id="imagePreview" class="preview-container hidden">
                                <img id="capturedImage" class="captured-image" alt="Captured selfie">
                            </div>
                        </div>
                        
                        <div class="camera-controls">
                            <button type="button" id="startCamera" class="btn btn-primary">Start Camera</button>
                            <button type="button" id="captureBtn" class="btn btn-success hidden">Capture Selfie</button>
                            <button type="button" id="retakeBtn" class="btn btn-secondary hidden">Retake Photo</button>
                            <button type="button" id="uploadBtn" class="btn btn-primary">Upload Image Instead</button>
                        </div>
                        
                        <input type="file" id="fileInput" accept="image/*" class="hidden">
                        
                        <input type="hidden" id="attendance_data" name="attendance_data">
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" id="submitBtn" class="btn btn-primary" disabled>Mark Attendance</button>
                        <a href="../dashboard/student-dashboard.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // DOM elements
        const video = document.getElementById('video');
        const canvas = document.getElementById('canvas');
        const capturedImage = document.getElementById('capturedImage');
        const startCameraBtn = document.getElementById('startCamera');
        const captureBtn = document.getElementById('captureBtn');
        const retakeBtn = document.getElementById('retakeBtn');
        const uploadBtn = document.getElementById('uploadBtn');
        const fileInput = document.getElementById('fileInput');
        const imagePreview = document.getElementById('imagePreview');
        const attendanceData = document.getElementById('attendance_data');
        const submitBtn = document.getElementById('submitBtn');
        const instituteSelect = document.getElementById('institute_id');
        const subjectSelect = document.getElementById('subject_id');
        
        let stream = null;
        
        // Start camera
        startCameraBtn.addEventListener('click', async function() {
            try {
                stream = await navigator.mediaDevices.getUserMedia({ 
                    video: { 
                        facingMode: 'user', 
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    }, 
                    audio: false 
                });
                
                video.srcObject = stream;
                startCameraBtn.classList.add('hidden');
                captureBtn.classList.remove('hidden');
                uploadBtn.classList.add('hidden');
                
            } catch (err) {
                alert('Error accessing camera: ' + err.message);
                console.error('Camera error:', err);
            }
        });
        
        // Capture photo
        captureBtn.addEventListener('click', function() {
            const context = canvas.getContext('2d');
            
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            
            context.drawImage(video, 0, 0, canvas.width, canvas.height);
            
            const imageData = canvas.toDataURL('image/jpeg', 0.8);
            
            capturedImage.src = imageData;
            imagePreview.classList.remove('hidden');
            video.classList.add('hidden');
            
            attendanceData.value = imageData;
            
            captureBtn.classList.add('hidden');
            retakeBtn.classList.remove('hidden');
            uploadBtn.classList.add('hidden');
            submitBtn.disabled = false;
            
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
            }
        });
        
        // Retake photo
        retakeBtn.addEventListener('click', function() {
            imagePreview.classList.add('hidden');
            video.classList.remove('hidden');
            retakeBtn.classList.add('hidden');
            startCameraBtn.classList.remove('hidden');
            uploadBtn.classList.remove('hidden');
            attendanceData.value = '';
            submitBtn.disabled = true;
            
            startCameraBtn.click();
        });
        
        uploadBtn.addEventListener('click', function() {
            fileInput.click();
        });
        
        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const imageData = e.target.result;
                    
                    capturedImage.src = imageData;
                    imagePreview.classList.remove('hidden');
                    video.classList.add('hidden');
                    
                    attendanceData.value = imageData;
                    
                    startCameraBtn.classList.add('hidden');
                    captureBtn.classList.add('hidden');
                    retakeBtn.classList.remove('hidden');
                    uploadBtn.classList.add('hidden');
                    submitBtn.disabled = false;
                };
                
                reader.readAsDataURL(file);
            }
        });
        
        function loadSubjects() {
            const instituteId = instituteSelect.value;
            if (instituteId) {
                window.location.href = `mark-attendance.php?institute_id=${instituteId}`;
            }
        }
        
        document.getElementById('attendanceForm').addEventListener('submit', function(e) {
            const instituteId = instituteSelect.value;
            const subjectId = subjectSelect.value;
            const imageData = attendanceData.value;
            
            if (!instituteId || !subjectId) {
                e.preventDefault();
                alert('Please select both institute and subject.');
                return false;
            }
            
            if (!imageData) {
                e.preventDefault();
                alert('Please capture or upload a selfie image.');
                return false;
            }
            
            submitBtn.disabled = true;
            submitBtn.textContent = 'Processing...';
        });
        
        <?php if ($selected_institute_id): ?>
            document.addEventListener('DOMContentLoaded', function() {
                instituteSelect.value = '<?php echo $selected_institute_id; ?>';
            });
        <?php endif; ?>
    </script>
</body>
</html>