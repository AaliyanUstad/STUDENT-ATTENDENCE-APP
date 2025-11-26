<?php
require_once '../config.php';
redirectIfNotLoggedIn();

$error = '';
$success = '';

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT id, name FROM institutes WHERE user_id = ? AND is_active = 1 ORDER BY name");
    $stmt->execute([$_SESSION['user_id']]);
    $institutes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error = "Failed to load institutes: " . $e->getMessage();
    $institutes = [];
}

$selected_institute_id = $_GET['institute_id'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $institute_id = $_POST['institute_id'];
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $difficulty = $_POST['difficulty'];
    $color_code = $_POST['color_code'] ?? '#3498db';
    
    if (empty($institute_id)) {
        $error = 'Please select an institute.';
    } elseif (empty($name)) {
        $error = 'Subject name is required.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id FROM institutes WHERE id = ? AND user_id = ?");
            $stmt->execute([$institute_id, $_SESSION['user_id']]);
            
            if ($stmt->rowCount() === 0) {
                $error = 'Invalid institute selected.';
            } else {
                $stmt = $pdo->prepare("SELECT id FROM subjects WHERE institute_id = ? AND name = ?");
                $stmt->execute([$institute_id, $name]);
                
                if ($stmt->rowCount() > 0) {
                    $error = 'A subject with this name already exists in the selected institute.';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO subjects (institute_id, name, description, difficulty, color_code) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $institute_id,
                        $name,
                        $description,
                        $difficulty,
                        $color_code
                    ]);
                    
                    $subject_id = $pdo->lastInsertId();
                    $success = 'Subject "' . htmlspecialchars($name) . '" has been created successfully!';
                    
                    $name = $description = '';
                    $selected_institute_id = $institute_id;
                }
            }
        } catch(PDOException $e) {
            $error = 'Failed to create subject: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Subject - Student Attendance</title>
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
    input[type="color"],
    select,
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
    select:focus,
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

    /* Optional Label */
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

    /* Difficulty Options */
    .difficulty-options {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
        margin-top: 0.5rem;
    }

    .difficulty-option {
        border: 2px solid var(--glass-border);
        border-radius: var(--border-radius);
        padding: 1rem;
        text-align: center;
        cursor: pointer;
        transition: var(--transition);
        background: var(--bg-tertiary);
    }

    .difficulty-option:hover {
        border-color: var(--primary);
        transform: translateY(-2px);
    }

    .difficulty-option.selected {
        border-color: var(--primary);
        background: rgba(139, 92, 246, 0.1);
    }

    .difficulty-easy.selected {
        border-color: var(--success);
        background: rgba(34, 197, 94, 0.1);
    }

    .difficulty-medium.selected {
        border-color: var(--warning);
        background: rgba(245, 158, 11, 0.1);
    }

    .difficulty-hard.selected {
        border-color: var(--error);
        background: rgba(239, 68, 68, 0.1);
    }

    .difficulty-icon {
        font-size: 1.5rem;
        margin-bottom: 0.5rem;
    }

    /* Color Picker */
    .color-preview {
        width: 30px;
        height: 30px;
        border-radius: 5px;
        display: inline-block;
        margin-left: 0.5rem;
        vertical-align: middle;
        border: 2px solid var(--glass-border);
    }

    .color-options {
        display: flex;
        gap: 0.5rem;
        margin-top: 0.5rem;
        flex-wrap: wrap;
    }

    .color-option {
        width: 30px;
        height: 30px;
        border-radius: 5px;
        cursor: pointer;
        border: 2px solid transparent;
        transition: var(--transition);
    }

    .color-option:hover {
        transform: scale(1.1);
    }

    .color-option.selected {
        border-color: var(--text-primary);
        transform: scale(1.1);
    }

    /* Color Input Wrapper */
    .color-input-wrapper {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 0.5rem;
    }

    input[type="color"] {
        width: 60px;
        height: 40px;
        padding: 0;
        border: 2px solid var(--glass-border);
        border-radius: var(--border-radius);
        cursor: pointer;
    }

    input[type="color"]::-webkit-color-swatch-wrapper {
        padding: 0;
    }

    input[type="color"]::-webkit-color-swatch {
        border: none;
        border-radius: 4px;
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

        .difficulty-options {
            grid-template-columns: 1fr;
        }

        .color-input-wrapper {
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

        .color-options {
            justify-content: center;
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
    <!-- Header -->
    <div class="header">
        <h1>🎓 StudentAttend</h1>
        <ul class="nav-links">
            <li><a href="../dashboard/dashboard.php">Dashboard</a></li>
            <li><a href="institutes.php">Institutes</a></li>
            <li><a href="subjects.php">Subjects</a></li>
            <li><a href="add-subject.php">Add Subject</a></li>
            <li><a href="../auth/logout.php">Logout (<?php echo htmlspecialchars($_SESSION['user_name']); ?>)</a></li>
        </ul>
    </div>
    
    <div class="container">
        <div class="card">
            <h2 class="page-title">Add New Subject</h2>
            <p class="page-subtitle">Create a new subject for one of your institutes</p>
            
            <?php if ($error): ?>
                <div class="error">
                    <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success">
                    <strong>Success!</strong> <?php echo $success; ?>
                    <div style="margin-top: 0.5rem;">
                        <a href="subjects.php?institute_id=<?php echo $selected_institute_id; ?>" class="btn btn-primary">View All Subjects</a>
                        <a href="add-subject.php?institute_id=<?php echo $selected_institute_id; ?>" class="btn btn-secondary">Add Another Subject</a>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (empty($institutes)): ?>
                <div class="error">
                    <strong>No Active Institutes Found</strong><br>
                    You need to create an institute before you can add subjects.
                    <div style="margin-top: 0.5rem;">
                        <a href="add-institute.php" class="btn btn-primary">Create Your First Institute</a>
                    </div>
                </div>
            <?php else: ?>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="institute_id">Institute <span style="color: #c33;">*</span></label>
                        <select id="institute_id" name="institute_id" required>
                            <option value="">Select an Institute</option>
                            <?php foreach ($institutes as $institute): ?>
                                <option value="<?php echo $institute['id']; ?>" 
                                    <?php echo ($selected_institute_id == $institute['id'] || (isset($_POST['institute_id']) && $_POST['institute_id'] == $institute['id'])) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($institute['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="name">Subject Name <span style="color: #c33;">*</span></label>
                        <input type="text" id="name" name="name" 
                               value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" 
                               required maxlength="100" placeholder="e.g., Mathematics, Physics, English Literature">
                        <div class="character-count"><span id="name-count">0</span>/100 characters</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description <span class="optional">(Optional)</span></label>
                        <textarea id="description" name="description" 
                                  maxlength="500" 
                                  placeholder="Brief description of the subject..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                        <div class="character-count"><span id="description-count">0</span>/500 characters</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Difficulty Level <span style="color: #c33;">*</span></label>
                        <div class="difficulty-options">
                            <div class="difficulty-option difficulty-easy <?php echo (isset($_POST['difficulty']) && $_POST['difficulty'] === 'easy') ? 'selected' : ''; ?>" data-value="easy">
                                <div class="difficulty-icon">🟢</div>
                                <div>Easy</div>
                                <input type="radio" name="difficulty" value="easy" <?php echo (isset($_POST['difficulty']) && $_POST['difficulty'] === 'easy') ? 'checked' : 'checked'; ?> hidden>
                            </div>
                            <div class="difficulty-option difficulty-medium <?php echo (isset($_POST['difficulty']) && $_POST['difficulty'] === 'medium') ? 'selected' : ''; ?>" data-value="medium">
                                <div class="difficulty-icon">🟡</div>
                                <div>Medium</div>
                                <input type="radio" name="difficulty" value="medium" <?php echo (isset($_POST['difficulty']) && $_POST['difficulty'] === 'medium') ? 'checked' : ''; ?> hidden>
                            </div>
                            <div class="difficulty-option difficulty-hard <?php echo (isset($_POST['difficulty']) && $_POST['difficulty'] === 'hard') ? 'selected' : ''; ?>" data-value="hard">
                                <div class="difficulty-icon">🔴</div>
                                <div>Hard</div>
                                <input type="radio" name="difficulty" value="hard" <?php echo (isset($_POST['difficulty']) && $_POST['difficulty'] === 'hard') ? 'checked' : ''; ?> hidden>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="color_code">Color <span class="optional">(Optional)</span></label>
                        <div style="display: flex; align-items: center;">
                            <input type="color" id="color_code" name="color_code" value="<?php echo isset($_POST['color_code']) ? htmlspecialchars($_POST['color_code']) : '#3498db'; ?>" style="width: 60px; height: 40px; margin-right: 1rem;">
                            <span>Selected Color: <span class="color-preview" style="background: <?php echo isset($_POST['color_code']) ? htmlspecialchars($_POST['color_code']) : '#3498db'; ?>;"></span></span>
                        </div>
                        
                        <div class="color-options">
                            <?php
                            $default_colors = [
                                '#3498db', '#e74c3c', '#2ecc71', '#f39c12', 
                                '#9b59b6', '#1abc9c', '#34495e', '#e67e22',
                                '#27ae60', '#2980b9', '#8e44ad', '#c0392b'
                            ];
                            foreach ($default_colors as $color):
                            ?>
                                <div class="color-option <?php echo (isset($_POST['color_code']) && $_POST['color_code'] === $color) ? 'selected' : ''; ?>" 
                                     style="background: <?php echo $color; ?>;" 
                                     data-color="<?php echo $color; ?>"></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Create Subject</button>
                        <a href="subjects.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function setupCharacterCounter(elementId, counterId, maxLength) {
            const element = document.getElementById(elementId);
            const counter = document.getElementById(counterId);
            
            if (element && counter) {
                counter.textContent = element.value.length;
                
                element.addEventListener('input', function() {
                    counter.textContent = this.value.length;
                    
                    if (this.value.length > maxLength * 0.9) {
                        counter.style.color = '#c33';
                    } else {
                        counter.style.color = '#666';
                    }
                });
            }
        }
        
        setupCharacterCounter('name', 'name-count', 100);
        setupCharacterCounter('description', 'description-count', 500);
        
        document.querySelectorAll('.difficulty-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.difficulty-option').forEach(opt => {
                    opt.classList.remove('selected');
                });
                
                this.classList.add('selected');
                
                const radio = this.querySelector('input[type="radio"]');
                radio.checked = true;
            });
        });
        
        document.querySelectorAll('.color-option').forEach(option => {
            option.addEventListener('click', function() {
                const color = this.getAttribute('data-color');
                document.getElementById('color_code').value = color;
                updateColorPreview();
                
                document.querySelectorAll('.color-option').forEach(opt => {
                    opt.classList.remove('selected');
                });
                this.classList.add('selected');
            });
        });
        
        document.getElementById('color_code').addEventListener('input', updateColorPreview);
        
        function updateColorPreview() {
            const color = document.getElementById('color_code').value;
            document.querySelector('.color-preview').style.background = color;
        }
        
        document.querySelector('form').addEventListener('submit', function(e) {
            const instituteId = document.getElementById('institute_id').value;
            const name = document.getElementById('name').value.trim();
            
            if (!instituteId) {
                e.preventDefault();
                alert('Please select an institute.');
                document.getElementById('institute_id').focus();
                return false;
            }
            
            if (!name) {
                e.preventDefault();
                alert('Please enter a subject name.');
                document.getElementById('name').focus();
                return false;
            }
        });
        
        updateColorPreview();
    </script>
</body>
</html>