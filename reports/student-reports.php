<?php
// reports/student-reports.php
require_once '../config.php';
redirectIfNotLoggedIn();
redirectIfNotStudent();

$selected_institute = $_GET['institute_id'] ?? '';
$date_range = $_GET['date_range'] ?? '30'; // 7, 30, 90, 365
$chart_type = $_GET['chart_type'] ?? 'bar'; // bar, line, pie

try {
    $pdo = getDBConnection();

    // Get enrolled institutes
    $institutes_stmt = $pdo->prepare("
        SELECT DISTINCT i.id, i.name 
        FROM institutes i 
        JOIN student_teacher_relationships str ON i.id = str.institute_id 
        WHERE str.student_id = ? AND str.is_active = 1 AND i.is_active = 1 
        ORDER BY i.name
    ");
    $institutes_stmt->execute([$_SESSION['user_id']]);
    $institutes = $institutes_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate date range
    $end_date = date('Y-m-d');
    $start_date = date('Y-m-d', strtotime("-$date_range days"));

    $detailed_reports = [];
    $attendance_trend = [];
    $overall_summary = [];

    if ($selected_institute) {
        // Get subjects for selected institute
        $subjects_stmt = $pdo->prepare("
            SELECT id, name, difficulty, color_code
            FROM subjects 
            WHERE institute_id = ? AND is_active = 1 
            ORDER BY name
        ");
        $subjects_stmt->execute([$selected_institute]);
        $subjects = $subjects_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate attendance for each subject
        foreach ($subjects as $subject) {
            $attendance = calculateAttendancePercentage(
                $_SESSION['user_id'],
                $selected_institute,
                $subject['id'],
                $start_date,
                $end_date
            );
            $warning_check = checkAttendanceWarning(
                $_SESSION['user_id'],
                $selected_institute,
                $subject['id']
            );

            $detailed_reports[] = [
                'subject' => $subject,
                'attendance' => $attendance,
                'warnings' => $warning_check
            ];
        }

        // Overall institute attendance
        $institute_attendance = calculateInstituteAttendance(
            $_SESSION['user_id'],
            $selected_institute,
            $start_date,
            $end_date
        );
        $institute_warnings = checkAttendanceWarning($_SESSION['user_id'], $selected_institute);

        // Get attendance trend data (last 7 days)
        $trend_stmt = $pdo->prepare("
            SELECT 
                ar.attendance_date,
                COUNT(*) as total_classes,
                SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) as attended_classes,
                ROUND((SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as percentage
            FROM attendance_records ar
            WHERE ar.user_id = ? 
            AND ar.institute_id = ?
            AND ar.attendance_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND CURDATE()
            GROUP BY ar.attendance_date
            ORDER BY ar.attendance_date
        ");
        $trend_stmt->execute([$_SESSION['user_id'], $selected_institute]);
        $attendance_trend = $trend_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get subject-wise attendance for chart
        $subject_attendance = [];
        foreach ($subjects as $subject) {
            $attendance = calculateAttendancePercentage(
                $_SESSION['user_id'],
                $selected_institute,
                $subject['id'],
                $start_date,
                $end_date
            );
            $subject_attendance[] = [
                'subject' => $subject['name'],
                'percentage' => $attendance['percentage'],
                'color' => $subject['color_code'],
                'attended' => $attendance['attended'],
                'total' => $attendance['total']
            ];
        }

        // Prepare data for charts
        $chart_data = [
            'labels' => array_column($subject_attendance, 'subject'),
            'percentages' => array_column($subject_attendance, 'percentage'),
            'colors' => array_column($subject_attendance, 'color'),
            'attended' => array_column($subject_attendance, 'attended'),
            'total' => array_column($subject_attendance, 'total')
        ];

        // Trend data for line chart
        $trend_labels = [];
        $trend_percentages = [];
        foreach ($attendance_trend as $day) {
            $trend_labels[] = date('M j', strtotime($day['attendance_date']));
            $trend_percentages[] = $day['percentage'];
        }

        $chart_data['trend_labels'] = $trend_labels;
        $chart_data['trend_percentages'] = $trend_percentages;

    }

} catch (PDOException $e) {
    $error = "Failed to load reports: " . $e->getMessage();
    $institutes = [];
    $detailed_reports = [];
    $chart_data = [];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Progress Reports - Student Attendance</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--border-radius);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
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

        .btn-whatsapp {
            background: #25D366;
            color: white;
            box-shadow: var(--shadow-md);
        }

        .btn-whatsapp:hover {
            background: #128C7E;
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        /* Report Filters */
        .report-filters {
            background: var(--bg-tertiary);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
            border: 1px solid var(--glass-border);
        }

        .filter-group {
            margin-bottom: 1rem;
        }

        .filter-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        select {
            width: 100%;
            padding: 0.75rem;
            background: var(--bg-secondary);
            border: 2px solid var(--glass-border);
            border-radius: var(--border-radius);
            font-size: 1rem;
            color: var(--text-primary);
            transition: var(--transition);
        }

        select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        select option {
            background: var(--bg-secondary);
            color: var(--text-primary);
            padding: 0.5rem;
        }

        /* Summary Cards */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .summary-card {
            background: var(--bg-secondary);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            text-align: center;
            box-shadow: var(--shadow-lg);
            transition: var(--transition);
            border: 1px solid var(--glass-border);
            border-left: 4px solid var(--primary);
        }

        .summary-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-xl);
        }

        .summary-number {
            font-size: 2rem;
            font-weight: 800;
            background: var(--bg-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .summary-label {
            color: var(--text-muted);
            margin-top: 0.5rem;
            font-weight: 500;
        }

        /* Charts Container */
        .charts-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .chart-card {
            background: var(--bg-secondary);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--glass-border);
            transition: var(--transition);
        }

        .chart-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-xl);
        }

        .chart-title {
            margin-bottom: 1rem;
            color: var(--text-primary);
            font-size: 1.2rem;
            font-weight: 600;
        }

        .chart-wrapper {
            position: relative;
            height: 300px;
        }

        /* Subject Reports */
        .subject-report {
            background: var(--bg-secondary);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--glass-border);
            transition: var(--transition);
        }

        .subject-report:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-xl);
        }

        .subject-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .attendance-subject {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 1.1rem;
        }

        .attendance-details {
            color: var(--text-muted);
            font-size: 0.9rem;
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
            transition: width 0.3s;
        }

        .progress-good {
            background: var(--success);
        }

        .progress-warning {
            background: var(--warning);
        }

        .progress-critical {
            background: var(--error);
        }

        /* Warning Messages */
        .warning-message {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
            padding: 0.75rem;
            border-radius: var(--border-radius);
            margin-top: 0.5rem;
            border-left: 4px solid var(--warning);
            border: 1px solid rgba(245, 158, 11, 0.2);
        }

        .critical-message {
            background: rgba(239, 68, 68, 0.1);
            color: var(--error);
            padding: 0.75rem;
            border-radius: var(--border-radius);
            margin-top: 0.5rem;
            border-left: 4px solid var(--error);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        /* Export Section */
        .export-section {
            background: rgba(16, 185, 129, 0.1);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            margin-top: 2rem;
            text-align: center;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .export-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 1rem;
            flex-wrap: wrap;
        }

        /* WhatsApp Modal */
        #whatsappModal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(5px);
        }

        #whatsappModal>div {
            background: var(--bg-secondary);
            padding: 2rem;
            border-radius: var(--border-radius-lg);
            max-width: 500px;
            width: 90%;
            border: 1px solid var(--glass-border);
            box-shadow: var(--shadow-xl);
        }

        #whatsappModal h3 {
            color: var(--text-primary);
            margin-bottom: 1rem;
        }

        #whatsappModal p {
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
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
        .summary-card,
        .chart-card,
        .subject-report {
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

            .charts-container {
                grid-template-columns: 1fr;
            }

            .export-buttons {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .report-filters>div {
                grid-template-columns: 1fr;
            }

            .subject-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
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

            .summary-cards {
                grid-template-columns: 1fr;
            }
        }

        /* Print Styles */
        @media print {

            .header,
            .report-filters,
            .export-section,
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
            .summary-card,
            .chart-card,
            .subject-report {
                background: white !important;
                color: black !important;
                border-color: #ccc !important;
            }
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>🎓 Student Portal</h1>
        <ul class="nav-links">
            <li><a href="../dashboard/student-dashboard.php">Dashboard</a></li>
            <li><a href="../attendance/mark-attendance.php">Mark Attendance</a></li>
            <li><a href="../attendance/attendance-history.php">My Attendance</a></li>
            <li><a href="student-reports.php">My Progress</a></li>
            <li><a href="../auth/logout.php" class="logout-btn">Logout
                    (<?php echo htmlspecialchars($_SESSION['user_name']); ?>)</a></li>
        </ul>
    </div>

    <div class="container">
        <div class="card">
            <h2 class="page-title">My Progress Reports</h2>
            <p>Detailed attendance analysis with visual charts and progress tracking</p>

            <div class="report-filters">
                <form method="GET" action="" id="reportForm">
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                        <div class="filter-group">
                            <label for="institute_id">Select Institute</label>
                            <select id="institute_id" name="institute_id">
                                <option value="">All Institutes</option>
                                <?php foreach ($institutes as $institute): ?>
                                    <option value="<?php echo $institute['id']; ?>" <?php echo $selected_institute == $institute['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($institute['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="date_range">Date Range</label>
                            <select id="date_range" name="date_range">
                                <option value="7" <?php echo $date_range == '7' ? 'selected' : ''; ?>>Last 7 days</option>
                                <option value="30" <?php echo $date_range == '30' ? 'selected' : ''; ?>>Last 30 days
                                </option>
                                <option value="90" <?php echo $date_range == '90' ? 'selected' : ''; ?>>Last 90 days
                                </option>
                                <option value="365" <?php echo $date_range == '365' ? 'selected' : ''; ?>>Last year
                                </option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="chart_type">Chart Type</label>
                            <select id="chart_type" name="chart_type">
                                <option value="bar" <?php echo $chart_type == 'bar' ? 'selected' : ''; ?>>Bar Chart
                                </option>
                                <option value="line" <?php echo $chart_type == 'line' ? 'selected' : ''; ?>>Line Chart
                                </option>
                                <option value="pie" <?php echo $chart_type == 'pie' ? 'selected' : ''; ?>>Pie Chart
                                </option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary" style="margin-top: 1rem;">Generate Report</button>
                </form>
            </div>

            <?php if ($selected_institute && !empty($detailed_reports)): ?>
                <!-- Summary Cards -->
                <div class="summary-cards">
                    <div class="summary-card">
                        <div class="summary-number"><?php echo $institute_attendance['percentage']; ?>%</div>
                        <div class="summary-label">Overall Attendance</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-number"><?php echo $institute_attendance['attended']; ?></div>
                        <div class="summary-label">Classes Attended</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-number"><?php echo $institute_attendance['total']; ?></div>
                        <div class="summary-label">Total Classes</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-number"><?php echo count($detailed_reports); ?></div>
                        <div class="summary-label">Subjects</div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="charts-container">
                    <div class="chart-card">
                        <h3 class="chart-title">Subject-wise Attendance</h3>
                        <div class="chart-wrapper">
                            <canvas id="subjectChart"></canvas>
                        </div>
                    </div>

                    <div class="chart-card">
                        <h3 class="chart-title">Attendance Trend (Last 7 Days)</h3>
                        <div class="chart-wrapper">
                            <canvas id="trendChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Subject-wise Reports -->
                <h3 style="margin-bottom: 1rem;">Subject-wise Performance</h3>
                <?php foreach ($detailed_reports as $report):
                    $subject = $report['subject'];
                    $attendance = $report['attendance'];
                    $warnings = $report['warnings'];

                    $percentage_class = 'percentage-good';
                    $progress_class = 'progress-good';

                    if ($attendance['percentage'] > 0) {
                        if ($attendance['percentage'] < $warnings['goal']['warning_threshold']) {
                            $percentage_class = 'percentage-critical';
                            $progress_class = 'progress-critical';
                        } elseif ($attendance['percentage'] < $warnings['goal']['target_percentage']) {
                            $percentage_class = 'percentage-warning';
                            $progress_class = 'progress-warning';
                        }
                    }
                    ?>
                    <div class="subject-report">
                        <div class="subject-header">
                            <div>
                                <div class="attendance-subject"><?php echo htmlspecialchars($subject['name']); ?></div>
                                <div class="attendance-details">
                                    Difficulty: <?php echo ucfirst($subject['difficulty']); ?> •
                                    <?php echo $attendance['attended']; ?> attended / <?php echo $attendance['total']; ?> total
                                    classes
                                </div>
                            </div>
                            <div class="attendance-percentage <?php echo $percentage_class; ?>">
                                <?php echo $attendance['percentage']; ?>%
                            </div>
                        </div>

                        <!-- Progress Bar -->
                        <div class="progress-bar">
                            <div class="progress-fill <?php echo $progress_class; ?>"
                                style="width: <?php echo min($attendance['percentage'], 100); ?>%">
                            </div>
                        </div>

                        <!-- Status Message -->
                        <?php if ($warnings['has_warnings']): ?>
                            <div
                                class="<?php echo $warnings['warnings'][0]['type'] === 'critical' ? 'critical-message' : 'warning-message'; ?>">
                                ⚠️ <?php echo $warnings['warnings'][0]['message']; ?>
                                (Target: <?php echo $warnings['goal']['target_percentage']; ?>%)
                            </div>
                        <?php elseif ($attendance['percentage'] >= $warnings['goal']['target_percentage']): ?>
                            <div style="color: #28a745; margin-top: 1rem;">
                                ✅ Meeting attendance target (Target: <?php echo $warnings['goal']['target_percentage']; ?>%)
                            </div>
                        <?php else: ?>
                            <div style="color: #ffc107; margin-top: 1rem;">
                                ⚠️ Below target (Target: <?php echo $warnings['goal']['target_percentage']; ?>%)
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <!-- Export Section -->
                <div class="export-section">
                    <h3>Share Your Progress</h3>
                    <p>Export your attendance report or share it via WhatsApp</p>
                    <div class="export-buttons">
                        <button type="button" class="btn btn-whatsapp" onclick="shareToWhatsApp()">
                            📱 Share to WhatsApp
                        </button>
                        <button type="button" class="btn btn-primary" onclick="exportAsImage()">
                            📊 Export as Image
                        </button>
                        <button type="button" class="btn btn-primary" onclick="printReport()">
                            🖨️ Print Report
                        </button>
                    </div>
                </div>

            <?php elseif ($selected_institute && empty($detailed_reports)): ?>
                <p style="color: #666; text-align: center; padding: 2rem;">No subjects found for this institute.</p>
            <?php else: ?>
                <p style="color: #666; text-align: center; padding: 2rem;">Please select an institute to view detailed
                    reports.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- WhatsApp Sharing Modal -->
    <div id="whatsappModal"
        style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
        <div style="background: white; padding: 2rem; border-radius: 10px; max-width: 500px; width: 90%;">
            <h3>Share via WhatsApp</h3>
            <p>Your attendance summary has been prepared. Click the button below to open WhatsApp and share your
                progress!</p>
            <div style="margin-top: 1rem;">
                <a id="whatsappLink" class="btn btn-whatsapp" style="width: 100%; justify-content: center;">
                    📱 Open WhatsApp
                </a>
                <button type="button" class="btn btn-secondary" onclick="closeWhatsAppModal()"
                    style="width: 100%; margin-top: 0.5rem;">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    <script>
        // Chart initialization
        <?php if ($selected_institute && !empty($chart_data)): ?>
            document.addEventListener('DOMContentLoaded', function () {
                const chartType = '<?php echo $chart_type; ?>';

                // Subject-wise Chart
                const subjectCtx = document.getElementById('subjectChart').getContext('2d');
                const subjectChart = new Chart(subjectCtx, {
                    type: chartType,
                    data: {
                        labels: <?php echo json_encode($chart_data['labels']); ?>,
                        datasets: [{
                            label: 'Attendance Percentage',
                            data: <?php echo json_encode($chart_data['percentages']); ?>,
                            backgroundColor: <?php echo json_encode($chart_data['colors']); ?>,
                            borderColor: '#333',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: chartType === 'line' ? {
                            y: {
                                beginAtZero: true,
                                max: 100,
                                title: {
                                    display: true,
                                    text: 'Percentage (%)'
                                }
                            }
                        } : {
                            y: {
                                beginAtZero: true,
                                max: 100
                            }
                        },
                        plugins: {
                            legend: {
                                display: chartType === 'pie',
                                position: 'bottom'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        const label = context.label || '';
                                        const value = context.raw || 0;
                                        const index = context.dataIndex;
                                        const attended = <?php echo json_encode($chart_data['attended']); ?>[index];
                                        const total = <?php echo json_encode($chart_data['total']); ?>[index];
                                        return `${label}: ${value}% (${attended}/${total} classes)`;
                                    }
                                }
                            }
                        }
                    }
                });

                // Trend Chart
                const trendCtx = document.getElementById('trendChart').getContext('2d');
                const trendChart = new Chart(trendCtx, {
                    type: 'line',
                    data: {
                        labels: <?php echo json_encode($chart_data['trend_labels']); ?>,
                        datasets: [{
                            label: 'Daily Attendance (%)',
                            data: <?php echo json_encode($chart_data['trend_percentages']); ?>,
                            backgroundColor: 'rgba(40, 167, 69, 0.2)',
                            borderColor: '#28a745',
                            borderWidth: 2,
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 100,
                                title: {
                                    display: true,
                                    text: 'Attendance (%)'
                                }
                            }
                        }
                    }
                });
            });
        <?php endif; ?>

        // WhatsApp Sharing Function
        function shareToWhatsApp() {
            // Generate summary text
            let summaryText = `📊 My Attendance Report - <?php echo htmlspecialchars($_SESSION['user_name']); ?>\n\n`;
            summaryText += `Institute: <?php echo $selected_institute ? htmlspecialchars($institutes[array_search($selected_institute, array_column($institutes, 'id'))]['name']) : 'All Institutes'; ?>\n`;
            summaryText += `Period: Last <?php echo $date_range; ?> days\n\n`;

            <?php if (!empty($detailed_reports)): ?>
                summaryText += `Subject-wise Attendance:\n`;
                <?php foreach ($detailed_reports as $report): ?>
                    summaryText += `• <?php echo htmlspecialchars($report['subject']['name']); ?>: <?php echo $report['attendance']['percentage']; ?>%\n`;
                <?php endforeach; ?>
                summaryText += `\nOverall: <?php echo $institute_attendance['percentage']; ?>% (<?php echo $institute_attendance['attended']; ?>/<?php echo $institute_attendance['total']; ?> classes)\n\n`;
            <?php endif; ?>

            summaryText += `Generated on: <?php echo date('M j, Y'); ?>\n`;
            summaryText += `Student Attendance System`;

            // URL encode the text
            const encodedText = encodeURIComponent(summaryText);
            const whatsappUrl = `https://wa.me/?text=${encodedText}`;

            // Update the link and show modal
            document.getElementById('whatsappLink').href = whatsappUrl;
            document.getElementById('whatsappModal').style.display = 'flex';
        }

        function closeWhatsAppModal() {
            document.getElementById('whatsappModal').style.display = 'none';
        }

        // Export as Image
        function exportAsImage() {
            const reportCard = document.querySelector('.card');

            html2canvas(reportCard).then(canvas => {
                const link = document.createElement('a');
                link.download = 'attendance-report-<?php echo date('Y-m-d'); ?>.png';
                link.href = canvas.toDataURL();
                link.click();
            });
        }

        // Print Report
        function printReport() {
            window.print();
        }

        // Auto-submit form when filters change
        document.getElementById('institute_id').addEventListener('change', function () {
            document.getElementById('reportForm').submit();
        });

        // Close modal when clicking outside
        document.getElementById('whatsappModal').addEventListener('click', function (e) {
            if (e.target === this) {
                closeWhatsAppModal();
            }
        });
    </script>

    <!-- Include html2canvas for image export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <!-- Print Styles -->
    <style>
        @media print {

            .header,
            .report-filters,
            .export-section,
            .nav-links {
                display: none !important;
            }

            .card {
                box-shadow: none !important;
                border: 1px solid #ccc !important;
            }

            body {
                background: white !important;
            }
        }
    </style>
</body>

</html>