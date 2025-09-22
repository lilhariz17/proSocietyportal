<?php
require_once 'config.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        try {
            // Get user from database
            $stmt = $pdo->prepare("SELECT user_id, role, email, password_hash, full_name FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['full_name'] = $user['full_name'];
                
                // Redirect to dashboard
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Invalid email or password.';
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Retirement Plan</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    
    body {
        background: linear-gradient(135deg, #800000 0%, #ff69b4 100%); /* maroon → candy pink */
        color: #333;
        line-height: 1.6;
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 100vh;
        padding: 20px;
    }
    
    .container {
        width: 100%;
        max-width: 500px;
        margin: 0 auto;
        animation: fadeIn 0.8s ease;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .form-container {
        background: white;
        border-radius: 35px;
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
        padding: 0 35px 40px 35px;
        text-align: center;
        position: relative;
        overflow: hidden;
    }
    
    .form-container::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 5px;
        background: linear-gradient(to right, #800000, #ff69b4); /* updated */
    }
    
    .logo {
        margin-bottom: 15px;
        color: #800000; /* updated */
        font-size: 36px;
    }
    
    /* Welcome Header Styles - Inside the box */
    .welcome-header {
        margin: 0 -35px 15px -35px;
        padding: 25px 20px 20px 20px;
        background: linear-gradient(to right, #800000, #ff69b4); /* updated */
        color: white;
        text-align: center;
        position: relative;
        overflow: hidden;
    }
    
    .welcome-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100' width='100' height='100'%3E%3Ccircle cx='50' cy='50' r='40' fill='none' stroke='rgba(255,255,255,0.1)' stroke-width='2'/%3E%3C/svg%3E");
        opacity: 0.3;
    }
    
    .welcome-header h1 {
        font-size: 26px;
        font-weight: 700;
        margin-bottom: 8px;
        position: relative;
        z-index: 1;
    }
    
    .welcome-header p {
        font-size: 16px;
        font-weight: 500;
        opacity: 0.9;
        position: relative;
        z-index: 1;
    }
    
    h2 {
        color: #2c3e50;
        margin-bottom: 25px;
        font-weight: 600;
        font-size: 28px;
        position: relative;
        display: inline-block;
    }
    
    h2::after {
        content: '';
        position: absolute;
        bottom: -10px;
        left: 50%;
        transform: translateX(-50%);
        width: 50px;
        height: 3px;
        background: linear-gradient(to right, #800000, #ff69b4); /* updated */
        border-radius: 3px;
    }
    
    .form-group {
        margin-bottom: 25px;
        text-align: left;
        position: relative;
    }
    
    label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: #2c3e50;
        padding-left: 5px;
    }
    
    .input-icon {
        position: relative;
    }
    
    .input-icon i {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #800000; /* updated */
        font-size: 18px;
    }
    
    input {
        width: 100%;
        padding: 14px 15px 14px 45px;
        border: 2px solid #e6e9ed;
        border-radius: 8px;
        font-size: 16px;
        transition: all 0.3s;
    }
    
    input:focus {
        border-color: #800000; /* updated */
        outline: none;
        box-shadow: 0 0 0 3px rgba(128, 0, 0, 0.2); /* maroon glow */
    }
    
    .password-toggle {
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        color: #800000; /* updated */
    }
    
    button {
        width: 100%;
        padding: 16px;
        background: linear-gradient(to right, #800000, #ff69b4); /* updated */
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 17px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        margin-top: 15px;
        box-shadow: 0 4px 10px rgba(128, 0, 0, 0.3); /* maroon shadow */
    }
    
    button:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(128, 0, 0, 0.4); /* deeper maroon */
    }
    
    .error {
        background-color: #ffebee;
        color: #c62828;
        padding: 14px;
        border-radius: 8px;
        margin-bottom: 25px;
        border-left: 4px solid #c62828;
        text-align: left;
        display: flex;
        align-items: center;
        animation: shake 0.5s ease;
    }
    
    .error i {
        margin-right: 10px;
        font-size: 20px;
    }
    
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        20%, 60% { transform: translateX(-5px); }
        40%, 80% { transform: translateX(5px); }
    }
    
    .redirect-link {
        margin-top: 30px;
        color: #666;
        font-size: 16px;
    }
    
    .redirect-link a {
        color: #800000; /* updated */
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s;
        position: relative;
    }
    
    .redirect-link a::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        width: 0;
        height: 2px;
        background: #800000; /* updated */
        transition: width 0.3s;
    }
    
    .redirect-link a:hover::after {
        width: 100%;
    }
    
    .features {
        display: flex;
        justify-content: space-between;
        margin-top: 20px;
    }
    
    .feature-item {
        display: flex;
        align-items: center;
        color: #666;
        font-size: 14px;
    }
    
    .feature-item i {
        margin-right: 5px;
        color: #800000; /* updated */
    }
    
    @media (max-width: 576px) {
        .form-container {
            padding: 0 25px 30px 25px;
        }
        
        .welcome-header {
            margin: 0 -25px 15px -25px;
            padding: 20px 15px 15px 15px;
        }
        
        .welcome-header h1 {
            font-size: 22px;
        }
        
        .welcome-header p {
            font-size: 14px;
        }
        
        .features {
            flex-direction: column;
            gap: 10px;
        }
    }
</style>

</head>
<body>
    <div class="container">
        <div class="form-container">
            <!-- Welcome Header Inside the Box -->
            <div class="welcome-header">
                <h1>Welcome! Pro Society Portal</h1>
                <p>Your gateway to retirement planning</p>
            </div>
            
            <h2>Login to Your Account</h2>
            
            <?php if ($error): ?>
                <div class="error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email</label>
                    <div class="input-icon">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" required>
                        <span class="password-toggle" id="passwordToggle">
                            <i class="far fa-eye"></i>
                        </span>
                    </div>
                </div>
                
                <button type="submit">Login</button>
            </form>
            
            <div class="features">
                <div class="feature-item">
                    <i class="fas fa-shield-alt"></i>
                    <span>Secure Login</span>
                </div>
                <div class="feature-item">
                    <i class="fas fa-clock"></i>
                    <span>24/7 Access</span>
                </div>
                <div class="feature-item">
                    <i class="fas fa-user-lock"></i>
                    <span>Privacy Protected</span>
                </div>
            </div>
            
            <div class="redirect-link">
                Don't have an account? <a href="signup.php">Sign up here</a>
            </div>
        </div>
    </div>

    <script>
        // Toggle password visibility
        document.getElementById('passwordToggle').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            }
        });
    </script>
</body>
</html>