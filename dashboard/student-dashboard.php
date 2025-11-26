<?php
require_once '../config.php';
redirectIfNotLoggedIn();
redirectIfNotStudent();

try {
    $pdo = getDBConnection();

    $institutes_stmt = $pdo->prepare("
        SELECT DISTINCT i.id, i.name 
        FROM institutes i 
        JOIN student_teacher_relationships str ON i.id = str.institute_id 
        WHERE str.student_id = ? AND str.is_active = 1 AND i.is_active = 1 
        ORDER BY i.name
    ");
    $institutes_stmt->execute([$_SESSION['user_id']]);
    $institutes = $institutes_stmt->fetchAll(PDO::FETCH_ASSOC);

    $institute_attendance = [];
    foreach ($institutes as $institute) {
        $attendance_data = calculateInstituteAttendance($_SESSION['user_id'], $institute['id']);
        $warning_check = checkAttendanceWarning($_SESSION['user_id'], $institute['id']);

        $institute_attendance[$institute['id']] = [
            'institute' => $institute,
            'attendance' => $attendance_data,
            'warnings' => $warning_check
        ];
    }

    $recent_stmt = $pdo->prepare("
        SELECT ar.*, i.name as institute_name, s.name as subject_name 
        FROM attendance_records ar 
        JOIN institutes i ON ar.institute_id = i.id 
        JOIN subjects s ON ar.subject_id = s.id 
        WHERE ar.user_id = ? 
        ORDER BY ar.attendance_date DESC, ar.created_at DESC 
        LIMIT 5
    ");
    $recent_stmt->execute([$_SESSION['user_id']]);
    $recent_attendance = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get today's attendance count
    $today_stmt = $pdo->prepare("
        SELECT COUNT(*) as today_count FROM attendance_records 
        WHERE user_id = ? AND attendance_date = CURDATE()
    ");
    $today_stmt->execute([$_SESSION['user_id']]);
    $today_count = $today_stmt->fetchColumn();

    $warnings = getUserWarnings($_SESSION['user_id'], true);

} catch (PDOException $e) {
    $error = "Failed to load data: " . $e->getMessage();
    $institutes = [];
    $institute_attendance = [];
    $recent_attendance = [];
    $today_count = 0;
    $warnings = [];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Student Attendance</title>
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


        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
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

        /* Quick Actions Grid */
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

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
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
            font-size: 2rem;
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

        /* Institute Cards */
        .attendance-overview {
            margin-top: 2rem;
        }

        .institute-cards {
            display: grid;
            gap: 1.5rem;
            margin-top: 1rem;
        }

        .institute-card {
            background: var(--bg-secondary);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
            border-left: 4px solid var(--primary);
            transition: var(--transition);
            border: 1px solid var(--glass-border);
        }

        .institute-card.warning {
            border-left-color: var(--warning);
            background: rgba(245, 158, 11, 0.05);
        }

        .institute-card.critical {
            border-left-color: var(--error);
            background: rgba(239, 68, 68, 0.05);
        }

        .institute-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-xl);
        }

        .institute-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .institute-name {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 1.1rem;
        }

        .attendance-percentage {
            font-size: 1.5rem;
            font-weight: bold;
        }

        .percentage-good {
            color: var(--success);
        }

        .percentage-warning {
            color: var(--warning);
        }

        .percentage-critical {
            color: var(--error);
        }

        .attendance-details {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        /* Warning Messages */
        .warning-message {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
            padding: 0.75rem;
            border-radius: 5px;
            margin-top: 0.5rem;
            border-left: 4px solid var(--warning);
        }

        .critical-message {
            background: rgba(239, 68, 68, 0.1);
            color: var(--error);
            padding: 0.75rem;
            border-radius: 5px;
            margin-top: 0.5rem;
            border-left: 4px solid var(--error);
        }

        /* Recent Attendance */
        .recent-attendance {
            margin-top: 2rem;
        }

        .attendance-list {
            margin-top: 1rem;
            background: var(--bg-secondary);
            border-radius: var(--border-radius);
            overflow: hidden;
            border: 1px solid var(--glass-border);
        }

        .attendance-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid var(--glass-border);
            transition: var(--transition);
        }

        .attendance-item:hover {
            background: var(--bg-tertiary);
        }

        .attendance-item:last-child {
            border-bottom: none;
        }

        .attendance-subject {
            font-weight: 600;
            color: var(--text-primary);
        }

        .attendance-date {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .attendance-status {
            color: var(--success);
            font-weight: 600;
        }

        /* Alert Styles */
        .alert {
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-top: 1rem;
            text-align: center;
            border: 1px solid;
        }

        .alert-warning {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
            border-color: var(--warning);
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            color: var(--success);
            border-color: var(--success);
        }

        /* Warnings Section */
        .warnings-section {
            margin-top: 2rem;
        }

        .warning-item {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 0.5rem;
            border-left: 4px solid var(--warning);
        }

        .warning-item.critical {
            background: rgba(239, 68, 68, 0.1);
            color: var(--error);
            border-left-color: var(--error);
        }

        /* Progress Bar */
        .progress-bar {
            width: 100%;
            height: 8px;
            background: var(--bg-tertiary);
            border-radius: 4px;
            overflow: hidden;
            margin-top: 0.5rem;
        }

        .progress-fill {
            height: 100%;
            background: var(--success);
            transition: width 0.3s;
        }

        .progress-fill.warning {
            background: var(--warning);
        }

        .progress-fill.critical {
            background: var(--error);
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

        .card,
        .action-card,
        .stat-card,
        .institute-card {
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

            .quick-actions {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .institute-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
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
        <h1>🎓 Student Portal</h1>
        <ul class="nav-links">
            <li><a href="student-dashboard.php">Dashboard</a></li>
            <li><a href="../attendance/mark-attendance.php">Mark Attendance</a></li>
            <li><a href="../attendance/attendance-history.php">My Attendance</a></li>
            <li><a href="../reports/student-reports.php">My Progress</a></li>
            <li><a href="../auth/logout.php" class="logout-btn">Logout
                    (<?php echo htmlspecialchars($_SESSION['user_name']); ?>)</a></li>
        </ul>
    </div>

    <div class="container">
        <div class="card">
            <h2 class="page-title">Student Dashboard</h2>
            <p>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>! Here's your attendance overview.</p>

            <!-- Warnings Section -->
            <?php if (!empty($warnings)): ?>
                <div class="warnings-section">
                    <h3>⚠️ Important Notifications</h3>
                    <?php foreach ($warnings as $warning): ?>
                        <div class="warning-item <?php echo $warning['warning_type']; ?>">
                            <strong><?php echo htmlspecialchars($warning['institute_name']); ?></strong>
                            <?php if ($warning['subject_name']): ?>
                                - <?php echo htmlspecialchars($warning['subject_name']); ?>
                            <?php endif; ?>
                            <br>
                            <?php echo htmlspecialchars($warning['message']); ?>
                            <br>
                            <small>Date: <?php echo date('M j, Y g:i A', strtotime($warning['created_at'])); ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $today_count; ?></div>
                    <div class="stat-label">Today's Classes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($institutes); ?></div>
                    <div class="stat-label">Enrolled Institutes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($warnings); ?></div>
                    <div class="stat-label">Active Warnings</div>
                </div>
            </div>

            <div class="quick-actions">
                <a href="../attendance/mark-attendance.php" class="action-card">
                    <div class="action-icon">📷</div>
                    <h3>Mark Attendance</h3>
                    <p class="action-description">Take selfie and mark today's attendance</p>
                </a>

                <a href="../attendance/attendance-history.php" class="action-card">
                    <div class="action-icon">📊</div>
                    <h3>Attendance History</h3>
                    <p class="action-description">View your attendance records</p>
                </a>

                <a href="../reports/student-reports.php" class="action-card">
                    <div class="action-icon">📈</div>
                    <h3>My Progress</h3>
                    <p class="action-description">Track your attendance percentage</p>
                </a>
            </div>

            <!-- Institute Attendance Overview -->
            <div class="attendance-overview">
                <h3>Institute Attendance</h3>
                <div class="institute-cards">
                    <?php if (empty($institute_attendance)): ?>
                        <p style="color: #666; text-align: center; padding: 2rem;">No enrolled institutes found.</p>
                    <?php else: ?>
                        <?php foreach ($institute_attendance as $data):
                            $institute = $data['institute'];
                            $attendance = $data['attendance'];
                            $warnings = $data['warnings'];

                            $card_class = '';
                            $percentage_class = 'percentage-good';

                            if ($attendance['percentage'] > 0) {
                                if ($attendance['percentage'] < $warnings['goal']['warning_threshold']) {
                                    $card_class = 'critical';
                                    $percentage_class = 'percentage-critical';
                                } elseif ($attendance['percentage'] < $warnings['goal']['target_percentage']) {
                                    $card_class = 'warning';
                                    $percentage_class = 'percentage-warning';
                                }
                            }
                            ?>
                            <div class="institute-card <?php echo $card_class; ?>">
                                <div class="institute-header">
                                    <div class="institute-name"><?php echo htmlspecialchars($institute['name']); ?></div>
                                    <div class="attendance-percentage <?php echo $percentage_class; ?>">
                                        <?php echo $attendance['percentage']; ?>%
                                    </div>
                                </div>

                                <div class="attendance-details">
                                    <?php echo $attendance['attended']; ?> attended out of <?php echo $attendance['total']; ?>
                                    classes
                                </div>

                                <!-- Progress Bar -->
                                <div class="progress-bar">
                                    <div class="progress-fill <?php echo $card_class; ?>"
                                        style="width: <?php echo min($attendance['percentage'], 100); ?>%">
                                    </div>
                                </div>

                                <!-- Warning Messages -->
                                <?php if ($warnings['has_warnings']): ?>
                                    <?php foreach ($warnings['warnings'] as $warning): ?>
                                        <div
                                            class="<?php echo $warning['type'] === 'critical' ? 'critical-message' : 'warning-message'; ?>">
                                            ⚠️ <?php echo htmlspecialchars($warning['message']); ?>
                                            (Target: <?php echo $warnings['goal']['target_percentage']; ?>%)
                                        </div>
                                    <?php endforeach; ?>
                                <?php elseif ($attendance['percentage'] >= $warnings['goal']['target_percentage']): ?>
                                    <div style="color: #28a745; margin-top: 0.5rem;">
                                        ✅ Meeting attendance target
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Attendance -->
            <div class="recent-attendance">
                <h3>Recent Attendance</h3>
                <div class="attendance-list">
                    <?php if (empty($recent_attendance)): ?>
                        <p style="color: #666; text-align: center; padding: 2rem;">No attendance records yet. Mark your
                            first attendance!</p>
                    <?php else: ?>
                        <?php foreach ($recent_attendance as $record): ?>
                            <div class="attendance-item">
                                <div>
                                    <div class="attendance-subject"><?php echo htmlspecialchars($record['subject_name']); ?>
                                    </div>
                                    <div class="attendance-date"><?php echo htmlspecialchars($record['institute_name']); ?> •
                                        <?php echo date('M j, Y', strtotime($record['attendance_date'])); ?></div>
                                </div>
                                <div class="attendance-status">✅ Present</div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <?php if ($today_count == 0): ?>
                    <div class="alert alert-warning">
                        <strong>Reminder:</strong> You haven't marked attendance for today!
                        <a href="../attendance/mark-attendance.php"
                            style="color: #856404; text-decoration: underline; margin-left: 0.5rem;">Mark now</a>
                    </div>
                <?php else: ?>
                    <div class="alert alert-success">
                        <strong>Great job!</strong> You've marked attendance for today.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>

</html>