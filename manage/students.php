<?php
require_once '../config.php';
redirectIfNotLoggedIn();
redirectIfNotTeacher();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_email'])) {
    $student_email = trim($_POST['student_email']);
    $institute_id = $_POST['institute_id'];
    
    try {
        $pdo = getDBConnection();
        
        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ? AND role = 'student'");
        $stmt->execute([$student_email]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$student) {
            $error = 'No student found with this email address.';
        } else {
            $stmt = $pdo->prepare("SELECT id FROM student_teacher_relationships WHERE teacher_id = ? AND student_id = ? AND institute_id = ?");
            $stmt->execute([$_SESSION['user_id'], $student['id'], $institute_id]);
            
            if ($stmt->rowCount() > 0) {
                $error = 'Student is already enrolled in this institute.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO student_teacher_relationships (teacher_id, student_id, institute_id) VALUES (?, ?, ?)");
                $stmt->execute([$_SESSION['user_id'], $student['id'], $institute_id]);
                
                $success = 'Student ' . htmlspecialchars($student['name']) . ' has been enrolled successfully!';
            }
        }
    } catch(PDOException $e) {
        $error = 'Failed to enroll student: ' . $e->getMessage();
    }
}

try {
    $pdo = getDBConnection();
    
    // Get teacher's institutes
    $stmt = $pdo->prepare("SELECT id, name FROM institutes WHERE user_id = ? AND is_active = 1 ORDER BY name");
    $stmt->execute([$_SESSION['user_id']]);
    $institutes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get enrolled students
    $stmt = $pdo->prepare("SELECT u.id, u.name, u.email, i.name as institute_name, str.created_at 
                          FROM users u 
                          JOIN student_teacher_relationships str ON u.id = str.student_id 
                          JOIN institutes i ON str.institute_id = i.id 
                          WHERE str.teacher_id = ? AND str.is_active = 1 
                          ORDER BY i.name, u.name");
    $stmt->execute([$_SESSION['user_id']]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $error = "Failed to load data: " . $e->getMessage();
    $institutes = [];
    $students = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students - Student Attendance</title>
    <style>
    :root {
        --primary: #8b5cf6;
        --primary-dark: #7c3aed;
        --secondary: #06b6d4;
        --accent: #10b981;
        --success: #22c55e;
        --warning: #f59e0b;
        --error: #ef4444;
        --text-primary: #f8fafc;
        --text-secondary: #cbd5e1;
        --text-muted: #94a3b8;
        --bg-primary: #0f172a;
        --bg-secondary: #1e293b;
        --bg-tertiary: #334155;
        --bg-gradient: linear-gradient(135deg, #8b5cf6 0%, #06b6d4 100%);
        --bg-gradient-dark: linear-gradient(135deg, #7c3aed 0%, #0891b2 100%);
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
        background: rgba(139, 92, 246, 0.1);
        border-color: var(--glass-border);
        transform: translateY(-1px);
    }

    .nav-links a.active {
        background: var(--bg-gradient);
        color: white;
        box-shadow: var(--shadow-md);
        border-color: transparent;
    }

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
        max-width: 1200px;
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

    .card p {
        color: var(--text-secondary);
        font-size: 1.1rem;
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

    .btn-small {
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
    }

    /* Add Student Form */
    .add-student-form {
        background: var(--bg-tertiary);
        padding: 1.5rem;
        border-radius: var(--border-radius);
        margin-bottom: 2rem;
        border: 1px solid var(--glass-border);
    }

    .add-student-form h3 {
        color: var(--text-primary);
        margin-bottom: 1rem;
        font-size: 1.25rem;
        font-weight: 600;
    }

    .form-group {
        margin-bottom: 1rem;
    }

    label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 600;
        color: var(--text-primary);
    }

    input[type="email"], select {
        width: 100%;
        padding: 0.75rem;
        background: var(--bg-secondary);
        border: 2px solid var(--glass-border);
        border-radius: var(--border-radius);
        font-size: 1rem;
        color: var(--text-primary);
        transition: var(--transition);
    }

    input[type="email"]:focus, select:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
    }

    input[type="email"]::placeholder {
        color: var(--text-muted);
    }

    select option {
        background: var(--bg-secondary);
        color: var(--text-primary);
        padding: 0.5rem;
    }

    /* Students Grid */
    .students-grid {
        display: grid;
        gap: 1.5rem;
    }

    .student-card {
        background: var(--bg-secondary);
        padding: 1.5rem;
        border-radius: var(--border-radius);
        border-left: 4px solid var(--primary);
        box-shadow: var(--shadow-lg);
        transition: var(--transition);
        border: 1px solid var(--glass-border);
        position: relative;
        overflow: hidden;
    }

    .student-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow-xl);
        border-color: var(--primary);
    }

    .student-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 100%;
        background: var(--bg-gradient);
        opacity: 0.02;
        transition: var(--transition);
    }

    .student-card:hover::before {
        opacity: 0.05;
    }

    .student-name {
        font-weight: 600;
        color: var(--text-primary);
        font-size: 1.1rem;
        margin-bottom: 0.5rem;
    }

    .student-meta {
        color: var(--text-muted);
        font-size: 0.9rem;
        margin-bottom: 1rem;
        line-height: 1.5;
    }

    .student-actions {
        display: flex;
        gap: 0.5rem;
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

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 3rem;
        color: var(--text-muted);
    }

    .empty-state h4 {
        margin-bottom: 1rem;
        color: var(--text-primary);
    }

    .empty-state p {
        color: var(--text-secondary);
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

    .card,
    .student-card {
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

        .student-actions {
            flex-direction: column;
        }

        .btn {
            width: 100%;
        }

        .btn-small {
            width: 100%;
            text-align: center;
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

        .add-student-form {
            padding: 1rem;
        }

        .student-card {
            padding: 1rem;
        }
    }

    /* Print Styles */
    @media print {
        .header,
        .add-student-form,
        .student-actions,
        .nav-links {
            display: none !important;
        }

        .card {
            box-shadow: none !important;
            border: 1px solid #ccc !important;
        }

        body {
            background: white !important;
            color: black !important;
        }

        .card,
        .student-card {
            background: white !important;
            color: black !important;
            border-color: #ccc !important;
        }
    }
</style>
</head>
<body>
    <div class="header">
        <h1>👨‍🏫 Teacher Portal</h1>
        <ul class="nav-links">
            <li><a href="../dashboard/teacher-dashboard.php">Dashboard</a></li>
            <li><a href="institutes.php">Institutes</a></li>
            <li><a href="students.php">Students</a></li>
            <li><a href="subjects.php">Subjects</a></li>
            <li><a href="../auth/logout.php" class="logout-btn">Logout (<?php echo htmlspecialchars($_SESSION['user_name']); ?>)</a></li>
        </ul>
    </div>
    
    <div class="container">
        <div class="card">
            <h2 class="page-title">Manage Students</h2>
            <p>Add students to your institutes and manage enrollments</p>
            
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="add-student-form">
                <h3>Add Student to Institute</h3>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="student_email">Student Email</label>
                        <input type="email" id="student_email" name="student_email" required placeholder="Enter student's email address">
                    </div>
                    <div class="form-group">
                        <label for="institute_id">Institute</label>
                        <select id="institute_id" name="institute_id" required>
                            <option value="">Select Institute</option>
                            <?php foreach ($institutes as $institute): ?>
                                <option value="<?php echo $institute['id']; ?>"><?php echo htmlspecialchars($institute['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Add Student</button>
                </form>
            </div>
            
            <h3>Enrolled Students</h3>
            <?php if (empty($students)): ?>
                <div class="empty-state">
                    <h4>No Students Enrolled</h4>
                    <p>You haven't added any students to your institutes yet.</p>
                </div>
            <?php else: ?>
                <div class="students-grid">
                    <?php foreach ($students as $student): ?>
                        <div class="student-card">
                            <div class="student-name"><?php echo htmlspecialchars($student['name']); ?></div>
                            <div class="student-meta">
                                Email: <?php echo htmlspecialchars($student['email']); ?><br>
                                Institute: <?php echo htmlspecialchars($student['institute_name']); ?><br>
                                Enrolled: <?php echo date('M j, Y', strtotime($student['created_at'])); ?>
                            </div>
                            <div class="student-actions">
                                <a href="student-attendance.php?student_id=<?php echo $student['id']; ?>" class="btn btn-small btn-primary">View Attendance</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>