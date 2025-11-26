<?php
require_once '../config.php';
redirectIfLoggedIn();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        try {
            $pdo = getDBConnection();

            $stmt = $pdo->prepare("SELECT id, name, email, password_hash, role FROM users WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];

                header("Location: ../dashboard/dashboard.php");
                exit();
            } else {
                $error = 'Invalid email or password.';
            }
        } catch (PDOException $e) {
            $error = 'Login failed: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Student Attendance</title>
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

        .register-link {
            text-align: center;
            margin-top: 1.5rem;
            color: var(--text-secondary);
        }

        .register-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
        }

        .register-link a:hover {
            color: var(--text-primary);
            text-decoration: underline;
        }

        /* Test Accounts Section */
        .test-accounts {
            background: rgba(139, 92, 246, 0.1);
            padding: 1.25rem;
            border-radius: var(--border-radius);
            margin: 1.5rem 0;
            border: 1px solid rgba(139, 92, 246, 0.2);
            border-left: 4px solid var(--primary);
        }

        .test-accounts h4 {
            margin-bottom: 0.75rem;
            color: var(--text-primary);
            font-size: 1rem;
            font-weight: 600;
        }

        .test-account-item {
            margin-bottom: 0.5rem;
            padding: 0.5rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: var(--border-radius);
            border: 1px solid var(--glass-border);
        }

        .test-account-item:last-child {
            margin-bottom: 0;
        }

        .test-account-role {
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 0.25rem;
        }

        .test-account-details {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        /* Password toggle */
        .password-toggle {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 0.25rem;
            transition: var(--transition);
        }

        .password-toggle:hover {
            color: var(--text-primary);
        }

        .password-wrapper {
            position: relative;
        }

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

        /* Form focus effects */
        .form-group:focus-within label {
            color: var(--primary);
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
        }

        @media (max-width: 480px) {
            .container {
                padding: 1.5rem;
            }

            h1 {
                font-size: 1.5rem;
            }

            .test-accounts {
                padding: 1rem;
            }

            .test-account-details {
                font-size: 0.8rem;
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

            .test-accounts {
                background: #f8f9fa !important;
                border: 1px solid #ccc !important;
            }
        }
    </style>

    <script>
        // Password toggle functionality
        document.addEventListener('DOMContentLoaded', function () {
            const passwordInput = document.getElementById('password');
            const passwordToggle = document.createElement('button');
            passwordToggle.type = 'button';
            passwordToggle.className = 'password-toggle';
            passwordToggle.innerHTML = '👁️';
            passwordToggle.setAttribute('aria-label', 'Toggle password visibility');

            const passwordWrapper = document.createElement('div');
            passwordWrapper.className = 'password-wrapper';

            if (passwordInput && passwordInput.parentNode) {
                passwordInput.parentNode.insertBefore(passwordWrapper, passwordInput);
                passwordWrapper.appendChild(passwordInput);
                passwordWrapper.appendChild(passwordToggle);

                passwordToggle.addEventListener('click', function () {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    passwordToggle.innerHTML = type === 'password' ? '👁️' : '👁️‍🗨️';
                });
            }
        });

        // Form submission enhancement
        document.querySelector('form').addEventListener('submit', function (e) {
            const submitBtn = this.querySelector('.btn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Logging in...';

            setTimeout(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Login';
            }, 3000);
        });

        document.addEventListener('DOMContentLoaded', function () {
            const emailInput = document.getElementById('email');
            if (emailInput) {
                emailInput.focus();
            }
        });
    </script>
</head>

<body>
    <div class="container">
        <h1>Student Attendance Login</h1>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email"
                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
            </div>

            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="btn">Login</button>
        </form>

        <div class="register-link">
            Don't have an account? <a href="register.php">Register here</a>
        </div>
    </div>
</body>

</html>