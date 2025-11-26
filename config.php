<?php
// config.php - Complete version with all functions
session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'attendance_system');
define('DB_USER', 'root');
define('DB_PASS', '');

function getDBConnection() {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch(PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isTeacher() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'teacher';
}

function isStudent() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'student';
}

function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function redirectIfNotLoggedIn() {
    if (!isLoggedIn()) {
        header("Location: auth/login.php");
        exit();
    }
}

function redirectIfNotTeacher() {
    redirectIfNotLoggedIn();
    if (!isTeacher()) {
        header("Location: ../dashboard/student-dashboard.php");
        exit();
    }
}

function redirectIfNotStudent() {
    redirectIfNotLoggedIn();
    if (!isStudent()) {
        header("Location: ../dashboard/teacher-dashboard.php");
        exit();
    }
}

function redirectIfLoggedIn() {
    if (isLoggedIn()) {
        header("Location: dashboard/dashboard.php");
        exit();
    }
}

function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $script = $_SERVER['SCRIPT_NAME'];
    $path = dirname($script);
    
    if ($path !== '/') {
        return $protocol . $host . $path . '/';
    }
    return $protocol . $host . '/';
}

function validateImageUpload($file_data) {
    if (strpos($file_data, 'data:image') !== 0) {
        return false;
    }
    
    $base64 = preg_replace('#^data:image/\w+;base64,#i', '', $file_data);
    $file_size = strlen(base64_decode($base64));
    
    if ($file_size > 2 * 1024 * 1024) {
        return false;
    }
    
    return true;
}

// Attendance Calculation Engine
function calculateAttendancePercentage($user_id, $institute_id, $subject_id, $start_date = null, $end_date = null) {
    try {
        $pdo = getDBConnection();
        
        // Set default date range (last 30 days if not specified)
        if (!$start_date) {
            $start_date = date('Y-m-d', strtotime('-30 days'));
        }
        if (!$end_date) {
            $end_date = date('Y-m-d');
        }
        
        // Count total possible classes (based on date range for now)
        $days_stmt = $pdo->prepare("
            SELECT DATEDIFF(?, ?) as total_days
        ");
        $days_stmt->execute([$end_date, $start_date]);
        $total_days = $days_stmt->fetchColumn();
        $total_days = max(1, $total_days); // Ensure at least 1 day
        
        // Count attended classes
        $attended_stmt = $pdo->prepare("
            SELECT COUNT(*) as attended_count
            FROM attendance_records ar
            WHERE ar.user_id = ? 
            AND ar.institute_id = ? 
            AND ar.subject_id = ?
            AND ar.attendance_date BETWEEN ? AND ?
            AND ar.status = 'present'
        ");
        $attended_stmt->execute([$user_id, $institute_id, $subject_id, $start_date, $end_date]);
        $attended_count = $attended_stmt->fetchColumn();
        
        // Calculate percentage
        if ($total_days > 0) {
            $percentage = ($attended_count / $total_days) * 100;
            return [
                'attended' => $attended_count,
                'total' => $total_days,
                'percentage' => round($percentage, 2),
                'status' => 'calculated'
            ];
        }
        
        return [
            'attended' => 0,
            'total' => 0,
            'percentage' => 0,
            'status' => 'no_classes'
        ];
        
    } catch(PDOException $e) {
        error_log("Attendance calculation error: " . $e->getMessage());
        return [
            'attended' => 0,
            'total' => 0,
            'percentage' => 0,
            'status' => 'error'
        ];
    }
}

// Calculate overall institute attendance
function calculateInstituteAttendance($user_id, $institute_id, $start_date = null, $end_date = null) {
    try {
        $pdo = getDBConnection();
        
        if (!$start_date) $start_date = date('Y-m-d', strtotime('-30 days'));
        if (!$end_date) $end_date = date('Y-m-d');
        
        // Get all subjects for this institute
        $subjects_stmt = $pdo->prepare("
            SELECT id FROM subjects 
            WHERE institute_id = ? AND is_active = 1
        ");
        $subjects_stmt->execute([$institute_id]);
        $subjects = $subjects_stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $total_attended = 0;
        $total_possible = 0;
        
        foreach ($subjects as $subject_id) {
            $attendance = calculateAttendancePercentage($user_id, $institute_id, $subject_id, $start_date, $end_date);
            $total_attended += $attendance['attended'];
            $total_possible += $attendance['total'];
        }
        
        if ($total_possible > 0) {
            $percentage = ($total_attended / $total_possible) * 100;
            return [
                'attended' => $total_attended,
                'total' => $total_possible,
                'percentage' => round($percentage, 2),
                'status' => 'calculated'
            ];
        }
        
        return [
            'attended' => 0,
            'total' => 0,
            'percentage' => 0,
            'status' => 'no_classes'
        ];
        
    } catch(PDOException $e) {
        error_log("Institute attendance calculation error: " . $e->getMessage());
        return [
            'attended' => 0,
            'total' => 0,
            'percentage' => 0,
            'status' => 'error'
        ];
    }
}

// Check attendance warning system
function checkAttendanceWarning($user_id, $institute_id, $subject_id = null) {
    try {
        $pdo = getDBConnection();
        
        // Get attendance goal
        $goal_stmt = $pdo->prepare("
            SELECT target_percentage, warning_threshold 
            FROM attendance_goals 
            WHERE user_id = ? AND institute_id = ? AND is_active = 1
        ");
        $goal_stmt->execute([$user_id, $institute_id]);
        $goal = $goal_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$goal) {
            // Use default thresholds
            $goal = [
                'target_percentage' => 75.00,
                'warning_threshold' => 70.00
            ];
        }
        
        if ($subject_id) {
            // Check specific subject
            $attendance = calculateAttendancePercentage($user_id, $institute_id, $subject_id);
        } else {
            // Check overall institute
            $attendance = calculateInstituteAttendance($user_id, $institute_id);
        }
        
        $warnings = [];
        
        if ($attendance['status'] === 'calculated') {
            $percentage = $attendance['percentage'];
            
            if ($percentage < $goal['warning_threshold']) {
                $warnings[] = [
                    'type' => 'critical',
                    'message' => "Attendance is critically low! ({$percentage}%)",
                    'percentage' => $percentage,
                    'threshold' => $goal['warning_threshold']
                ];
            } elseif ($percentage < $goal['target_percentage']) {
                $warnings[] = [
                    'type' => 'warning', 
                    'message' => "Attendance is below target ({$percentage}%)",
                    'percentage' => $percentage,
                    'threshold' => $goal['target_percentage']
                ];
            }
        }
        
        return [
            'attendance' => $attendance,
            'goal' => $goal,
            'warnings' => $warnings,
            'has_warnings' => !empty($warnings)
        ];
        
    } catch(PDOException $e) {
        error_log("Warning check error: " . $e->getMessage());
        return [
            'attendance' => ['percentage' => 0, 'status' => 'error'],
            'goal' => ['target_percentage' => 75, 'warning_threshold' => 70],
            'warnings' => [],
            'has_warnings' => false
        ];
    }
}

// Create automatic warning when attendance is recorded
function createAutomaticWarning($attendance_record_id) {
    try {
        $pdo = getDBConnection();
        
        // Get attendance record details
        $record_stmt = $pdo->prepare("
            SELECT ar.user_id, ar.institute_id, ar.subject_id, ar.attendance_date
            FROM attendance_records ar
            WHERE ar.id = ?
        ");
        $record_stmt->execute([$attendance_record_id]);
        $record = $record_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$record) return false;
        
        // Check for warnings after this attendance
        $warning_check = checkAttendanceWarning(
            $record['user_id'], 
            $record['institute_id'], 
            $record['subject_id']
        );
        
        if ($warning_check['has_warnings']) {
            // Create notification in warnings table
            foreach ($warning_check['warnings'] as $warning) {
                // Insert into warnings table
                $notification_stmt = $pdo->prepare("
                    INSERT INTO attendance_warnings 
                    (user_id, institute_id, subject_id, warning_type, message, attendance_percentage, threshold_percentage, is_read, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW())
                ");
                
                $notification_stmt->execute([
                    $record['user_id'],
                    $record['institute_id'],
                    $record['subject_id'],
                    $warning['type'],
                    $warning['message'],
                    $warning['percentage'],
                    $warning['threshold']
                ]);
            }
            
            return true;
        }
        
        return false;
        
    } catch(PDOException $e) {
        error_log("Automatic warning creation error: " . $e->getMessage());
        return false;
    }
}

function getUserWarnings($user_id, $unread_only = true) {
    try {
        $pdo = getDBConnection();
        
        $query = "
            SELECT aw.*, i.name as institute_name, s.name as subject_name
            FROM attendance_warnings aw
            LEFT JOIN institutes i ON aw.institute_id = i.id
            LEFT JOIN subjects s ON aw.subject_id = s.id
            WHERE aw.user_id = ?
        ";
        
        if ($unread_only) {
            $query .= " AND aw.is_read = 0";
        }
        
        $query .= " ORDER BY aw.created_at DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$user_id]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch(PDOException $e) {
        error_log("Get warnings error: " . $e->getMessage());
        return [];
    }
}

function markWarningAsRead($warning_id) {
    try {
        $pdo = getDBConnection();
        
        $stmt = $pdo->prepare("
            UPDATE attendance_warnings 
            SET is_read = 1, read_at = NOW() 
            WHERE id = ?
        ");
        return $stmt->execute([$warning_id]);
        
    } catch(PDOException $e) {
        error_log("Mark warning as read error: " . $e->getMessage());
        return false;
    }
}

function userHasAccessToInstitute($institute_id) {
    if (!isLoggedIn()) return false;
    
    try {
        $pdo = getDBConnection();
        if (isTeacher()) {
            $stmt = $pdo->prepare("SELECT id FROM institutes WHERE id = ? AND user_id = ?");
            $stmt->execute([$institute_id, $_SESSION['user_id']]);
        } else {
            $stmt = $pdo->prepare("SELECT id FROM student_teacher_relationships WHERE institute_id = ? AND student_id = ? AND is_active = 1");
            $stmt->execute([$institute_id, $_SESSION['user_id']]);
        }
        return $stmt->rowCount() > 0;
    } catch(PDOException $e) {
        return false;
    }
}
?>