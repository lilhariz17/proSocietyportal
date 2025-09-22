<?php
// sidebar.php - Reusable sidebar component with integrated CSS
?>
<style>
/* Sidebar Styles */
.sidebar {
    width: 250px;
    background-color: #2c3e50;
    color: white;
    padding: 20px 0;
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
    height: 100vh;
    position: fixed;
    left: 0;
    top: 0;
    z-index: 1000;
}

.sidebar-header {
    padding: 0 20px 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.sidebar-header h2 {
    font-size: 1.5rem;
    margin-bottom: 5px;
}

.sidebar-header p {
    color: #bdc3c7;
    font-size: 0.9rem;
}

.sidebar-menu {
    list-style: none;
    margin-top: 20px;
    flex: 1;
}

.sidebar-menu li {
    margin-bottom: 5px;
}

.sidebar-menu a {
    display: flex;
    align-items: center;
    color: #bdc3c7;
    text-decoration: none;
    padding: 12px 20px;
    transition: all 0.3s ease;
    border-left: 3px solid transparent;
}

.sidebar-menu a:hover, 
.sidebar-menu a.active {
    background-color: rgba(255, 255, 255, 0.1);
    color: white;
    border-left: 3px solid #3498db;
}

.sidebar-menu i {
    margin-right: 10px;
    width: 20px;
    text-align: center;
}

/* Back to Dashboard Button */
.back-to-dashboard {
    margin: 15px 20px;
}

.back-to-dashboard a {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    padding: 12px;
    background-color: rgba(52, 152, 219, 0.2);
    color: white;
    text-decoration: none;
    border-radius: 4px;
    transition: background-color 0.3s ease;
    border: 1px solid rgba(52, 152, 219, 0.3);
}

.back-to-dashboard a:hover {
    background-color: rgba(52, 152, 219, 0.4);
}

.back-to-dashboard i {
    margin-right: 8px;
}

/* Sidebar Footer Styles */
.sidebar-footer {
    margin-top: auto;
    padding: 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.sidebar-user-info {
    display: flex;
    align-items: center;
    margin-bottom: 15px;
}

.sidebar-user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: rgba(255, 255, 255, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 10px;
}

.sidebar-user-avatar i {
    color: white;
    font-size: 1.2rem;
}

.sidebar-user-details {
    color: white;
}

.sidebar-user-name {
    font-weight: 600;
    font-size: 0.9rem;
}

.sidebar-user-role {
    font-size: 0.8rem;
    color: #bdc3c7;
}

.sidebar-logout-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    padding: 10px;
    background-color: rgba(231, 76, 60, 0.2);
    color: white;
    text-decoration: none;
    border-radius: 4px;
    transition: background-color 0.3s ease;
}

.sidebar-logout-btn:hover {
    background-color: rgba(231, 76, 60, 0.4);
}

.sidebar-logout-btn i {
    margin-right: 8px;
}

/* Main Content Adjustment */
.main-content-with-sidebar {
    margin-left: 250px;
    padding: 20px;
    min-height: 100vh;
}

/* Responsive Design */
@media (max-width: 768px) {
    .sidebar {
        width: 100%;
        height: auto;
        position: relative;
    }
    
    .main-content-with-sidebar {
        margin-left: 0;
    }
    
    .back-to-dashboard span {
        display: none;
    }
    
    .back-to-dashboard a {
        justify-content: center;
    }
    
    .back-to-dashboard i {
        margin-right: 0;
        font-size: 1.2rem;
    }
}
</style>

<aside class="sidebar">
    <div class="sidebar-header">
        <h2>Admin Portal</h2>
        <p>Retirement Plan System</p>
    </div>
    
    <!-- Back to Dashboard Button -->
    <div class="back-to-dashboard">
        <a href="adminDash.php">
            <i class="fas fa-arrow-left"></i>
            <span>Back to Dashboard</span>
        </a>
    </div>
    
    <ul class="sidebar-menu">
        <li><a href="adminDash.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'adminDash.php' ? 'active' : ''; ?>"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
        <li><a href="manageUser.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'manageUser.php' ? 'active' : ''; ?>"><i class="fas fa-users"></i> User Management</a></li>
        <li><a href="generateReport.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'generateReport.php' ? 'active' : ''; ?>"><i class="fas fa-chart-bar"></i> Generate Reports</a></li>
        <li><a href="systemSet.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'systemSet.php' ? 'active' : ''; ?>"><i class="fas fa-cog"></i> System Settings</a></li>
        <li><a href="manageJob.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'manageJob.php' ? 'active' : ''; ?>"><i class="fas fa-briefcase"></i> Job Listings</a></li>
    </ul>
    
    <div class="sidebar-footer">
        <div class="sidebar-user-info">
            <div class="sidebar-user-avatar">
                <i class="fas fa-user-shield"></i>
            </div>
            <div class="sidebar-user-details">
                <div class="sidebar-user-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?></div>
                <div class="sidebar-user-role">Administrator</div>
            </div>
        </div>
        <a href="logout.php" class="sidebar-logout-btn">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</aside>