<?php
require_once '../config.php';
redirectIfNotLoggedIn();
redirectIfNotTeacher();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $address = trim($_POST['address']);
    $contact_email = trim($_POST['contact_email']);
    $contact_phone = trim($_POST['contact_phone']);
    
    if (empty($name)) {
        $error = 'Institute name is required.';
    } else {
        try {
            $pdo = getDBConnection();
            
            $stmt = $pdo->prepare("SELECT id FROM institutes WHERE user_id = ? AND name = ?");
            $stmt->execute([$_SESSION['user_id'], $name]);
            
            if ($stmt->rowCount() > 0) {
                $error = 'You already have an institute with this name.';
            } else {
                // Insert new institute
                $stmt = $pdo->prepare("INSERT INTO institutes (user_id, name, description, address, contact_email, contact_phone) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_SESSION['user_id'],
                    $name,
                    $description,
                    $address,
                    $contact_email,
                    $contact_phone
                ]);
                
                $institute_id = $pdo->lastInsertId();
                $success = 'Institute "' . htmlspecialchars($name) . '" has been created successfully!';
                
                $name = $description = $address = $contact_email = $contact_phone = '';
            }
        } catch(PDOException $e) {
            $error = 'Failed to create institute: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Institute - Student Attendance</title>
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
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .logout-btn:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
        background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
    }

    .teacher-badge {
        background: var(--bg-gradient);
        color: white;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        margin-left: 0.5rem;
    }

    .container {
        max-width: 800px;
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

    input[type="text"],
    input[type="email"],
    input[type="tel"],
    textarea {
        width: 100%;
        padding: 0.75rem;
        background: var(--bg-tertiary);
        border: 2px solid var(--glass-border);
        border-radius: var(--border-radius);
        font-size: 1rem;
        color: var(--text-primary);
        transition: var(--transition);
        font-family: inherit;
    }

    input:focus,
    textarea:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
    }

    textarea {
        resize: vertical;
        min-height: 100px;
    }

    input::placeholder,
    textarea::placeholder {
        color: var(--text-muted);
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

    .success .btn {
        margin-top: 0.5rem;
        margin-right: 0.5rem;
    }

    /* Required Field Indicator */
    .required {
        color: var(--error);
    }

    /* Optional Field Indicator */
    .optional {
        color: var(--text-muted);
        font-weight: normal;
    }

    /* Character Count */
    .character-count {
        text-align: right;
        font-size: 0.875rem;
        color: var(--text-muted);
        margin-top: 0.25rem;
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

        .form-actions {
            flex-direction: column;
        }

        .btn {
            width: 100%;
        }

        .logout-btn {
            justify-content: center;
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

        .teacher-badge {
            margin-left: 0.25rem;
            font-size: 0.7rem;
            padding: 0.2rem 0.5rem;
        }
    }

    /* Print Styles */
    @media print {
        .header,
        .form-actions,
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

        .card {
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
            <li><a href="institutes.php">My Institutes</a></li>
            <li><a href="add-institute.php">Add Institute</a></li>
            <li><a href="../auth/logout.php" class="logout-btn">Logout (<?php echo htmlspecialchars($_SESSION['user_name']); ?>) <span class="teacher-badge">TEACHER</span></a></li>
        </ul>
    </div>
    
    <div class="container">
        <div class="card">
            <h2 class="page-title">Add New Institute</h2>
            <p class="page-subtitle">Create a new educational institute to start managing students and subjects</p>
            
            <?php if ($error): ?>
                <div class="error">
                    <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success">
                    <strong>Success!</strong> <?php echo $success; ?>
                    <div style="margin-top: 0.5rem;">
                        <a href="institutes.php" class="btn btn-primary">View All Institutes</a>
                        <a href="add-institute.php" class="btn btn-secondary">Add Another Institute</a>
                    </div>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="name">Institute Name <span style="color: #c33;">*</span></label>
                    <input type="text" id="name" name="name" 
                           value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" 
                           required maxlength="100" placeholder="e.g., ABC School, Math Excellence Center">
                </div>
                
                <div class="form-group">
                    <label for="description">Description <span style="color: #666; font-weight: normal;">(Optional)</span></label>
                    <textarea id="description" name="description" 
                              maxlength="500" 
                              placeholder="Brief description of the institute..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="address">Address <span style="color: #666; font-weight: normal;">(Optional)</span></label>
                    <textarea id="address" name="address" 
                              maxlength="255" 
                              placeholder="Full address of the institute..."><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="contact_email">Contact Email <span style="color: #666; font-weight: normal;">(Optional)</span></label>
                    <input type="email" id="contact_email" name="contact_email" 
                           value="<?php echo isset($_POST['contact_email']) ? htmlspecialchars($_POST['contact_email']) : ''; ?>" 
                           maxlength="150" placeholder="institute@example.com">
                </div>
                
                <div class="form-group">
                    <label for="contact_phone">Contact Phone <span style="color: #666; font-weight: normal;">(Optional)</span></label>
                    <input type="tel" id="contact_phone" name="contact_phone" 
                           value="<?php echo isset($_POST['contact_phone']) ? htmlspecialchars($_POST['contact_phone']) : ''; ?>" 
                           maxlength="20" placeholder="+1234567890">
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Create Institute</button>
                    <a href="institutes.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>