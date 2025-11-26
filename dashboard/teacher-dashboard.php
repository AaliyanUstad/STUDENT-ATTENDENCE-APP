<?php
require_once '../config.php';
redirectIfNotLoggedIn();
redirectIfNotTeacher();

try {
    $pdo = getDBConnection();

    $stmt = $pdo->prepare("SELECT COUNT(*) as institute_count FROM institutes WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $institute_count = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) as subject_count FROM subjects s JOIN institutes i ON s.institute_id = i.id WHERE i.user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $subject_count = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) as student_count FROM student_teacher_relationships WHERE teacher_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $student_count = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) as attendance_count FROM attendance_records ar JOIN institutes i ON ar.institute_id = i.id WHERE i.user_id = ? AND ar.attendance_date = CURDATE()");
    $stmt->execute([$_SESSION['user_id']]);
    $today_attendance = $stmt->fetchColumn();

} catch (PDOException $e) {
    $institute_count = $subject_count = $student_count = $today_attendance = 0;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - Student Attendance</title>
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
            position: relative;
            overflow: hidden;
            border: 1px solid transparent;
        }


        .nav-links a:hover {
            color: white;
            transform: translateY(-1px);
            border-color: var(--glass-border);
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
            position: relative;
            overflow: hidden;
        }

       

        .logout-btn:hover::before {
            left: 100%;
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        /* Modern Welcome Card with Dark Theme */
        .welcome-card {
            background: var(--bg-secondary);
            padding: 2.5rem;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-xl);
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
            border: 1px solid var(--glass-border);
        }

        .welcome-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--bg-gradient);
        }

        .welcome-card h2 {
            color: var(--text-primary);
            font-size: 2rem;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }

        .welcome-card p {
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }

        .teacher-badge {
            background: var(--bg-gradient);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-left: 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            box-shadow: var(--shadow-md);
        }

        /* Modern Quick Actions Grid */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .action-card {
            background: var(--bg-secondary);
            padding: 2rem;
            border-radius: var(--border-radius);
            text-align: center;
            box-shadow: var(--shadow-lg);
            text-decoration: none;
            color: var(--text-primary);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            border: 1px solid var(--glass-border);
        }

        .action-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--bg-gradient);
            transform: scaleX(0);
            transition: var(--transition);
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
            background: var(--bg-tertiary);
        }

        .action-card:hover::before {
            transform: scaleX(1);
        }

        .action-card h3 {
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .action-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            background: var(--bg-gradient);
            -webkit-background-clip: text;
            background-clip: text;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.3));
        }

        .action-description {
            color: var(--text-muted);
            margin-top: 0.75rem;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        /* Modern Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }

        .stat-card {
            background: var(--bg-secondary);
            padding: 2rem;
            border-radius: var(--border-radius);
            text-align: center;
            box-shadow: var(--shadow-lg);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            border: 1px solid var(--glass-border);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 100%;
            background: var(--bg-gradient);
            opacity: 0.05;
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-xl);
            background: var(--bg-tertiary);
        }

        .stat-card:hover::before {
            opacity: 0.1;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            background: var(--bg-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
            position: relative;
        }

        .stat-label {
            color: var(--text-muted);
            margin-top: 0.5rem;
            font-weight: 500;
            position: relative;
        }

        /* Loading animation for cards */
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

        .welcome-card,
        .action-card,
        .stat-card {
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

            .welcome-card {
                padding: 1.5rem;
            }

            .quick-actions {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

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
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>👨‍🏫 Teacher Portal</h1>
        <ul class="nav-links">
            <li><a href="teacher-dashboard.php">Dashboard</a></li>
            <li><a href="../manage/institutes.php">Institutes</a></li>
            <li><a href="../manage/students.php">Students</a></li>
            <li><a href="../reports/teacher-reports.php">Reports</a></li>
            <li><a href="../auth/logout.php" class="logout-btn">Logout
                    (<?php echo htmlspecialchars($_SESSION['user_name']); ?>)</a></li>
        </ul>
    </div>

    <div class="container">
        <div class="welcome-card">
            <h2>Teacher Dashboard</h2>
            <p>Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?>! <span
                    class="teacher-badge">TEACHER</span></p>
            <p>Manage your institutes, subjects, and student attendance records.</p>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $institute_count; ?></div>
                    <div class="stat-label">Institutes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $subject_count; ?></div>
                    <div class="stat-label">Subjects</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $student_count; ?></div>
                    <div class="stat-label">Students</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $today_attendance; ?></div>
                    <div class="stat-label">Today's Attendance</div>
                </div>
            </div>

            <div class="quick-actions">
                <a href="../manage/institutes.php" class="action-card">
                    <div class="action-icon">🏫</div>
                    <h3>Manage Institutes</h3>
                    <p class="action-description">Create and manage educational institutes</p>
                </a>

                <a href="../manage/subjects.php" class="action-card">
                    <div class="action-icon">📚</div>
                    <h3>Manage Subjects</h3>
                    <p class="action-description">Add and organize subjects for your institutes</p>
                </a>

                <a href="../manage/students.php" class="action-card">
                    <div class="action-icon">👥</div>
                    <h3>Manage Students</h3>
                    <p class="action-description">Add students to your institutes</p>
                </a>

                <a href="../reports/teacher-reports.php" class="action-card">
                    <div class="action-icon">📊</div>
                    <h3>View Reports</h3>
                    <p class="action-description">Analyze attendance data and generate reports</p>
                </a>
            </div>
        </div>
    </div>
</body>

</html>