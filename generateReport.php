<?php
require_once 'config.php';

// Redirect to login if not authenticated or not admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Initialize variables
$error = '';
$success = '';
$report_data = [];
$report_type = '';
$start_date = '';
$end_date = '';

// Handle report generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_report'])) {
    $report_type = $_POST['report_type'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    
    // Validate dates if provided
    if (!empty($start_date) && !empty($end_date) && strtotime($start_date) > strtotime($end_date)) {
        $error = 'End date must be after start date.';
    } else {
        try {
            // Generate report based on type
            switch ($report_type) {
                case 'user_registrations':
                    $query = "SELECT 
                                DATE(created_at) as registration_date, 
                                role, 
                                COUNT(*) as count 
                              FROM users 
                              WHERE 1=1";
                    
                    if (!empty($start_date)) {
                        $query .= " AND created_at >= :start_date";
                    }
                    if (!empty($end_date)) {
                        $query .= " AND created_at <= :end_date + INTERVAL 1 DAY";
                    }
                    
                    $query .= " GROUP BY DATE(created_at), role ORDER BY registration_date DESC";
                    
                    $stmt = $pdo->prepare($query);
                    if (!empty($start_date)) $stmt->bindValue(':start_date', $start_date);
                    if (!empty($end_date)) $stmt->bindValue(':end_date', $end_date);
                    $stmt->execute();
                    $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    break;
                    
                case 'job_postings':
                    $query = "SELECT 
                                c.company_name,
                                j.title,
                                j.job_type,
                                j.location,
                                j.created_at,
                                COUNT(a.application_id) as application_count
                              FROM jobs j
                              JOIN companies c ON j.company_id = c.company_id
                              LEFT JOIN applications a ON j.job_id = a.job_id
                              WHERE 1=1";
                    
                    if (!empty($start_date)) {
                        $query .= " AND j.created_at >= :start_date";
                    }
                    if (!empty($end_date)) {
                        $query .= " AND j.created_at <= :end_date + INTERVAL 1 DAY";
                    }
                    
                    $query .= " GROUP BY j.job_id ORDER BY j.created_at DESC";
                    
                    $stmt = $pdo->prepare($query);
                    if (!empty($start_date)) $stmt->bindValue(':start_date', $start_date);
                    if (!empty($end_date)) $stmt->bindValue(':end_date', $end_date);
                    $stmt->execute();
                    $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    break;
                    
                case 'applications':
                    $query = "SELECT 
                                a.application_id,
                                u.full_name as applicant_name,
                                j.title as job_title,
                                c.company_name,
                                a.status,
                                a.applied_at
                              FROM applications a
                              JOIN users u ON a.retiree_id = u.user_id
                              JOIN jobs j ON a.job_id = j.job_id
                              JOIN companies c ON j.company_id = c.company_id
                              WHERE 1=1";
                    
                    if (!empty($start_date)) {
                        $query .= " AND a.applied_at >= :start_date";
                    }
                    if (!empty($end_date)) {
                        $query .= " AND a.applied_at <= :end_date + INTERVAL 1 DAY";
                    }
                    
                    $query .= " ORDER BY a.applied_at DESC";
                    
                    $stmt = $pdo->prepare($query);
                    if (!empty($start_date)) $stmt->bindValue(':start_date', $start_date);
                    if (!empty($end_date)) $stmt->bindValue(':end_date', $end_date);
                    $stmt->execute();
                    $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    break;
                    
                case 'system_activity':
                    $query = "SELECT 
                                al.log_id,
                                u.full_name,
                                al.activity_type,
                                al.details,
                                al.created_at
                              FROM activity_logs al
                              JOIN users u ON al.user_id = u.user_id
                              WHERE 1=1";
                    
                    if (!empty($start_date)) {
                        $query .= " AND al.created_at >= :start_date";
                    }
                    if (!empty($end_date)) {
                        $query .= " AND al.created_at <= :end_date + INTERVAL 1 DAY";
                    }
                    
                    $query .= " ORDER BY al.created_at DESC LIMIT 100";
                    
                    $stmt = $pdo->prepare($query);
                    if (!empty($start_date)) $stmt->bindValue(':start_date', $start_date);
                    if (!empty($end_date)) $stmt->bindValue(':end_date', $end_date);
                    $stmt->execute();
                    $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    break;
                    
                default:
                    $error = 'Please select a valid report type.';
                    break;
            }
            
            // Log the report generation activity
            if (empty($error)) {
                $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, activity_type, details) VALUES (?, ?, ?)");
                $details = "Generated report: $report_type" . 
                          (!empty($start_date) ? " from $start_date" : "") . 
                          (!empty($end_date) ? " to $end_date" : "");
                $stmt->execute([$_SESSION['user_id'], 'generate_report', $details]);
                
                // Save report to reports table
                $stmt = $pdo->prepare("INSERT INTO reports (admin_id, report_type, created_at) VALUES (?, ?, NOW())");
                $stmt->execute([$_SESSION['user_id'], $report_type]);
                
                $success = 'Report generated successfully. Found ' . count($report_data) . ' records.';
            }
            
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Handle PDF export
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_pdf']) && !empty($report_data)) {
    // This would typically generate a PDF file for download
    // For simplicity, we'll just set a message
    $success = 'PDF export functionality would be implemented here.';
}

// Handle CSV export
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_csv']) && !empty($report_data)) {
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=report_' . $report_type . '_' . date('Y-m-d') . '.csv');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add headers
    if (!empty($report_data)) {
        fputcsv($output, array_keys($report_data[0]));
        
        // Add data
        foreach ($report_data as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    exit();
}

// Determine current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Reports - Retirement Plan</title>
    <link rel="stylesheet" href="style.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        /* Layout untuk sidebar dan content */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', 'Roboto', sans-serif;
        }
        
        body {
            display: flex;
            min-height: 100vh;
            background-color: #f8f9fa;
            margin: 0;
            color: #333;
        }
        
        .content {
            flex: 1;
            margin-left: 250px;
            padding: 0;
        }
        
        @media (max-width: 768px) {
            .content {
                margin-left: 0;
            }
        }
        
        /* Dashboard Header */
        .dashboard-header {
            background: linear-gradient(135deg, #800000 0%, #ff63a5 100%);
            color: white;
            padding: 25px 30px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .dashboard-header h1 {
            font-weight: 600;
            font-size: 28px;
            margin: 0;
            letter-spacing: 0.5px;
        }
        
        /* Dashboard Content */
        .dashboard-content {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .back-btn {
            display: inline-block;
            margin-bottom: 20px;
            padding: 12px 20px;
            background: linear-gradient(135deg, #800000 0%, #ff63a5 100%);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(127, 140, 141, 0.3);
        }
        .back-btn:hover {
            background: linear-gradient(135deg, #ff63a5 0%, #800000 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(127, 140, 141, 0.4);
        }
        
        h2 {
            color: #2c3e50;
            margin-bottom: 25px;
            font-weight: 600;
            border-bottom: 2px solid #800000; /* only color changed */
            padding-bottom: 12px;
            font-size: 24px;
        }
        
        .error {
            background-color: #fee;
            color: #c33;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #c33;
            font-weight: 500;
        }
        
        .success {
            background-color: #efe;
            color: #363;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #363;
            font-weight: 500;
        }
        
        .report-form {
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            padding: 25px;
            margin-bottom: 25px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
            font-size: 16px;
        }
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            font-family: 'Poppins', sans-serif;
            transition: border-color 0.3s ease;
        }
        .form-control:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            margin-right: 15px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-primary {
            background: linear-gradient(135deg, #800000 0%, #ff63a5 100%);
            color: white;
        }
        .btn-primary:hover {
            opacity: 0.9;
        }
        .btn-success {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            color: white;
            box-shadow: 0 2px 5px rgba(46, 204, 113, 0.3);
        }
        .btn-success:hover {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(46, 204, 113, 0.4);
        }
        .btn-info {
            background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
            color: white;
            box-shadow: 0 2px 5px rgba(155, 89, 182, 0.3);
        }
        .btn-info:hover {
            background: linear-gradient(135deg, #8e44ad 0%, #9b59b6 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(155, 89, 182, 0.4);
        }
        .report-results {
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            padding: 25px;
            margin-top: 25px;
        }
        .results-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .results-table th, .results-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .results-table th {
            background: linear-gradient(135deg, #800000 0%, #ff63a5 100%);
            font-weight: 600;
            color: white;
            font-size: 16px;
        }
        .results-table tr:hover {
            background-color: #f9f9f9;
        }
        .export-options {
            margin: 25px 0;
            padding: 20px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 8px;
            border-left: 4px solid #3498db;
        }
        .export-options h3 {
            margin-bottom: 15px;
            color: #2c3e50;
            font-weight: 600;
        }
        .no-data {
            text-align: center;
            padding: 50px;
            color: #7f8c8d;
            font-style: italic;
            font-size: 18px;
        }
        .stats-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            padding: 20px;
            text-align: center;
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-number {
            font-size: 2.2rem;
            font-weight: 700;
            color: #3498db;
            margin: 15px 0;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        }
        .stat-label {
            color: #7f8c8d;
            font-size: 1rem;
            font-weight: 500;
        }
        
        .date-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
    </style>
</head>
<body>
    <!-- Include Sidebar -->
    <?php 
    // Set current page for active menu highlighting
    $current_page = 'generateReport.php';
    include 'sidebarAdmin.php'; 
    ?>
    
    <!-- Main Content -->
    <div class="content">
        <div class="dashboard-header">
            <h1>Generate Reports - Pro Society Portal</h1>
        </div>
        
        <div class="dashboard-content">
            <h2>Report Generator</h2>
            
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <div class="report-form">
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="report_type">Report Type</label>
                        <select id="report_type" name="report_type" class="form-control" required>
                            <option value="">Select Report Type</option>
                            <option value="user_registrations" <?php echo $report_type === 'user_registrations' ? 'selected' : ''; ?>>User Registrations</option>
                            <option value="job_postings" <?php echo $report_type === 'job_postings' ? 'selected' : ''; ?>>Job Postings</option>
                            <option value="applications" <?php echo $report_type === 'applications' ? 'selected' : ''; ?>>Job Applications</option>
                            <option value="system_activity" <?php echo $report_type === 'system_activity' ? 'selected' : ''; ?>>System Activity Logs</option>
                        </select>
                    </div>
                    
                    <div class="date-grid">
                        <div class="form-group">
                            <label for="start_date">Start Date (Optional)</label>
                            <input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="end_date">End Date (Optional)</label>
                            <input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date); ?>">
                        </div>
                    </div>
                    
                    <button type="submit" name="generate_report" class="btn btn-primary">Generate Report</button>
                </form>
            </div>
            
            <?php if (!empty($report_data)): ?>
                <div class="export-options">
                    <h3>Export Options</h3>
                    <form method="POST" action="" style="display: inline-block;">
                        <input type="hidden" name="report_type" value="<?php echo htmlspecialchars($report_type); ?>">
                        <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                        <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                        <button type="submit" name="export_csv" class="btn btn-success">Export to CSV</button>
                    </form>
                    
                    <form method="POST" action="" style="display: inline-block;">
                        <input type="hidden" name="report_type" value="<?php echo htmlspecialchars($report_type); ?>">
                        <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                        <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                        <button type="submit" name="export_pdf" class="btn btn-info">Export to PDF</button>
                    </form>
                </div>
                
                <div class="stats-summary">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($report_data); ?></div>
                        <div class="stat-label">Total Records</div>
                    </div>
                    
                    <?php if ($report_type === 'user_registrations'): ?>
                        <?php
                        $role_counts = [];
                        foreach ($report_data as $row) {
                            $role = $row['role'];
                            if (!isset($role_counts[$role])) {
                                $role_counts[$role] = 0;
                            }
                            $role_counts[$role] += $row['count'];
                        }
                        ?>
                        <?php foreach ($role_counts as $role => $count): ?>
                            <div class="stat-card">
                                <div class="stat-number"><?php echo $count; ?></div>
                                <div class="stat-label"><?php echo ucfirst($role); ?>s</div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="report-results">
                    <h3>Report Results: <?php echo ucfirst(str_replace('_', ' ', $report_type)); ?></h3>
                    
                    <table class="results-table">
                        <thead>
                            <tr>
                                <?php if (!empty($report_data)): ?>
                                    <?php foreach (array_keys($report_data[0]) as $column): ?>
                                        <th><?php echo ucfirst(str_replace('_', ' ', $column)); ?></th>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($report_data)): ?>
                                <?php foreach ($report_data as $row): ?>
                                    <tr>
                                        <?php foreach ($row as $value): ?>
                                            <td><?php echo htmlspecialchars($value); ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?php echo count($report_data[0] ?? []); ?>" class="no-data">
                                        No data found for the selected criteria.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($report_data) && empty($error)): ?>
                <div class="report-results">
                    <div class="no-data">
                        No data found for the selected report criteria.
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>