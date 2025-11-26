<?php
require_once '../config.php';
redirectIfNotLoggedIn();

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM institutes WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $institutes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Failed to load institutes: " . $e->getMessage();
    $institutes = [];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Institutes - Student Attendance</title>
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

        .btn-secondary {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border: 1px solid var(--glass-border);
        }

        .btn-secondary:hover {
            background: var(--bg-secondary);
            transform: translateY(-2px);
        }

        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        /* Institute Grid */
        .institute-grid {
            display: grid;
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .institute-card {
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

        .institute-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-xl);
            border-color: var(--primary);
        }

        .institute-card::before {
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

        .institute-card:hover::before {
            opacity: 0.05;
        }

        .institute-name {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .institute-meta {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-bottom: 1rem;
            line-height: 1.5;
        }

        .institute-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        /* Status Indicators */
        .status-active {
            color: var(--success);
            font-weight: 600;
        }

        .status-inactive {
            color: var(--error);
            font-weight: 600;
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

            .institute-actions {
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

            .institute-card {
                padding: 1rem;
            }

            .institute-name {
                font-size: 1.1rem;
            }
        }

        /* Print Styles */
        @media print {

            .header,
            .institute-actions,
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
            .institute-card {
                background: white !important;
                color: black !important;
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
            <li><a href="institutes.php">My Institutes</a></li>
            <li><a href="add-institute.php">Add Institute</a></li>
            <li><a href="../auth/logout.php">Logout (<?php echo htmlspecialchars($_SESSION['user_name']); ?>)</a></li>
        </ul>
    </div>

    <div class="container">
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <h2 class="page-title">My Institutes</h2>
                <a href="add-institute.php" class="btn btn-primary">+ Add New Institute</a>
            </div>

            <?php if (empty($institutes)): ?>
                <div class="empty-state">
                    <h3>No Institutes Yet</h3>
                    <p>You haven't created any institutes. Add your first institute to start tracking attendance.</p>
                    <a href="add-institute.php" class="btn btn-primary" style="margin-top: 1rem;">Create Your First
                        Institute</a>
                </div>
            <?php else: ?>
                <div class="institute-grid">
                    <?php foreach ($institutes as $institute): ?>
                        <div class="institute-card">
                            <div class="institute-name"><?php echo htmlspecialchars($institute['name']); ?></div>
                            <div class="institute-meta">
                                Created: <?php echo date('M j, Y', strtotime($institute['created_at'])); ?>
                                <?php if ($institute['contact_email']): ?>
                                    • <?php echo htmlspecialchars($institute['contact_email']); ?>
                                <?php endif; ?>
                                <?php if ($institute['is_active']): ?>
                                    • <span style="color: #28a745;">Active</span>
                                <?php else: ?>
                                    • <span style="color: #dc3545;">Inactive</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($institute['description']): ?>
                                <p><?php echo htmlspecialchars($institute['description']); ?></p>
                            <?php endif; ?>
                            <div class="institute-actions">
                                <a href="edit-institute.php?id=<?php echo $institute['id']; ?>"
                                    class="btn btn-small btn-primary">Edit</a>
                                <a href="subjects.php?institute_id=<?php echo $institute['id']; ?>"
                                    class="btn btn-small btn-secondary">Manage Subjects</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- In manage/institutes.php - Add to institute cards -->
        <div class="institute-actions">
            <a href="subjects.php?institute_id=<?php echo $institute['id']; ?>" class="btn btn-small btn-primary">View
                Subjects</a>
            <a href="add-subject.php?institute_id=<?php echo $institute['id']; ?>"
                class="btn btn-small btn-secondary">Add Subject</a>
        </div>
    </div>
</body>

</html>