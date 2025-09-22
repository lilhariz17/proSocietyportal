<?php
session_start();
require_once 'config.php';

// Redirect if not logged in or not retiree
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'retiree') {
    header("Location: login.php");
    exit();
}

$retiree_id = $_SESSION['user_id'];
$error = '';
$applications = [];

// === Status filter from URL (normalize) ===
$status_filter_raw = $_GET['status'] ?? 'all';
$status_filter = strtolower(trim($status_filter_raw));

// === Map DB status → internal bucket ===
function map_status_to_bucket(string $dbStatus): string {
    $s = strtolower(trim($dbStatus));
    if ($s === 'accepted' || $s === 'approved') return 'approved';
    if ($s === 'rejected') return 'rejected';
    if ($s === 'pending' || $s === 'reviewed') return 'pending';
    return 'pending'; // default fallback
}

// === Display text for status badges ===
function display_status_text(string $dbStatus): string {
    $s = strtolower(trim($dbStatus));
    if ($s === 'accepted') return 'Approved';
    return ucfirst($s);
}

try {
    // === Base query for retiree's applications ===
    $sql = "SELECT a.*, j.title, j.location, j.job_type, c.company_name
            FROM applications a
            JOIN jobs j ON a.job_id = j.job_id
            JOIN companies c ON j.company_id = c.company_id
            WHERE a.retiree_id = ?";
    $params = [$retiree_id];

    // Apply filter
    if ($status_filter !== 'all') {
        if ($status_filter === 'approved') {
            $allowed = ['approved', 'accepted'];
        } elseif ($status_filter === 'pending') {
            $allowed = ['pending', 'reviewed'];
        } elseif ($status_filter === 'rejected') {
            $allowed = ['rejected'];
        } else {
            $allowed = [$status_filter];
        }

        $placeholders = implode(',', array_fill(0, count($allowed), '?'));
        $sql .= " AND LOWER(TRIM(a.status)) IN ($placeholders)";
        foreach ($allowed as $val) {
            $params[] = strtolower($val);
        }
    }

    $sql .= " ORDER BY a.applied_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// === Count applications by bucket ===
$status_counts = [
    'all' => 0,
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0
];

try {
    $count_sql = "SELECT TRIM(LOWER(status)) AS status, COUNT(*) AS cnt
                  FROM applications
                  WHERE retiree_id = ?
                  GROUP BY TRIM(LOWER(status))";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute([$retiree_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total = 0;
    foreach ($rows as $r) {
        $bucket = map_status_to_bucket($r['status']);
        if (isset($status_counts[$bucket])) {
            $status_counts[$bucket] += (int)$r['cnt'];
        }
        $total += (int)$r['cnt'];
    }
    $status_counts['all'] = $total;
} catch (PDOException $e) {
    // keep all counts as 0 if error
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application History - Retirement Plan</title>
    <style>
    body {
        margin: 0;
        padding: 0;
        background-color: #f8f9fa; /* subtle light background */
        font-family: 'Poppins','Roboto',sans-serif;
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
        margin-bottom: 30px;
    }

    .dashboard-header h1 {
        margin: 0;
        font-size: 28px;
        font-weight: 600;
    }

    /* Filters + application cards */
    .filter-section,
    .applications-list,
    .no-applications {
        background: white;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 25px;
    }

    /* Status filters styled with gradient where appropriate */
    .status-filters {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }

    .status-filter {
        padding: 8px 16px;
        border-radius: 20px;
        font-weight: 500;
        cursor: pointer;
        transition: 0.3s;
        color: white;
    }

    /* Maroon → pink gradient for 'all' filter */
    .status-filter.all { 
        background: linear-gradient(135deg, #800000 0%, #ff63a5 100%);
    }

    .status-filter.pending { background: #f39c12; color: white; }
    .status-filter.approved { background: #2ecc71; color: white; }
    .status-filter.rejected { background: #e74c3c; color: white; }
    .status-filter.interview { 
        /* New interview status with purple gradient */
        background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
        color: white;
    }

    .status-filter.active { box-shadow: 0 0 0 2px currentColor; }

    .count-badge {
        padding: 2px 6px;
        background: rgba(0,0,0,0.2);
        border-radius: 10px;
        font-size: 0.8rem;
        margin-left: 5px;
    }

    /* Application card styling */
    .application-card {
        padding: 20px;
        border-radius: 8px;
        background: white;
        box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        transition: 0.3s;
        margin-bottom: 15px;
    }

    .application-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    .application-header {
        display: flex;
        justify-content: space-between;
        margin-bottom: 15px;
    }

    .job-title { 
        font-size: 1.2rem; 
        font-weight: 600; 
        margin: 0 0 5px 0; 
        color: #800000; /* match sidebar maroon */
    }

    .company-name { 
        color: #9a9999ff; /* pink accent for company */
        margin-bottom: 8px; 
    }

    .application-status {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 500;
    }

    .status-pending { background: #fff3cd; color: #856404; }
    .status-approved { background: #d4edda; color: #155724; }
    .status-rejected { background: #f8d7da; color: #721c24; }
    .status-interview { 
        background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
        color: white;
    }

    .applied-date { color: #7f8c8d; font-size: 0.9rem; }

    /* View button with gradient */
    .btn-view {
        background: linear-gradient(135deg, #800000 0%, #ff63a5 100%);
        color: #fff;
        padding: 8px 14px;
        border-radius: 4px;
        text-decoration: none;
        transition: all 0.3s ease;
    }

    .btn-view:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }

    .no-applications {
        text-align: center;
        padding: 40px 20px;
    }

    .no-applications p {
        margin-bottom: 20px;
        font-size: 1.1rem;
        color: #7f8c8d;
    }
</style>

</head>
<body>
    <?php include 'sidebarRetiree.php'; ?>

    <div class="content">
        <!-- ✅ Header matches retireeDash -->
        <div class="dashboard-header">
            <h1> Applications History - Pro Society Portal</h1>
        </div>

        <!-- Status filters -->
        <div class="filter-section">
            <h3>Filter by Status</h3>
            <div class="status-filters">
                <div class="status-filter all <?php echo ($status_filter === 'all') ? 'active' : ''; ?>" data-status="all">
                    All <span class="count-badge"><?php echo $status_counts['all']; ?></span>
                </div>
                <div class="status-filter pending <?php echo ($status_filter === 'pending') ? 'active' : ''; ?>" data-status="pending">
                    Pending <span class="count-badge"><?php echo $status_counts['pending']; ?></span>
                </div>
                <div class="status-filter approved <?php echo ($status_filter === 'approved') ? 'active' : ''; ?>" data-status="approved">
                    Approved <span class="count-badge"><?php echo $status_counts['approved']; ?></span>
                </div>
                <div class="status-filter rejected <?php echo ($status_filter === 'rejected') ? 'active' : ''; ?>" data-status="rejected">
                    Rejected <span class="count-badge"><?php echo $status_counts['rejected']; ?></span>
                </div>
            </div>
        </div>

        <!-- Applications list -->
        <?php if (!empty($error)): ?>
            <div class="no-applications"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif (empty($applications)): ?>
            <div class="no-applications">
                <p>You haven't applied to any jobs yet.</p>
                <a href="searchJob.php" class="btn-view">Browse Jobs</a>
            </div>
        <?php else: ?>
            <div class="applications-list">
                <?php foreach ($applications as $app): 
                    $dbStatus = $app['status'] ?? 'pending';
                    $bucket = map_status_to_bucket($dbStatus);
                    $badgeClass = 'status-' . $bucket;
                    $displayText = display_status_text($dbStatus);
                ?>
                    <div class="application-card">
                        <div class="application-header">
                            <div>
                                <h3 class="job-title"><?php echo htmlspecialchars($app['title']); ?></h3>
                                <p class="company-name"><?php echo htmlspecialchars($app['company_name']); ?></p>
                                <p><?php echo htmlspecialchars($app['location']); ?> • <?php echo htmlspecialchars($app['job_type']); ?></p>
                            </div>
                            <span class="application-status <?php echo $badgeClass; ?>">
                                <?php echo htmlspecialchars($displayText); ?>
                            </span>
                        </div>
                        <div class="application-footer">
                            <span class="applied-date">Applied on: <?php echo date('F j, Y', strtotime($app['applied_at'])); ?></span>
                            <a href="job_details.php?id=<?php echo (int)$app['job_id']; ?>" class="btn-view">View Job</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Reload page with selected status filter
        document.querySelectorAll('.status-filter').forEach(btn => {
            btn.addEventListener('click', () => {
                const status = btn.getAttribute('data-status');
                window.location.href = `viewApplicationsR.php?status=${status}`;
            });
        });
    </script>
</body>
</html>
