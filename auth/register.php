<?php
// auth/register.php - Updated with role selection
require_once '../config.php';
redirectIfLoggedIn();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'] ?? 'student';
    
    // Validation
    if (empty($name) || empty($email) || empty($password)) {
        $error = 'All fields are required.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        try {
            $pdo = getDBConnection();
            
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() > 0) {
                $error = 'Email already registered.';
            } else {
                // Hash password and create user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $email, $hashed_password, $role]);
                
                $success = 'Registration successful! You can now login.';
                // Clear form
                $name = $email = '';
            }
        } catch(PDOException $e) {
            $error = 'Registration failed: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Student Attendance</title>
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
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        background: var(--bg-gradient);
        position: relative;
        overflow: hidden;
    }

    body::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: 
            radial-gradient(circle at 20% 80%, rgba(139, 92, 246, 0.1) 0%, transparent 50%),
            radial-gradient(circle at 80% 20%, rgba(6, 182, 212, 0.1) 0%, transparent 50%),
            radial-gradient(circle at 40% 40%, rgba(16, 185, 129, 0.05) 0%, transparent 50%);
        z-index: -1;
    }

    .container {
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        padding: 2.5rem;
        border-radius: var(--border-radius-lg);
        box-shadow: var(--shadow-xl);
        width: 100%;
        max-width: 450px;
        border: 1px solid var(--glass-border);
        position: relative;
        overflow: hidden;
    }

    .container::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--bg-gradient);
    }

    h1 {
        text-align: center;
        margin-bottom: 1.5rem;
        color: var(--text-primary);
        font-size: 2rem;
        font-weight: 700;
    }

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
    input[type="password"] {
        width: 100%;
        padding: 0.75rem;
        background: var(--bg-tertiary);
        border: 2px solid var(--glass-border);
        border-radius: var(--border-radius);
        font-size: 1rem;
        color: var(--text-primary);
        transition: var(--transition);
    }

    input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
    }

    input::placeholder {
        color: var(--text-muted);
    }

    .btn {
        width: 100%;
        padding: 0.75rem;
        background: var(--bg-gradient);
        color: white;
        border: none;
        border-radius: var(--border-radius);
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
        box-shadow: var(--shadow-md);
    }

    .btn:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }

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

    .login-link {
        text-align: center;
        margin-top: 1.5rem;
        color: var(--text-secondary);
    }

    .login-link a {
        color: var(--primary);
        text-decoration: none;
        font-weight: 600;
        transition: var(--transition);
    }

    .login-link a:hover {
        color: var(--text-primary);
        text-decoration: underline;
    }

    /* Role Selection */
    .role-selection {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .role-option {
        border: 2px solid var(--glass-border);
        border-radius: var(--border-radius);
        padding: 1.25rem;
        text-align: center;
        cursor: pointer;
        transition: var(--transition);
        background: var(--bg-tertiary);
        position: relative;
        overflow: hidden;
    }

    .role-option:hover {
        border-color: var(--primary);
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    .role-option.selected {
        border-color: var(--primary);
        background: rgba(139, 92, 246, 0.1);
        box-shadow: var(--shadow-md);
    }

    .role-option::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 100%;
        background: var(--bg-gradient);
        opacity: 0;
        transition: var(--transition);
    }

    .role-option.selected::before {
        opacity: 0.05;
    }

    .role-icon {
        font-size: 2rem;
        margin-bottom: 0.5rem;
        position: relative;
        z-index: 1;
    }

    .role-description {
        font-size: 0.8rem;
        color: var(--text-muted);
        margin-top: 0.25rem;
        position: relative;
        z-index: 1;
    }

    /* Password strength indicator */
    .password-strength {
        height: 4px;
        background: var(--bg-tertiary);
        border-radius: 2px;
        margin-top: 0.5rem;
        overflow: hidden;
    }

    .password-strength-fill {
        height: 100%;
        width: 0%;
        transition: var(--transition);
        border-radius: 2px;
    }

    .strength-weak { background: var(--error); width: 33%; }
    .strength-medium { background: var(--warning); width: 66%; }
    .strength-strong { background: var(--success); width: 100%; }

    /* Loading animation */
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .container {
        animation: fadeInUp 0.6s ease-out;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .container {
            padding: 2rem;
            margin: 1rem;
        }

        h1 {
            font-size: 1.75rem;
        }

        .role-selection {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 480px) {
        .container {
            padding: 1.5rem;
        }

        h1 {
            font-size: 1.5rem;
        }

        .role-option {
            padding: 1rem;
        }

        .role-icon {
            font-size: 1.75rem;
        }
    }

    /* Print Styles */
    @media print {
        body {
            background: white !important;
            color: black !important;
        }

        .container {
            background: white !important;
            color: black !important;
            box-shadow: none !important;
            border: 1px solid #ccc !important;
        }

        .btn {
            background: #666 !important;
            color: white !important;
        }
    }
</style>

<script>
    // Role selection
    document.querySelectorAll('.role-option').forEach(option => {
        option.addEventListener('click', function() {
            document.querySelectorAll('.role-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            this.classList.add('selected');
            const radio = this.querySelector('input[type="radio"]');
            radio.checked = true;
        });
    });

    // Password strength indicator
    const passwordInput = document.getElementById('password');
    const strengthBar = document.createElement('div');
    strengthBar.className = 'password-strength';
    const strengthFill = document.createElement('div');
    strengthFill.className = 'password-strength-fill';
    strengthBar.appendChild(strengthFill);
    
    if (passwordInput) {
        passwordInput.parentNode.appendChild(strengthBar);
        
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            
            if (password.length >= 6) strength++;
            if (password.length >= 8) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            strengthFill.className = 'password-strength-fill';
            if (strength <= 2) {
                strengthFill.classList.add('strength-weak');
            } else if (strength <= 4) {
                strengthFill.classList.add('strength-medium');
            } else {
                strengthFill.classList.add('strength-strong');
            }
        });
    }

    // Password confirmation
    document.querySelector('form').addEventListener('submit', function(e) {
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        
        if (password !== confirmPassword) {
            e.preventDefault();
            alert('Passwords do not match!');
            return false;
        }
    });
</script>
</head>
<body>
    <div class="container">
        <h1>Create Account</h1>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="name">Full Name:</label>
                <input type="text" id="name" name="name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label>Account Type:</label>
                <div class="role-selection">
                    <div class="role-option <?php echo (isset($_POST['role']) && $_POST['role'] === 'student') || !isset($_POST['role']) ? 'selected' : ''; ?>" data-role="student">
                        <div class="role-icon">🎓</div>
                        <div>Student</div>
                        <div class="role-description">Mark attendance only</div>
                        <input type="radio" name="role" value="student" <?php echo (isset($_POST['role']) && $_POST['role'] === 'student') || !isset($_POST['role']) ? 'checked' : ''; ?> hidden>
                    </div>
                    <div class="role-option <?php echo (isset($_POST['role']) && $_POST['role'] === 'teacher') ? 'selected' : ''; ?>" data-role="teacher">
                        <div class="role-icon">👨‍🏫</div>
                        <div>Teacher</div>
                        <div class="role-description">Create institutes & subjects</div>
                        <input type="radio" name="role" value="teacher" <?php echo (isset($_POST['role']) && $_POST['role'] === 'teacher') ? 'checked' : ''; ?> hidden>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required minlength="6">
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            
            <button type="submit" class="btn">Register</button>
        </form>
        
        <div class="login-link">
            Already have an account? <a href="login.php">Login here</a>
        </div>
    </div>

    <script>
        // Role selection
        document.querySelectorAll('.role-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.role-option').forEach(opt => {
                    opt.classList.remove('selected');
                });
                this.classList.add('selected');
                const radio = this.querySelector('input[type="radio"]');
                radio.checked = true;
            });
        });

        // Password confirmation
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
        });
    </script>
</body>
</html>