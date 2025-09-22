<?php
// Database connection (replace with your actual credentials)
$servername = "localhost";
$username = "your_username";
$password = "your_password";
$dbname = "your_database";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch applications from database
$sql = "SELECT * FROM applications ORDER BY application_date DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Applications</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f7f9;
            color: #333;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        header {
            background-color: #2c3e50;
            color: white;
            padding: 20px 0;
            margin-bottom: 30px;
        }
        h1 {
            margin: 0;
            padding: 0 20px;
        }
        .application-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .application-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .applicant-name {
            font-size: 1.4em;
            font-weight: 600;
            color: #2c3e50;
        }
        .application-date {
            color: #7f8c8d;
            font-size: 0.9em;
        }
        .application-details {
            margin-bottom: 15px;
        }
        .application-actions {
            display: flex;
            gap: 10px;
        }
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
        }
        .btn-view {
            background-color: #3498db;
            color: white;
        }
        .btn-download {
            background-color: #2ecc71;
            color: white;
        }
        .btn-reject {
            background-color: #e74c3c;
            color: white;
        }
        .no-applications {
            text-align: center;
            padding: 40px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .filters {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .filter-group {
            margin-right: 15px;
        }
        label {
            margin-right: 5px;
        }
        select, input {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1>View Applications</h1>
        </div>
    </header>
    
    <div class="container">
        <div class="filters">
            <h3>Filter Applications</h3>
            <div style="display: flex; flex-wrap: wrap;">
                <div class="filter-group">
                    <label for="job-title">Job Title:</label>
                    <select id="job-title">
                        <option value="">All Jobs</option>
                        <!-- Populate with job titles from database -->
                    </select>
                </div>
                <div class="filter-group">
                    <label for="status">Status:</label>
                    <select id="status">
                        <option value="">All Statuses</option>
                        <option value="new">New</option>
                        <option value="reviewed">Reviewed</option>
                        <option value="interview">Interview</option>
                        <option value="rejected">Rejected</option>
                        <option value="hired">Hired</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="date">Date:</label>
                    <input type="date" id="date">
                </div>
            </div>
        </div>

        <?php if ($result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
                <div class="application-card">
                    <div class="application-header">
                        <div class="applicant-name"><?php echo htmlspecialchars($row['applicant_name']); ?></div>
                        <div class="application-date"><?php echo date('M j, Y', strtotime($row['application_date'])); ?></div>
                    </div>
                    <div class="application-details">
                        <p><strong>Position:</strong> <?php echo htmlspecialchars($row['job_title']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($row['applicant_email']); ?></p>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($row['applicant_phone']); ?></p>
                        <p><strong>Status:</strong> <span class="status-badge"><?php echo ucfirst($row['status']); ?></span></p>
                    </div>
                    <div class="application-actions">
                        <button class="btn btn-view">View Full Application</button>
                        <button class="btn btn-download">Download Resume</button>
                        <button class="btn btn-reject">Reject</button>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="no-applications">
                <h2>No Applications Yet</h2>
                <p>You haven't received any job applications yet. Check back later.</p>
            </div>
        <?php endif; ?>
    </div>

    <?php $conn->close(); ?>
</body>
</html>