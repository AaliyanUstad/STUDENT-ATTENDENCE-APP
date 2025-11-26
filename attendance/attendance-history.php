<?php
require_once '../config.php';
redirectIfNotLoggedIn();

try {
    $pdo = getDBConnection();

    $query = "SELECT ar.*, i.name as institute_name, s.name as subject_name, s.difficulty
              FROM attendance_records ar
              JOIN institutes i ON ar.institute_id = i.id
              JOIN subjects s ON ar.subject_id = s.id
              WHERE ar.user_id = ?
              ORDER BY ar.attendance_date DESC, ar.created_at DESC
              LIMIT 50";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $attendance_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Failed to load attendance history: " . $e->getMessage();
    $attendance_records = [];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance History - Student Attendance</title>
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

        /* Attendance List */
        .attendance-list {
            margin-top: 2rem;
        }

        .attendance-item {
            display: flex;
            align-items: center;
            padding: 1.5rem;
            border-bottom: 1px solid var(--glass-border);
            gap: 1.5rem;
            transition: var(--transition);
            background: var(--bg-secondary);
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
            border: 1px solid var(--glass-border);
        }

        .attendance-item:hover {
            background: var(--bg-tertiary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .attendance-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .selfie-image {
            width: 80px;
            height: 80px;
            border-radius: var(--border-radius);
            object-fit: cover;
            border: 2px solid var(--glass-border);
            box-shadow: var(--shadow-md);
            transition: var(--transition);
        }

        .attendance-item:hover .selfie-image {
            border-color: var(--primary);
            transform: scale(1.05);
        }

        .attendance-details {
            flex: 1;
        }

        .attendance-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.75rem;
        }

        .institute-name {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 1.1rem;
            margin-bottom: 0.25rem;
        }

        .subject-name {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

        .attendance-date {
            color: var(--text-muted);
            font-size: 0.9rem;
            text-align: right;
            min-width: 100px;
        }

        .attendance-meta {
            display: flex;
            gap: 1rem;
            margin-top: 0.75rem;
            flex-wrap: wrap;
            align-items: center;
        }

        /* Difficulty Badges */
        .difficulty-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            border: 1px solid;
        }

        .difficulty-easy {
            background: rgba(34, 197, 94, 0.1);
            color: var(--success);
            border-color: rgba(34, 197, 94, 0.3);
        }

        .difficulty-medium {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
            border-color: rgba(245, 158, 11, 0.3);
        }

        .difficulty-hard {
            background: rgba(239, 68, 68, 0.1);
            color: var(--error);
            border-color: rgba(239, 68, 68, 0.3);
        }

        /* Status Styles */
        .status-present {
            color: var(--success);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .notes-indicator {
            color: var(--warning);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: help;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--text-muted);
        }

        .empty-state h3 {
            margin-bottom: 1rem;
            color: var(--text-primary);
        }

        .empty-state p {
            margin-bottom: 1.5rem;
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
        .attendance-item {
            animation: fadeInUp 0.6s ease-out;
        }

        .attendance-item:nth-child(even) {
            animation-delay: 0.1s;
        }

        .attendance-item:nth-child(odd) {
            animation-delay: 0.2s;
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

            .attendance-item {
                flex-direction: column;
                text-align: center;
                padding: 1.25rem;
            }

            .selfie-image {
                width: 100px;
                height: 100px;
            }

            .attendance-header {
                flex-direction: column;
                align-items: center;
                gap: 0.5rem;
            }

            .attendance-date {
                text-align: center;
            }

            .attendance-meta {
                justify-content: center;
            }

            .btn {
                width: 100%;
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

            .attendance-meta {
                flex-direction: column;
                gap: 0.5rem;
            }

            .selfie-image {
                width: 80px;
                height: 80px;
            }
        }

        /* Print Styles */
        @media print {

            .header,
            .btn,
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
            .attendance-item {
                background: white !important;
                color: black !important;
                border-color: #ccc !important;
            }

            .selfie-image {
                border-color: #ccc !important;
            }
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>🎓 StudentAttend</h1>
        <ul class="nav-links">
            <li><a href="../dashboard/dashboard.php">Dashboard</a></li>
            <li><a href="mark-attendance.php">Mark Attendance</a></li>
            <li><a href="attendance-history.php">Attendance History</a></li>
            <li><a href="../auth/logout.php">Logout (<?php echo htmlspecialchars($_SESSION['user_name']); ?>)</a></li>
        </ul>
    </div>

    <div class="container">
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <h2 class="page-title">Attendance History</h2>
                <a href="mark-attendance.php" class="btn btn-primary">Mark New Attendance</a>
            </div>

            <?php if (empty($attendance_records)): ?>
                <div class="empty-state">
                    <h3>No Attendance Records Yet</h3>
                    <p>You haven't marked any attendance yet. Start by marking your first attendance!</p>
                    <a href="mark-attendance.php" class="btn btn-primary" style="margin-top: 1rem;">Mark Your First
                        Attendance</a>
                </div>
            <?php else: ?>
                <div class="attendance-list">
                    <?php foreach ($attendance_records as $record): ?>
                        <div class="attendance-item">
                            <img src="../<?php echo htmlspecialchars($record['selfie_image_path']); ?>" alt="Attendance selfie"
                                class="selfie-image"
                                onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAiIGhlaWdodD0iODAiIHZpZXdCb3g9IjAgMCA4MCA4MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjgwIiBoZWlnaHQ9IjgwIiBmaWxsPSIjRjVGNUY1Ii8+CjxwYXRoIGQ9Ik00MCA0MEM0My4zMTM3IDQwIDQ2IDM3LjMxMzcgNDYgMzRDNDYgMzAuNjg2MyA0My4zMTM3IDI4IDQwIDI4QzM2LjY4NjMgMjggMzQgMzAuNjg2MyAzNCAzNEMzNCAzNy4zMTM3IDM2LjY4NjMgNDAgNDAgNDBaTTQwIDQ0QzMyLjI2IDQ0IDI2IDUwLjI2IDI2IDU4SDU0QzU0IDUwLjI2IDQ3Ljc0IDQ0IDQwIDQ0WiIgZmlsbD0iIzk5OTk5OSIvPgo8L3N2Zz4K'">
                            <div class="attendance-details">
                                <div class="attendance-header">
                                    <div>
                                        <div class="institute-name"><?php echo htmlspecialchars($record['institute_name']); ?>
                                        </div>
                                        <div class="subject-name"><?php echo htmlspecialchars($record['subject_name']); ?></div>
                                    </div>
                                    <div class="attendance-date">
                                        <?php echo date('M j, Y', strtotime($record['attendance_date'])); ?>
                                    </div>
                                </div>
                                <div class="attendance-meta">
                                    <span class="status-present">✅ Present</span>
                                    <span class="difficulty-badge difficulty-<?php echo $record['difficulty']; ?>">
                                        <?php echo ucfirst($record['difficulty']); ?>
                                    </span>
                                    <?php if ($record['notes']): ?>
                                        <span title="<?php echo htmlspecialchars($record['notes']); ?>">📝 Has notes</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>