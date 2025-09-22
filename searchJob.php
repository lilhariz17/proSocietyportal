<?php
session_start();
require_once 'config.php';

// Ensure PDO is available
if (!isset($pdo) || !($pdo instanceof PDO)) {
    die("Database connection not established. Check config.php (expected \$pdo PDO instance).");
}

// Redirect if not logged in or not retiree
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'retiree') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get retiree's profile (skills)
try {
    $skills_query = "SELECT skills FROM retiree_profiles WHERE retiree_id = ?";
    $stmt = $pdo->prepare($skills_query);
    $stmt->execute([$user_id]);
    $retiree_profile = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("DB error fetching retiree profile: " . $e->getMessage());
    $retiree_profile = null;
}

$user_skills = [];
if ($retiree_profile && !empty($retiree_profile['skills'])) {
    $user_skills = array_map('trim', explode(',', $retiree_profile['skills']));
}

// Initialize search variables
$search_results = [];
$search_term = "";
$location_filter = "";
$job_type_filter = "";

// Handle search form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $search_term = trim($_POST['search_term'] ?? '');
    $location_filter = trim($_POST['location'] ?? '');
    $job_type_filter = trim($_POST['job_type'] ?? '');
    
    $query = "SELECT j.*, c.company_name, c.industry 
              FROM jobs j 
              JOIN companies c ON j.company_id = c.company_id 
              WHERE j.status = 'approved'";
    
    $params = [];
    
    if (!empty($search_term)) {
        $query .= " AND (j.title LIKE ? OR j.description LIKE ? OR c.company_name LIKE ?)";
        $search_param = "%$search_term%";
        $params = array_merge($params, [$search_param, $search_param, $search_param]);
    }
    
    if (!empty($location_filter)) {
        $query .= " AND j.location LIKE ?";
        $params[] = "%$location_filter%";
    }
    
    if (!empty($job_type_filter)) {
        $query .= " AND j.job_type = ?";
        $params[] = $job_type_filter;
    }
    
    $query .= " ORDER BY j.created_at DESC";
    
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $search_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("DB error executing job search: " . $e->getMessage());
        $search_results = [];
    }
}

// Get available job types for dropdown filter
try {
    $job_types_stmt = $pdo->query("SELECT DISTINCT job_type FROM jobs WHERE status = 'approved' ORDER BY job_type");
    $job_types = $job_types_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("DB error fetching job types: " . $e->getMessage());
    $job_types = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Jobs - Retirement Plan Portal</title>
    <style>
        /* General layout reset */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins','Roboto',sans-serif;
        }

        body {
            display: flex;
            min-height: 100vh;
            background-color: #f5f7f9;
            color: #333;
        }

        /* Content area beside sidebar */
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

        /* ✅ Unified dashboard header with sidebar gradient */
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

        /* Search box styling */
        .search-section {
            background-color: white;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin: 30px 20px;
        }

        .search-section h2 {
            margin-bottom: 20px;
            color: #2c3e50;
        }

        /* Form grid layout */
        .search-form {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr auto;
            gap: 15px;
        }

        @media (max-width: 768px) {
            .search-form {
                grid-template-columns: 1fr;
            }
        }

        /* Form input styling */
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        /* Buttons styling with sidebar gradient */
        .btn {
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #800000 0%, #ff63a5 100%);
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #ff63a5 0%, #800000 100%);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #800000 0%, #ff63a5 100%);
            color: white;
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, #ff63a5 0%, #800000 100%);
            transform: translateY(-2px);
        }

        .results-section {
            padding: 20px;
        }

        .results-section h2 {
            margin-bottom: 20px;
            color: #2c3e50;
        }

        /* Job card styling */
        .job-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .job-header {
            margin-bottom: 10px;
        }

        .job-title {
            font-size: 20px;
            font-weight: 600;
            color: #2c3e50;
        }

        .company-name {
            font-size: 15px;
            color: #7f8c8d;
        }

        .job-details {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin: 10px 0;
            color: #7f8c8d;
        }

        .job-description {
            margin: 15px 0;
            line-height: 1.6;
        }

        .job-actions {
            display: flex;
            gap: 10px;
        }

        .no-results {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .skills-highlight {
            background-color: #e8f4fc;
            padding: 10px;
            border-radius: 4px;
            margin-top: 15px;
        }

        .skills-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }

        .skill-tag {
            background-color: #3498db;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
        }

        .matching-skills {
            margin-top: 10px;
            padding: 10px;
            background-color: #e8f6ef;
            border-left: 4px solid #2ecc71;
            border-radius: 4px;
        }

        .matching-skills h4 {
            margin-bottom: 5px;
            color: #27ae60;
        }
    </style>
</head>
<body>
    <!-- Include Retiree Sidebar -->
    <?php include 'sidebarRetiree.php'; ?>

    <!-- Main Content -->
    <div class="content">
        <!-- ✅ Header now same as retireeDash.php -->
        <div class="dashboard-header">
            <h1>Search Job Opportunities - Pro Society Portal</h1>
        </div>

        <!-- Search Section -->
        <section class="search-section">
            <h2>Find Opportunities That Match Your Skills</h2>
            <form method="POST" class="search-form">
                <div class="form-group">
                    <label for="search_term">Keywords</label>
                    <input type="text" id="search_term" name="search_term" placeholder="Job title, company, or keywords" value="<?php echo htmlspecialchars($search_term); ?>">
                </div>
                <div class="form-group">
                    <label for="location">Location</label>
                    <input type="text" id="location" name="location" placeholder="City, state, or remote" value="<?php echo htmlspecialchars($location_filter); ?>">
                </div>
                <div class="form-group">
                    <label for="job_type">Job Type</label>
                    <select id="job_type" name="job_type">
                        <option value="">All Types</option>
                        <?php foreach ($job_types as $type): ?>
                            <option value="<?php echo htmlspecialchars($type['job_type']); ?>" <?php echo ($job_type_filter === $type['job_type']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type['job_type']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="align-self: flex-end;">
                    <button type="submit" class="btn btn-primary">Search Jobs</button>
                </div>
            </form>

            <?php if (!empty($user_skills)): ?>
            <div class="skills-highlight">
                <strong>Your Skills:</strong>
                <div class="skills-list">
                    <?php foreach ($user_skills as $skill): ?>
                        <span class="skill-tag"><?php echo htmlspecialchars($skill); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </section>

        <!-- Results Section -->
        <section class="results-section">
            <h2>Job Opportunities</h2>

            <?php if (empty($search_results)): ?>
                <div class="no-results">
                    <h3>No job opportunities found</h3>
                    <p>Try adjusting your search criteria or check back later for new postings.</p>
                </div>
            <?php else: ?>
                <?php foreach ($search_results as $job): 
                    // Extract simple skill words from description
                    $job_skills = [];
                    if (!empty($job['description'])) {
                        preg_match_all('/\b([A-Za-z]+)\b/', $job['description'], $potential_skills);
                        $job_skills = array_map('strtolower', $potential_skills[0]);
                    }
                    $matching_skills = array_intersect(
                        array_map('strtolower', $user_skills), 
                        $job_skills
                    );
                ?>
                    <div class="job-card">
                        <div class="job-header">
                            <h3 class="job-title"><?php echo htmlspecialchars($job['title']); ?></h3>
                            <div class="company-name"><?php echo htmlspecialchars($job['company_name']); ?> • <?php echo htmlspecialchars($job['industry']); ?></div>
                        </div>

                        <div class="job-details">
                            <span>📍 <?php echo htmlspecialchars($job['location']); ?></span>
                            <span>🕒 <?php echo htmlspecialchars($job['job_type']); ?></span>
                            <span>📅 Posted: <?php echo date('M j, Y', strtotime($job['created_at'])); ?></span>
                        </div>

                        <div class="job-description">
                            <?php 
                            $description = $job['description'];
                            if (strlen($description) > 200) {
                                $description = substr($description, 0, 200) . '...';
                            }
                            echo nl2br(htmlspecialchars($description));
                            ?>
                        </div>

                        <?php if (!empty($matching_skills)): ?>
                        <div class="matching-skills">
                            <h4>Matches your skills:</h4>
                            <div class="skills-list">
                                <?php foreach ($matching_skills as $skill): ?>
                                    <span class="skill-tag"><?php echo htmlspecialchars($skill); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="job-actions">
                            <a href="job_details.php?id=<?php echo urlencode($job['job_id']); ?>" class="btn btn-secondary">View Details</a>
                            <a href="apply_job.php?id=<?php echo urlencode($job['job_id']); ?>" class="btn btn-primary">Apply Now</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </div>
</body>
</html>
