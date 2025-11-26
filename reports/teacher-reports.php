<?php
// reports/teacher-reports.php
require_once '../config.php';
redirectIfNotLoggedIn();
redirectIfNotTeacher();

$selected_institute = $_GET['institute_id'] ?? '';
$date_range = $_GET['date_range'] ?? '30';
$chart_type = $_GET['chart_type'] ?? 'bar';

try {
    $pdo = getDBConnection();
    
    // Get teacher's institutes
    $institutes_stmt = $pdo->prepare("
        SELECT id, name FROM institutes 
        WHERE user_id = ? AND is_active = 1 
        ORDER BY name
    ");
    $institutes_stmt->execute([$_SESSION['user_id']]);
    $institutes = $institutes_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $reports_data = [];
    $overall_stats = [];
    $chart_data = [];
    
    if ($selected_institute) {
        // Get institute name
        $institute_name_stmt = $pdo->prepare("SELECT name FROM institutes WHERE id = ?");
        $institute_name_stmt->execute([$selected_institute]);
        $institute_name = $institute_name_stmt->fetchColumn();
        
        // Get students for this institute
        $students_stmt = $pdo->prepare("
            SELECT u.id, u.name, u.email
            FROM users u
            JOIN student_teacher_relationships str ON u.id = str.student_id
            WHERE str.teacher_id = ? AND str.institute_id = ? AND str.is_active = 1
            ORDER BY u.name
        ");
        $students_stmt->execute([$_SESSION['user_id'], $selected_institute]);
        $students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get subjects for this institute
        $subjects_stmt = $pdo->prepare("
            SELECT id, name, color_code FROM subjects 
            WHERE institute_id = ? AND is_active = 1 
            ORDER BY name
        ");
        $subjects_stmt->execute([$selected_institute]);
        $subjects = $subjects_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate date range
        $end_date = date('Y-m-d');
        $start_date = date('Y-m-d', strtotime("-$date_range days"));
        
        // Overall statistics
        $total_students = count($students);
        $total_subjects = count($subjects);
        $total_attendance_records = 0;
        $total_possible_classes = 0;
        $average_attendance = 0;
        
        // Calculate attendance for each student
        foreach ($students as $student) {
            $student_attendance = [];
            $total_attended = 0;
            $total_possible = 0;
            
            foreach ($subjects as $subject) {
                $attendance = calculateAttendancePercentage(
                    $student['id'], 
                    $selected_institute, 
                    $subject['id'],
                    $start_date,
                    $end_date
                );
                
                $student_attendance[] = [
                    'subject' => $subject,
                    'attendance' => $attendance
                ];
                
                $total_attended += $attendance['attended'];
                $total_possible += $attendance['total'];
            }
            
            $overall_percentage = $total_possible > 0 ? round(($total_attended / $total_possible) * 100, 2) : 0;
            
            $reports_data[] = [
                'student' => $student,
                'attendance' => $student_attendance,
                'overall' => [
                    'attended' => $total_attended,
                    'total' => $total_possible,
                    'percentage' => $overall_percentage
                ]
            ];
            
            $total_attendance_records += $total_attended;
            $total_possible_classes += $total_possible;
        }
        
        // Calculate overall statistics
        if ($total_possible_classes > 0) {
            $average_attendance = round(($total_attendance_records / $total_possible_classes) * 100, 2);
        }
        
        $overall_stats = [
            'total_students' => $total_students,
            'total_subjects' => $total_subjects,
            'total_attendance_records' => $total_attendance_records,
            'total_possible_classes' => $total_possible_classes,
            'average_attendance' => $average_attendance
        ];
        
        // Prepare chart data - Student Performance
        $chart_labels = [];
        $chart_percentages = [];
        $chart_colors = [];
        
        foreach ($reports_data as $data) {
            $chart_labels[] = $data['student']['name'];
            $chart_percentages[] = $data['overall']['percentage'];
            
            // Color coding based on performance
            if ($data['overall']['percentage'] >= 80) {
                $chart_colors[] = '#28a745'; // Green for good
            } elseif ($data['overall']['percentage'] >= 60) {
                $chart_colors[] = '#ffc107'; // Yellow for average
            } else {
                $chart_colors[] = '#dc3545'; // Red for poor
            }
        }
        
        // Subject-wise average attendance
        $subject_stats = [];
        foreach ($subjects as $subject) {
            $subject_total = 0;
            $subject_count = 0;
            
            foreach ($reports_data as $data) {
                foreach ($data['attendance'] as $att) {
                    if ($att['subject']['id'] == $subject['id']) {
                        $subject_total += $att['attendance']['percentage'];
                        $subject_count++;
                    }
                }
            }
            
            $subject_avg = $subject_count > 0 ? round($subject_total / $subject_count, 2) : 0;
            $subject_stats[] = [
                'subject' => $subject,
                'average_percentage' => $subject_avg
            ];
        }
        
        // Low attendance alerts
        $low_attendance_students = array_filter($reports_data, function($data) {
            return $data['overall']['percentage'] < 70;
        });
        
        $chart_data = [
            'student_labels' => $chart_labels,
            'student_percentages' => $chart_percentages,
            'student_colors' => $chart_colors,
            'subject_stats' => $subject_stats,
            'low_attendance_count' => count($low_attendance_students)
        ];
        
        // Attendance trend (last 7 days)
        $trend_stmt = $pdo->prepare("
            SELECT 
                ar.attendance_date,
                COUNT(*) as total_records,
                SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) as present_records,
                ROUND((SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as percentage
            FROM attendance_records ar
            WHERE ar.institute_id = ?
            AND ar.attendance_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND CURDATE()
            GROUP BY ar.attendance_date
            ORDER BY ar.attendance_date
        ");
        $trend_stmt->execute([$selected_institute]);
        $attendance_trend = $trend_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $trend_labels = [];
        $trend_percentages = [];
        foreach ($attendance_trend as $day) {
            $trend_labels[] = date('M j', strtotime($day['attendance_date']));
            $trend_percentages[] = $day['percentage'];
        }
        
        $chart_data['trend_labels'] = $trend_labels;
        $chart_data['trend_percentages'] = $trend_percentages;
        
    }
    
} catch(PDOException $e) {
    $error = "Failed to load reports: " . $e->getMessage();
    $institutes = [];
    $reports_data = [];
    $overall_stats = [];
    $chart_data = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Reports - Student Attendance</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        max-width: 1400px;
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

    .btn-warning {
        background: var(--warning);
        color: #1f2937;
        box-shadow: var(--shadow-md);
    }

    .btn-warning:hover {
        background: #d97706;
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
        box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
    }

    select option {
        background: var(--bg-secondary);
        color: var(--text-primary);
        padding: 0.5rem;
    }

    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: var(--bg-secondary);
        padding: 1.5rem;
        border-radius: var(--border-radius);
        text-align: center;
        box-shadow: var(--shadow-lg);
        transition: var(--transition);
        border: 1px solid var(--glass-border);
        border-left: 4px solid var(--primary);
    }

    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow-xl);
    }

    .stat-number {
        font-size: 2rem;
        font-weight: 800;
        background: var(--bg-gradient);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin-bottom: 0.5rem;
    }

    .stat-label {
        color: var(--text-muted);
        margin-top: 0.5rem;
        font-weight: 500;
        font-size: 0.9rem;
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

    /* Alerts Section */
    .alerts-section {
        background: rgba(245, 158, 11, 0.1);
        border: 1px solid rgba(245, 158, 11, 0.3);
        border-radius: var(--border-radius);
        padding: 1.5rem;
        margin-bottom: 2rem;
    }

    .alert-title {
        color: var(--warning);
        font-weight: 600;
        margin-bottom: 1rem;
    }

    .alert-list {
        list-style: none;
    }

    .alert-item {
        padding: 0.75rem 0;
        border-bottom: 1px solid rgba(245, 158, 11, 0.2);
        color: var(--text-secondary);
    }

    .alert-item:last-child {
        border-bottom: none;
    }

    /* Students Table */
    .students-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 1rem;
        background: var(--bg-secondary);
        border-radius: var(--border-radius);
        overflow: hidden;
        box-shadow: var(--shadow-lg);
    }

    .students-table th, .students-table td {
        padding: 1rem;
        text-align: left;
        border-bottom: 1px solid var(--glass-border);
    }

    .students-table th {
        background: var(--bg-tertiary);
        font-weight: 600;
        color: var(--text-primary);
    }

    .students-table tr:hover {
        background: var(--bg-tertiary);
    }

    .students-table tr:last-child td {
        border-bottom: none;
    }

    /* Attendance Percentage Styles */
    .attendance-percentage {
        font-weight: bold;
    }

    .percentage-excellent {
        color: var(--success);
    }

    .percentage-good {
        color: #22d3ee;
    }

    .percentage-average {
        color: var(--warning);
    }

    .percentage-poor {
        color: #f97316;
    }

    .percentage-critical {
        color: var(--error);
    }

    /* Progress Bar */
    .progress-bar {
        width: 100px;
        height: 8px;
        background: var(--bg-tertiary);
        border-radius: 4px;
        overflow: hidden;
    }

    .progress-fill {
        height: 100%;
        transition: width 0.3s;
    }

    .progress-excellent {
        background: var(--success);
    }

    .progress-good {
        background: #22d3ee;
    }

    .progress-average {
        background: var(--warning);
    }

    .progress-poor {
        background: #f97316;
    }

    .progress-critical {
        background: var(--error);
    }

    /* Subject Stats */
    .subject-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .subject-stat-card {
        background: var(--bg-secondary);
        padding: 1.5rem;
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-lg);
        text-align: center;
        border: 1px solid var(--glass-border);
        transition: var(--transition);
    }

    .subject-stat-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-xl);
    }

    /* Export Section */
    .export-section {
        background: rgba(139, 92, 246, 0.1);
        padding: 1.5rem;
        border-radius: var(--border-radius);
        margin-top: 2rem;
        text-align: center;
        border: 1px solid rgba(139, 92, 246, 0.2);
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

    #whatsappModal > div {
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
    .stat-card,
    .chart-card,
    .subject-stat-card {
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

        .report-filters > div {
            grid-template-columns: 1fr;
        }

        .students-table {
            font-size: 0.875rem;
        }

        .students-table th, 
        .students-table td {
            padding: 0.75rem 0.5rem;
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

        .stats-grid {
            grid-template-columns: 1fr;
        }

        .subject-stats {
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
            font-size: 12px !important;
        }

        .card,
        .stat-card,
        .chart-card,
        .subject-stat-card,
        .students-table {
            background: white !important;
            color: black !important;
            border-color: #ccc !important;
        }

        .students-table th {
            background: #f8f9fa !important;
            color: black !important;
        }

        .charts-container {
            grid-template-columns: 1fr !important;
        }

        .chart-wrapper {
            height: 250px !important;
        }
    }
</style>
</head>
<body>
    <div class="header">
        <h1>👨‍🏫 Teacher Portal</h1>
        <ul class="nav-links">
            <li><a href="../dashboard/teacher-dashboard.php">Dashboard</a></li>
            <li><a href="../manage/institutes.php">Institutes</a></li>
            <li><a href="../manage/students.php">Students</a></li>
            <li><a href="../manage/subjects.php">Subjects</a></li>
            <li><a href="teacher-reports.php">Reports</a></li>
            <li><a href="../auth/logout.php" class="logout-btn">Logout (<?php echo htmlspecialchars($_SESSION['user_name']); ?>)</a></li>
        </ul>
    </div>
    
    <div class="container">
        <div class="card">
            <h2 class="page-title">Teacher Reports & Analytics</h2>
            <p>Comprehensive attendance analysis and student performance monitoring</p>
            
            <div class="report-filters">
                <form method="GET" action="" id="reportForm">
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                        <div class="filter-group">
                            <label for="institute_id">Select Institute</label>
                            <select id="institute_id" name="institute_id">
                                <option value="">Select Institute</option>
                                <?php foreach ($institutes as $institute): ?>
                                    <option value="<?php echo $institute['id']; ?>" 
                                        <?php echo $selected_institute == $institute['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($institute['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="date_range">Date Range</label>
                            <select id="date_range" name="date_range">
                                <option value="7" <?php echo $date_range == '7' ? 'selected' : ''; ?>>Last 7 days</option>
                                <option value="30" <?php echo $date_range == '30' ? 'selected' : ''; ?>>Last 30 days</option>
                                <option value="90" <?php echo $date_range == '90' ? 'selected' : ''; ?>>Last 90 days</option>
                                <option value="365" <?php echo $date_range == '365' ? 'selected' : ''; ?>>Last year</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="chart_type">Chart Type</label>
                            <select id="chart_type" name="chart_type">
                                <option value="bar" <?php echo $chart_type == 'bar' ? 'selected' : ''; ?>>Bar Chart</option>
                                <option value="line" <?php echo $chart_type == 'line' ? 'selected' : ''; ?>>Line Chart</option>
                                <option value="pie" <?php echo $chart_type == 'pie' ? 'selected' : ''; ?>>Pie Chart</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary" style="margin-top: 1rem;">Generate Report</button>
                </form>
            </div>
            
            <?php if ($selected_institute && !empty($reports_data)): ?>
                <!-- Overall Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $overall_stats['total_students']; ?></div>
                        <div class="stat-label">Total Students</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $overall_stats['total_subjects']; ?></div>
                        <div class="stat-label">Subjects</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $overall_stats['average_attendance']; ?>%</div>
                        <div class="stat-label">Average Attendance</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $chart_data['low_attendance_count']; ?></div>
                        <div class="stat-label">Low Attendance Alerts</div>
                    </div>
                </div>
                
                <!-- Low Attendance Alerts -->
                <?php if ($chart_data['low_attendance_count'] > 0): ?>
                <div class="alerts-section">
                    <h3 class="alert-title">⚠️ Low Attendance Alerts</h3>
                    <ul class="alert-list">
                        <?php foreach ($reports_data as $data): ?>
                            <?php if ($data['overall']['percentage'] < 70): ?>
                                <li class="alert-item">
                                    <strong><?php echo htmlspecialchars($data['student']['name']); ?></strong> 
                                    - <?php echo $data['overall']['percentage']; ?>% attendance
                                    (<?php echo $data['overall']['attended']; ?>/<?php echo $data['overall']['total']; ?> classes)
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <!-- Charts Section -->
                <div class="charts-container">
                    <div class="chart-card">
                        <h3 class="chart-title">Student Performance Overview</h3>
                        <div class="chart-wrapper">
                            <canvas id="studentChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="chart-card">
                        <h3 class="chart-title">Attendance Trend (Last 7 Days)</h3>
                        <div class="chart-wrapper">
                            <canvas id="trendChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Subject-wise Statistics -->
                <h3 style="margin-bottom: 1rem;">Subject-wise Average Attendance</h3>
                <div class="subject-stats">
                    <?php foreach ($chart_data['subject_stats'] as $subject_stat): 
                        $percentage = $subject_stat['average_percentage'];
                        $percentage_class = 'percentage-average';
                        if ($percentage >= 90) $percentage_class = 'percentage-excellent';
                        elseif ($percentage >= 80) $percentage_class = 'percentage-good';
                        elseif ($percentage >= 60) $percentage_class = 'percentage-average';
                        elseif ($percentage >= 40) $percentage_class = 'percentage-poor';
                        else $percentage_class = 'percentage-critical';
                    ?>
                        <div class="subject-stat-card">
                            <h4><?php echo htmlspecialchars($subject_stat['subject']['name']); ?></h4>
                            <div class="attendance-percentage <?php echo $percentage_class; ?>" style="font-size: 1.5rem; margin: 0.5rem 0;">
                                <?php echo $percentage; ?>%
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill <?php echo str_replace('percentage-', 'progress-', $percentage_class); ?>" 
                                     style="width: <?php echo min($percentage, 100); ?>%">
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Detailed Student Report -->
                <h3 style="margin-bottom: 1rem;">Detailed Student Attendance</h3>
                <div style="overflow-x: auto;">
                    <table class="students-table">
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Email</th>
                                <?php foreach ($subjects as $subject): ?>
                                    <th><?php echo htmlspecialchars($subject['name']); ?></th>
                                <?php endforeach; ?>
                                <th>Overall</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reports_data as $data): 
                                $overall_percentage = $data['overall']['percentage'];
                                $overall_class = 'percentage-average';
                                if ($overall_percentage >= 90) $overall_class = 'percentage-excellent';
                                elseif ($overall_percentage >= 80) $overall_class = 'percentage-good';
                                elseif ($overall_percentage >= 60) $overall_class = 'percentage-average';
                                elseif ($overall_percentage >= 40) $overall_class = 'percentage-poor';
                                else $overall_class = 'percentage-critical';
                            ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($data['student']['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($data['student']['email']); ?></td>
                                    <?php foreach ($data['attendance'] as $att): 
                                        $percentage = $att['attendance']['percentage'];
                                        $class = 'percentage-average';
                                        if ($percentage >= 90) $class = 'percentage-excellent';
                                        elseif ($percentage >= 80) $class = 'percentage-good';
                                        elseif ($percentage >= 60) $class = 'percentage-average';
                                        elseif ($percentage >= 40) $class = 'percentage-poor';
                                        else $class = 'percentage-critical';
                                    ?>
                                        <td>
                                            <span class="attendance-percentage <?php echo $class; ?>">
                                                <?php echo $percentage; ?>%
                                            </span>
                                            <br>
                                            <small style="color: #666;">
                                                (<?php echo $att['attendance']['attended']; ?>/<?php echo $att['attendance']['total']; ?>)
                                            </small>
                                        </td>
                                    <?php endforeach; ?>
                                    <td>
                                        <span class="attendance-percentage <?php echo $overall_class; ?>">
                                            <?php echo $overall_percentage; ?>%
                                        </span>
                                        <br>
                                        <small style="color: #666;">
                                            (<?php echo $data['overall']['attended']; ?>/<?php echo $data['overall']['total']; ?>)
                                        </small>
                                    </td>
                                    <td>
                                        <?php if ($overall_percentage >= 80): ?>
                                            <span style="color: #28a745;">✅ Excellent</span>
                                        <?php elseif ($overall_percentage >= 60): ?>
                                            <span style="color: #ffc107;">⚠️ Needs Improvement</span>
                                        <?php else: ?>
                                            <span style="color: #dc3545;">❌ Critical</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Export Section -->
                <div class="export-section">
                    <h3>Export & Share Reports</h3>
                    <p>Share comprehensive reports with parents or school administration</p>
                    <div class="export-buttons">
                        <button type="button" class="btn btn-whatsapp" onclick="shareToWhatsApp()">
                            📱 Share to WhatsApp
                        </button>
                        <button type="button" class="btn btn-primary" onclick="exportAsImage()">
                            📊 Export as Image
                        </button>
                        <button type="button" class="btn btn-warning" onclick="exportStudentList()">
                            📋 Export Student List
                        </button>
                        <button type="button" class="btn btn-primary" onclick="printReport()">
                            🖨️ Print Report
                        </button>
                    </div>
                </div>
                
            <?php elseif ($selected_institute && empty($reports_data)): ?>
                <p style="color: #666; text-align: center; padding: 2rem;">No students enrolled in this institute yet.</p>
            <?php else: ?>
                <p style="color: #666; text-align: center; padding: 2rem;">Please select an institute to view detailed reports.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- WhatsApp Sharing Modal -->
    <div id="whatsappModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
        <div style="background: white; padding: 2rem; border-radius: 10px; max-width: 500px; width: 90%;">
            <h3>Share via WhatsApp</h3>
            <p>Your teacher report has been prepared. Click the button below to open WhatsApp and share with parents or administration!</p>
            <div style="margin-top: 1rem;">
                <a id="whatsappLink" class="btn btn-whatsapp" style="width: 100%; justify-content: center;">
                    📱 Open WhatsApp
                </a>
                <button type="button" class="btn btn-secondary" onclick="closeWhatsAppModal()" style="width: 100%; margin-top: 0.5rem;">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    <script>
        // Chart initialization
        <?php if ($selected_institute && !empty($chart_data)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const chartType = '<?php echo $chart_type; ?>';
            
            // Student Performance Chart
            const studentCtx = document.getElementById('studentChart').getContext('2d');
            const studentChart = new Chart(studentCtx, {
                type: chartType,
                data: {
                    labels: <?php echo json_encode($chart_data['student_labels']); ?>,
                    datasets: [{
                        label: 'Attendance Percentage',
                        data: <?php echo json_encode($chart_data['student_percentages']); ?>,
                        backgroundColor: <?php echo json_encode($chart_data['student_colors']); ?>,
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
                                label: function(context) {
                                    return `${context.label}: ${context.raw}%`;
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
                        label: 'Institute Attendance (%)',
                        data: <?php echo json_encode($chart_data['trend_percentages']); ?>,
                        backgroundColor: 'rgba(102, 126, 234, 0.2)',
                        borderColor: '#667eea',
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
            // Generate comprehensive report text
            let summaryText = `👨‍🏫 Teacher Report - <?php echo htmlspecialchars($_SESSION['user_name']); ?>\n\n`;
            summaryText += `Institute: <?php echo $selected_institute ? htmlspecialchars($institute_name) : 'N/A'; ?>\n`;
            summaryText += `Report Period: Last <?php echo $date_range; ?> days\n\n`;
            
            summaryText += `📊 Overall Statistics:\n`;
            summaryText += `• Total Students: <?php echo $overall_stats['total_students']; ?>\n`;
            summaryText += `• Total Subjects: <?php echo $overall_stats['total_subjects']; ?>\n`;
            summaryText += `• Average Attendance: <?php echo $overall_stats['average_attendance']; ?>%\n`;
            summaryText += `• Low Attendance Alerts: <?php echo $chart_data['low_attendance_count']; ?>\n\n`;
            
            <?php if (!empty($chart_data['subject_stats'])): ?>
                summaryText += `📚 Subject-wise Averages:\n`;
                <?php foreach ($chart_data['subject_stats'] as $subject_stat): ?>
                    summaryText += `• <?php echo htmlspecialchars($subject_stat['subject']['name']); ?>: <?php echo $subject_stat['average_percentage']; ?>%\n`;
                <?php endforeach; ?>
                summaryText += `\n`;
            <?php endif; ?>
            
            summaryText += `⚠️ Key Insights:\n`;
            summaryText += `• <?php echo count(array_filter($reports_data, fn($data) => $data['overall']['percentage'] >= 80)); ?> students with excellent attendance (80%+)\n`;
            summaryText += `• <?php echo count(array_filter($reports_data, fn($data) => $data['overall']['percentage'] < 70)); ?> students need attention (<70%)\n\n`;
            
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
            // Using html2canvas to capture the report
            const reportCard = document.querySelector('.card');
            
            html2canvas(reportCard).then(canvas => {
                const link = document.createElement('a');
                link.download = 'teacher-report-<?php echo date('Y-m-d'); ?>.png';
                link.href = canvas.toDataURL();
                link.click();
            });
        }
        
        // Export Student List as CSV
        function exportStudentList() {
            let csvContent = "data:text/csv;charset=utf-8,";
            csvContent += "Student Name,Email,Overall Attendance,Status\n";
            
            <?php foreach ($reports_data as $data): ?>
                csvContent += "<?php echo htmlspecialchars($data['student']['name']); ?>,<?php echo htmlspecialchars($data['student']['email']); ?>,<?php echo $data['overall']['percentage']; ?>%,<?php echo $data['overall']['percentage'] >= 80 ? 'Excellent' : ($data['overall']['percentage'] >= 60 ? 'Needs Improvement' : 'Critical'); ?>\n";
            <?php endforeach; ?>
            
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "student-list-<?php echo date('Y-m-d'); ?>.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        // Print Report
        function printReport() {
            window.print();
        }
        
        // Auto-submit form when filters change
        document.getElementById('institute_id').addEventListener('change', function() {
            document.getElementById('reportForm').submit();
        });
        
        // Close modal when clicking outside
        document.getElementById('whatsappModal').addEventListener('click', function(e) {
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
            .header, .report-filters, .export-section, .nav-links {
                display: none !important;
            }
            .card {
                box-shadow: none !important;
                border: 1px solid #ccc !important;
            }
            body {
                background: white !important;
                font-size: 12px !important;
            }
            .charts-container {
                grid-template-columns: 1fr !important;
            }
            .chart-wrapper {
                height: 250px !important;
            }
        }
    </style>
</body>
</html>