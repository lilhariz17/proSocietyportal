<?php
require_once 'config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $full_name = $_POST['full_name'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $date_of_birth = $_POST['date_of_birth'] ?? '';
    $location = $_POST['location'] ?? '';
    
    // Validate inputs
    if (empty($role) || empty($email) || empty($password) || empty($full_name) || 
        empty($phone) || empty($date_of_birth) || empty($location)) {
        $error = 'All fields are required.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } else {
        try {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() > 0) {
                $error = 'Email already registered.';
            } else {
                // Hash password
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert user into database
                $stmt = $pdo->prepare("INSERT INTO users (role, email, password_hash, full_name, phone, date_of_birth, location) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$role, $email, $password_hash, $full_name, $phone, $date_of_birth, $location]);
                
                $user_id = $pdo->lastInsertId();
                
                // Create profile based on role
                if ($role === 'retiree') {
                    $stmt = $pdo->prepare("INSERT INTO retiree_profiles (retiree_id) VALUES (?)");
                    $stmt->execute([$user_id]);
                } elseif ($role === 'employer') {
                    $stmt = $pdo->prepare("INSERT INTO employer_profiles (employer_id) VALUES (?)");
                    $stmt->execute([$user_id]);
                }
                
                $success = 'Registration successful. You can now login.';
                header("Refresh: 2; URL=login.php");
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
    <title>Sign Up - Retirement Plan</title>
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
        line-height: 1.5;
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 100vh;
        padding: 15px;
    }
    
    .container {
        width: 100%;
        max-width: 500px;
        margin: 0 auto;
        animation: fadeIn 0.8s ease;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-15px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .form-container {
        background: white;
        border-radius: 35px;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        padding: 0 25px 25px 25px;
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
        height: 4px;
        background: linear-gradient(to right, #800000, #ff69b4); /* updated */
    }
    
    /* Welcome Header Styles - Inside the box */
    .welcome-header {
        margin: 0 -25px 12px -25px;
        padding: 18px 15px 12px 15px;
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
        font-size: 22px;
        font-weight: 700;
        margin-bottom: 4px;
        position: relative;
        z-index: 1;
    }
    
    .welcome-header p {
        font-size: 14px;
        font-weight: 500;
        opacity: 0.9;
        position: relative;
        z-index: 1;
    }
    
    .logo {
        margin-bottom: 3px;
        color: #800000; /* updated */
        font-size: 28px;
    }
    
    h2 {
        color: #2c3e50;
        margin-bottom: 15px;
        font-weight: 600;
        font-size: 22px;
        position: relative;
        display: inline-block;
    }
    
    h2::after {
        content: '';
        position: absolute;
        bottom: -6px;
        left: 50%;
        transform: translateX(-50%);
        width: 35px;
        height: 2px;
        background: linear-gradient(to right, #800000, #ff69b4); /* updated */
        border-radius: 2px;
    }
    
    .form-group {
        margin-bottom: 15px;
        text-align: left;
        position: relative;
    }
    
    label {
        display: block;
        margin-bottom: 5px;
        font-weight: 500;
        color: #2c3e50;
        padding-left: 4px;
        font-size: 14px;
    }
    
    .input-icon {
        position: relative;
    }
    
    .input-icon i {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #800000; /* updated */
        font-size: 15px;
    }
    
    input, select {
        width: 100%;
        padding: 10px 12px 10px 35px;
        border: 1.5px solid #e6e9ed;
        border-radius: 6px;
        font-size: 14px;
        transition: all 0.3s;
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
    }
    
    select {
        background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23800000' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e"); /* updated arrow */
        background-repeat: no-repeat;
        background-position: right 10px center;
        background-size: 12px;
    }
    
    input:focus, select:focus {
        border-color: #800000; /* updated */
        outline: none;
        box-shadow: 0 0 0 2px rgba(128, 0, 0, 0.2); /* maroon glow */
    }
    
    .password-toggle {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        color: #800000; /* updated */
        font-size: 14px;
    }
    
    button {
        width: 100%;
        padding: 12px;
        background: linear-gradient(to right, #800000, #ff69b4); /* updated */
        color: white;
        border: none;
        border-radius: 6px;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        margin-top: 8px;
        box-shadow: 0 3px 8px rgba(128, 0, 0, 0.3); /* updated shadow */
    }
    
    button:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 10px rgba(128, 0, 0, 0.4); /* updated hover */
    }
    
    .error {
        background-color: #ffebee;
        color: #c62828;
        padding: 10px;
        border-radius: 6px;
        margin-bottom: 15px;
        border-left: 3px solid #c62828;
        text-align: left;
        display: flex;
        align-items: center;
        animation: shake 0.5s ease;
        font-size: 13px;
    }
    
    .error i {
        margin-right: 6px;
        font-size: 16px;
    }
    
    .success {
        background-color: #e8f5e9;
        color: #2e7d32;
        padding: 10px;
        border-radius: 6px;
        margin-bottom: 15px;
        border-left: 3px solid #2e7d32;
        text-align: left;
        display: flex;
        align-items: center;
        font-size: 13px;
    }
    
    .success i {
        margin-right: 6px;
        font-size: 16px;
    }
    
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        20%, 60% { transform: translateX(-4px); }
        40%, 80% { transform: translateX(4px); }
    }
    
    .redirect-link {
        margin-top: 20px;
        color: #666;
        font-size: 14px;
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
        height: 1.5px;
        background: #800000; /* updated */
        transition: width 0.3s;
    }
    
    .redirect-link a:hover::after {
        width: 100%;
    }
    
    .features {
        display: flex;
        justify-content: space-between;
        margin-top: 12px;
        flex-wrap: wrap;
    }
    
    .feature-item {
        display: flex;
        align-items: center;
        color: #666;
        font-size: 12px;
        margin-bottom: 6px;
        width: calc(50% - 4px);
    }
    
    .feature-item i {
        margin-right: 4px;
        color: #800000; /* updated */
        font-size: 13px;
    }
    
    @media (max-width: 576px) {
        .form-container {
            padding: 0 18px 20px 18px;
        }
        
        .welcome-header {
            margin: 0 -18px 12px -18px;
            padding: 15px 12px 10px 12px;
        }
        
        .welcome-header h1 {
            font-size: 20px;
        }
        
        .welcome-header p {
            font-size: 13px;
        }
        
        .features {
            flex-direction: column;
            gap: 6px;
        }
        
        .feature-item {
            width: 100%;
        }
    }
</style>

</head>
<body>
    <div class="container">
        <div class="form-container">
            <!-- Welcome Header Inside the Box -->
            <div class="welcome-header">
                <h1>Join Pro Society Portal</h1>
                <p>Start your retirement planning journey today</p>
            </div>
            
            <div class="logo">
                <i class="fas fa-retirement"></i>
            </div>
            
            <h2>Create an Account</h2>
            
            <?php if ($error): ?>
                <div class="error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo $success; ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="role">I am a:</label>
                    <div class="input-icon">
                        <i class="fas fa-user-tag"></i>
                        <select id="role" name="role" required>
                            <option value="">Select Role</option>
                            <option value="retiree" <?php echo (isset($_POST['role']) && $_POST['role'] === 'retiree') ? 'selected' : ''; ?>>Retiree</option>
                            <option value="employer" <?php echo (isset($_POST['role']) && $_POST['role'] === 'employer') ? 'selected' : ''; ?>>Employer</option>
                            <option value="admin" <?php echo (isset($_POST['role']) && $_POST['role'] === 'admin') ? 'selected' : ''; ?>>Administrator</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <div class="input-icon">
                        <i class="fas fa-user"></i>
                        <input type="text" id="full_name" name="full_name" value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <div class="input-icon">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <div class="input-icon">
                        <i class="fas fa-phone"></i>
                        <input type="text" id="phone" name="phone" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="date_of_birth">Date of Birth</label>
                    <div class="input-icon">
                        <i class="fas fa-calendar-alt"></i>
                        <input type="date" id="date_of_birth" name="date_of_birth" value="<?php echo isset($_POST['date_of_birth']) ? htmlspecialchars($_POST['date_of_birth']) : ''; ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="location">Location</label>
                    <div class="input-icon">
                        <i class="fas fa-map-marker-alt"></i>
                        <input type="text" id="location" name="location" value="<?php echo isset($_POST['location']) ? htmlspecialchars($_POST['location']) : ''; ?>" required>
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
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="input-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                        <span class="password-toggle" id="confirmPasswordToggle">
                            <i class="far fa-eye"></i>
                        </span>
                    </div>
                </div>
                
                <button type="submit">Sign Up</button>
            </form>
            
            <div class="features">
                <div class="feature-item">
                    <i class="fas fa-shield-alt"></i>
                    <span>Secure Registration</span>
                </div>
                <div class="feature-item">
                    <i class="fas fa-user-check"></i>
                    <span>Role-Based Access</span>
                </div>
                <div class="feature-item">
                    <i class="fas fa-clock"></i>
                    <span>Instant Access</span>
                </div>
                <div class="feature-item">
                    <i class="fas fa-user-lock"></i>
                    <span>Privacy Protected</span>
                </div>
            </div>
            
            <div class="redirect-link">
                Already have an account? <a href="login.php">Login here</a>
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
        
        // Toggle confirm password visibility
        document.getElementById('confirmPasswordToggle').addEventListener('click', function() {
            const confirmPasswordInput = document.getElementById('confirm_password');
            const eyeIcon = this.querySelector('i');
            
            if (confirmPasswordInput.type === 'password') {
                confirmPasswordInput.type = 'text';
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            } else {
                confirmPasswordInput.type = 'password';
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            }
        });
    </script>
</body>
</html>