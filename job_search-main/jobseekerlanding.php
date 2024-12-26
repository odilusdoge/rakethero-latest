<?php
    include 'db_conn.php';
    session_start();

    // Debug session information
    error_log("Session data: " . print_r($_SESSION, true));

    // Logout logic
    if (isset($_GET['action']) && $_GET['action'] == 'logout') {
        session_unset();
        session_destroy();
        header("Location: index.php");
        exit();
    }

    // Check if user is not logged in; redirect to login page
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['userType']) || !isset($_SESSION['username'])) {
        error_log("User not logged in - redirecting to index.php");
        header("Location: index.php");
        exit();
    }

    // Connect to the database
    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Add this debug query before the main query
    $debug_jobs = $conn->query("SELECT COUNT(*) as total FROM jobs WHERE status = 'Open'");
    if ($debug_jobs) {
        $total_jobs = $debug_jobs->fetch_assoc()['total'];
        error_log("Total open jobs in database: " . $total_jobs);
    }

    // Add this function after your database connection
    function cleanupOldApplications($conn) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Only get applications that are completed OR have accepted quotations
            // Do NOT include rejected applications
            $getOldApps = "SELECT a.applications_id, a.userid, a.jobid, a.status
                           FROM applications a
                           WHERE (a.status = 'Completed')  /* Remove rejected from here */
                           OR EXISTS (
                               SELECT 1 
                               FROM quotations q 
                               WHERE q.applications_id = a.applications_id 
                               AND q.status = 'Accepted'  /* Only accepted quotations */
                           )";
            
            $result = $conn->query($getOldApps);
            if ($result) {
                while ($app = $result->fetch_assoc()) {
                    // Only delete if it's not a rejected application
                    if ($app['status'] !== 'Rejected') {
                        // Delete quotations first
                        $deleteQuotes = $conn->prepare("DELETE FROM quotations WHERE applications_id = ?");
                        $deleteQuotes->bind_param("i", $app['applications_id']);
                        $deleteQuotes->execute();
                        
                        // Then delete the application
                        $deleteApp = $conn->prepare("DELETE FROM applications WHERE applications_id = ?");
                        $deleteApp->bind_param("i", $app['applications_id']);
                        $deleteApp->execute();
                    }
                }
            }
            
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Error cleaning up old applications: " . $e->getMessage());
        }
    }

    // Call the cleanup function
    cleanupOldApplications($conn);

    // Update the jobs query to properly show available jobs
    $jobs_query = "SELECT j.*, jt.jobType, u.username as employer_name, 
        COUNT(DISTINCT a.applications_id) as application_count,
        CASE 
            WHEN EXISTS (
                SELECT 1 
                FROM applications a2 
                WHERE a2.jobId = j.jobs_id 
                AND a2.userId = ?
                AND a2.status NOT IN ('Rejected')
            ) THEN 1 
            ELSE 0 
        END as has_applied
    FROM jobs j
    LEFT JOIN job_type jt ON j.jobTypeID = jt.jobTypeID
    LEFT JOIN users u ON j.employerId = u.users_id
    LEFT JOIN applications a ON j.jobs_id = a.jobid
    WHERE j.status = 'Open' 
    AND j.is_onlyavailable = TRUE
    AND (
        NOT EXISTS (
            SELECT 1
            FROM applications a4
            WHERE a4.jobid = j.jobs_id
            AND a4.userId = ?
            AND a4.status NOT IN ('Rejected')
        )
    )
    AND NOT EXISTS (
        SELECT 1 
        FROM applications a3
        JOIN quotations q ON a3.applications_id = q.applications_id 
        WHERE a3.jobid = j.jobs_id 
        AND q.status = 'Accepted'
    )
    GROUP BY j.jobs_id
    ORDER BY j.postedDate DESC";

    // Debug the query
    error_log("Jobs Query: " . $jobs_query);

    $stmt = $conn->prepare($jobs_query);
    $stmt->bind_param("ii", $_SESSION['user_id'], $_SESSION['user_id']);
    $stmt->execute();
    $jobs = $stmt->get_result();

    // Add debug logging
    error_log("Number of jobs found: " . $jobs->num_rows);

    // Debug the first job if any exist
    if ($jobs->num_rows > 0) {
        $first_job = $jobs->fetch_assoc();
        error_log("First job details: " . print_r($first_job, true));
        $jobs->data_seek(0); // Reset the pointer
    }

    // Before the jobs listing HTML, add this debug output
    if (isset($_SESSION['user_id'])) {
        error_log("User is logged in with ID: " . $_SESSION['user_id']);
    } else {
        error_log("No user_id in session");
    }

    // Update the applied jobs query to show jobs with quotations
    $applied_jobs_query = "SELECT 
        j.*, 
        a.applications_id,
        a.status as application_status,
        a.applicationDate,
        a.proposal,
        u.username as employer_name,
        latest_q.status as quotation_status,
        latest_q.quotations_id,
        latest_q.price,
        latest_q.description as quotation_description
    FROM applications a
    JOIN jobs j ON a.jobid = j.jobs_id
    JOIN users u ON j.employerId = u.users_id
    LEFT JOIN (
        SELECT q.*
        FROM quotations q
        INNER JOIN (
            SELECT applications_id, MAX(quotations_id) as latest_quotation_id
            FROM quotations
            GROUP BY applications_id
        ) latest ON q.quotations_id = latest.latest_quotation_id
    ) latest_q ON a.applications_id = latest_q.applications_id
    WHERE a.userid = ? 
    AND a.status NOT IN ('Rejected')
    AND NOT EXISTS (
        SELECT 1 
        FROM quotations q2 
        WHERE q2.applications_id = a.applications_id 
        AND q2.status = 'Accepted'
    )
    ORDER BY a.applicationDate DESC";

    // Use prepared statement for security
    $stmt = $conn->prepare($applied_jobs_query);
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $appliedJobs = $stmt->get_result();

    if (!$appliedJobs) {
        die("Error fetching applied jobs: " . $conn->error);
    }
    ?>

    <!DOCTYPE html>
    <html lang="en-US" dir="ltr" class="chrome windows">

    <head>
    <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>RaketHero | Find Your Next Job</title>

        <!-- Favicons -->
        <link rel="apple-touch-icon" sizes="180x180" href="assets/img/favicons/apple-touch-icon.png">
        <link rel="icon" type="image/png" sizes="32x32" href="assets/img/favicons/favicon.ico">
        <link rel="icon" type="image/png" sizes="16x16" href="assets/img/favicons/favicon.ico">
        <link rel="shortcut icon" type="image/x-icon" href="assets/img/favicons/favicon.ico">
        <link rel="manifest" href="assets/img/favicons/manifest.json">
        <meta name="msapplication-TileImage" content="assets/img/favicons/favicon.ico">
        <meta name="theme-color" content="#ffffff">

        <!-- Stylesheets -->
        <link href="assets/css/theme.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">

        <style>
        /* Job Card Styling */
        .job-card {
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border: 1px solid #e0e0e0;
        }

        .job-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .job-card h5 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-weight: 600;
        }

        .job-card p {
            color: #555;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }

        .job-card strong {
            color: #2c3e50;
            font-weight: 600;
        }

        .job-card .proposal-text {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border: 1px solid #e9ecef;
            color: #495057;
        }

        .job-card .button-container {
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }

        .job-card .btn {
            padding: 8px 20px;
            border-radius: 5px;
            font-weight: 500;
            text-transform: none;
            font-size: 0.9rem;
        }

        .job-card .btn-primary {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }

        .job-card .btn-warning {
            background-color: #ffc107;
            border-color: #ffc107;
            color: #000;
        }

        .job-card .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
        }

        .job-card .btn-info {
            background-color: #17a2b8;
            border-color: #17a2b8;
            color: #fff;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
        }

        .status-badge.pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-badge.accepted {
            background-color: #d4edda;
            color: #155724;
        }

        .status-badge.rejected {
            background-color: #f8d7da;
            color: #721c24;
        }

        /* Section Styling */
        .section-header {
            margin-bottom: 30px;
            color: #2c3e50;
        }

        /* Container Spacing */
        .container {
            padding-top: 30px;
            padding-bottom: 30px;
        }

        /* Add these to your existing style section */
        .job-details {
            padding: 10px 0;
        }

        .proposal-section {
            margin: 15px 0;
        }

        .quotation-section {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border: 1px solid #e9ecef;
        }

        .job-content {
            width: 100%;
        }

        /* Update status badge styles */
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
            margin-left: 5px;
        }

        .status-badge.pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-badge.accepted {
            background-color: #d4edda;
            color: #155724;
        }

        .status-badge.rejected {
            background-color: #f8d7da;
            color: #721c24;
        }

        /* Button container consistency */
        .button-container {
            margin-top: 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        /* Consistent button styling */
        .button-container .btn {
            padding: 8px 20px;
            border-radius: 5px;
            font-weight: 500;
            text-transform: none;
            font-size: 0.9rem;
            min-width: 120px;
        }

        /* Add to your existing styles */
        .bi-check-circle {
            color: #28a745;
        }

        #successModal .modal-header {
            border-bottom: none;
        }

        #successModal .modal-body {
            padding: 2rem;
        }

        #successMessage {
            font-size: 1.1rem;
            color: #28a745;
        }

        /* Add to your existing style section */
        .form-select {
            border-radius: 5px;
            border: 1px solid #ced4da;
            padding: 0.375rem 2.25rem 0.375rem 0.75rem;
        }

        .form-label {
            font-weight: 500;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        #resetFilters {
            height: 38px;
            border-radius: 5px;
        }

        .filter-section {
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }

        /* Price range inputs */
        #minPrice, #maxPrice {
            width: 50%;
            border-radius: 5px;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .col-md-4 .d-flex {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            #minPrice, #maxPrice {
                width: 100%;
            }
        }

        /* Search and Filter Styles */
        #searchGigInput {
            background-color: #f8f9fa;
            padding: 19px;
            border-radius: 8px;
            margin-top: -8px;
            margin-right: 24px;
        }

        .form-select {
            border-radius: 5px;
            border: 1px solid #ced4da;
            padding: 0.375rem 2.25rem 0.375rem 0.75rem;
        }

        .form-label {
            font-weight: 500;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        #resetFilters {
            height: 38px;
            border-radius: 5px;
        }

        .filter-section {
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }

        /* Price range inputs */
        #minPrice, #maxPrice {
            width: 50%;
            border-radius: 5px;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .col-md-4 .d-flex {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            #minPrice, #maxPrice {
                width: 100%;
            }
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }

        .notification-item {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #eee;
            transition: background-color 0.2s;
        }

        .notification-item:hover {
            background-color: #f8f9fa;
        }

        .notification-item.unread {
            background-color: #e8f4ff;
        }

        .notification-item.unread:hover {
            background-color: #d8ecff;
        }

        .notification-dropdown {
            width: 350px;
            max-height: 400px;
            overflow-y: auto;
        }

        .dropdown-header {
            background-color: #f8f9fa;
            font-weight: bold;
            padding: 0.5rem 1rem;
        }
        </style>
    </head>

    <body>
        <main class="main" id="top">
            <!-- Navbar -->
            <nav class="navbar navbar-expand-lg navbar-light bg-light shadow fixed-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#">
                <span class="text-info">Raket</span><span class="text-warning">Hero</span>
            </a>
            <!-- Navbar Toggler -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <!-- Navbar Links -->
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item px-2 dropdown">
                        <a class="nav-link fw-medium position-relative" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-bell"></i>
                            <?php
                            // Get unread notifications count for jobseeker
                            $stmt = $conn->prepare("
                                SELECT COUNT(*) 
                                FROM notifications 
                                WHERE user_id = ? 
                                AND is_read = 0 
                                AND type IN ('proposal_accepted', 'proposal_rejected', 'application_rejected')
                            ");

                            if (!$stmt) {
                                error_log("Prepare failed: " . $conn->error);
                                $unreadCount = 0;
                            } else {
                                $stmt->bind_param("i", $_SESSION['user_id']);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                $unreadCount = $result->fetch_row()[0];
                            }

                            if ($unreadCount > 0) {
                                echo "<span class='notification-badge'>$unreadCount</span>";
                            }
                            ?>
                        </a>
                        <div class="dropdown-menu notification-dropdown" aria-labelledby="notificationDropdown">
                            <h6 class="dropdown-header">Notifications</h6>
                            <?php
                            // Get notifications
                            $stmt = $conn->prepare("
                                SELECT n.*, 
                                       j.title as job_title, 
                                       u.username as employer_name,
                                       n.created_at,
                                       n.is_read,
                                       n.type,
                                       n.message
                                FROM notifications n 
                                LEFT JOIN jobs j ON j.jobs_id = n.job_id
                                LEFT JOIN users u ON u.users_id = n.from_user_id
                                WHERE n.user_id = ? 
                                AND n.type IN ('proposal_accepted', 'proposal_rejected', 'application_rejected')  /* Added application_rejected */
                                ORDER BY n.created_at DESC 
                                LIMIT 10
                            ");

                            if (!$stmt) {
                                error_log("Prepare failed: " . $conn->error);
                                echo "<div class='dropdown-item'>Error loading notifications</div>";
                            } else {
                                $stmt->bind_param("i", $_SESSION['user_id']);
                                $stmt->execute();
                                $notifications = $stmt->get_result();

                                if ($notifications && $notifications->num_rows > 0) {
                                    while ($notification = $notifications->fetch_assoc()) {
                                        $unreadClass = $notification['is_read'] ? '' : 'unread';
                                        echo "<div class='notification-item $unreadClass'>";
                                        
                                        // Format message based on notification type
                                        switch ($notification['type']) {
                                            case 'proposal_accepted':
                                                $employerName = htmlspecialchars($notification['employer_name'] ?? 'An employer');
                                                $jobTitle = htmlspecialchars($notification['job_title'] ?? 'a job');
                                                echo "<strong>{$employerName}</strong> accepted your application for <strong>{$jobTitle}</strong>";
                                                break;
                                                
                                            case 'proposal_rejected':
                                            case 'application_rejected':
                                                $employerName = htmlspecialchars($notification['employer_name'] ?? 'An employer');
                                                $jobTitle = htmlspecialchars($notification['job_title'] ?? 'a job');
                                                echo "<strong>{$employerName}</strong> declined your application for <strong>{$jobTitle}</strong>";
                                                break;
                                                
                                            default:
                                                echo htmlspecialchars($notification['message']);
                                                break;
                                        }
                                        
                                        echo "<div class='small text-muted mt-1'>" . date('M j, Y g:i A', strtotime($notification['created_at'])) . "</div>";
                                        echo "</div>";
                                    }
                                } else {
                                    echo "<div class='dropdown-item'>No notifications</div>";
                                }
                            }
                            ?>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-center small text-muted" href="#" onclick="markAllAsRead()">Mark all as read</a>
                        </div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="#home">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#jobs">Available Jobs</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#applied-jobs">Your Applied Jobs</a>
                    </li>
                    <li class="nav-item px-2"><a class="nav-link fw-medium" href="profile.php">Profile</a></li>   
                    <li class="nav-item">
                        <a class="nav-link" href="?action=logout">Logout</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="transactions.php">Transaction History</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

            <!-- Gig Search Section -->
            <section class="py-5 mt-5" id="gig-search">
                <div class="container">
                    <h4 class="mb-4">Search for Gigs</h4>
                    <!-- Search and Filter Form -->
                    <form class="form-inline mb-4" id="searchFilterForm">
                        <!-- Search Bar -->
                        <div class="input-group w-100 mb-3">
                            <input type="text" class="form-control rounded-start" id="searchGigInput" placeholder="Search by title or category">
                            <button class="btn btn-primary" type="button" onclick="applyFilters()">Search</button>
                        </div>

                        <!-- Filters Row -->
                        <div class="row g-3">
                            <!-- Pay Type Filter -->
                            <div class="col-md-3">
                                <label class="form-label">Pay Type</label>
                                <select class="form-select" id="payTypeFilter">
                                    <option value="">All Pay Types</option>
                                    <option value="Pay by day">Pay by Day</option>
                                    <option value="Pay by hour">Pay by Hour</option>
                                    <option value="Pay by project">Pay by Project</option>
                                    <option value="Pay by week">Pay by Week</option>
                                </select>
                            </div>

                            <!-- Price Range Filters -->
                            <div class="col-md-3">
                                <label class="form-label">Min Price (PHP)</label>
                                <input type="number" class="form-control" id="minPrice" placeholder="Min Price">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Max Price (PHP)</label>
                                <input type="number" class="form-control" id="maxPrice" placeholder="Max Price">
                            </div>

                            <!-- Location Filter -->
                            <div class="col-md-3">
                                <label class="form-label">Location</label>
                                <input type="text" class="form-control" id="locationFilter" placeholder="Enter location">
                            </div>
                        </div>
                    </form>
                </div>
            </section>

            <!-- Available Jobs Section -->
            <section class="py-5" id="jobs">
                <div class="container">
                    <h4 class="section-header">Available Jobs</h4>
                    <div class="job-listing">
                        <?php 
                        if (!isset($_SESSION['user_id'])) {
                            echo '<div class="alert alert-warning">Not logged in</div>';
                        }
                        
                        if (!isset($jobs)) {
                            echo '<div class="alert alert-danger">$jobs variable not set</div>';
                        } else if (!$jobs) {
                            echo '<div class="alert alert-danger">Query failed</div>';
                        } else if ($jobs->num_rows === 0) {
                            echo '<div class="alert alert-info">No available jobs found at the moment.</div>';
                        } else {
                            while ($job = $jobs->fetch_assoc()): 
                                // Debug each job
                                error_log("Processing job: " . print_r($job, true));
                        ?>
                            <div class="job-card" 
                                data-title="<?php echo htmlspecialchars($job['title']); ?>" 
                                data-description="<?php echo htmlspecialchars($job['description']); ?>"
                                data-jobid="<?php echo htmlspecialchars($job['jobs_id']); ?>"
                                data-username="<?php echo htmlspecialchars($job['employer_name']); ?>"
                                data-location="<?php echo htmlspecialchars($job['location']); ?>"
                                data-posteddate="<?php echo htmlspecialchars($job['postedDate']); ?>"
                                data-price="<?php echo htmlspecialchars($job['price']); ?>"
                                data-paytype="<?php echo htmlspecialchars($job['payType']); ?>"
                                data-jobtype="<?php echo htmlspecialchars($job['jobType']); ?>"
                                data-status="Open">
                                <div class="job-content">
                                    <h5><?php echo htmlspecialchars($job['title']); ?></h5>
                                    <p><strong>Posted by:</strong> <?php echo htmlspecialchars($job['employer_name']); ?></p>
                                    <p><strong>Location:</strong> <?php echo htmlspecialchars($job['location']); ?></p>
                                    <p><strong>Pay:</strong> PHP <?php echo htmlspecialchars($job['price']); ?> (<?php echo htmlspecialchars($job['payType']); ?>)</p>
                                    <p><strong>Job Type:</strong> <?php echo htmlspecialchars($job['jobType']); ?></p>
                                    <p><strong>Description:</strong> <?php echo htmlspecialchars($job['description']); ?></p>
                                    <div class="button-container">
                                        <button class="btn btn-primary apply-btn">Apply Now</button>
                                    </div>
                                </div>
                            </div>
                        <?php 
                            endwhile;
                        } 
                        ?>
                    </div>
                </div>
            </section>

            <!-- Modal Structure -->
            <div class="modal fade" id="jobDetailsModal" tabindex="-1" aria-labelledby="jobDetailsModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalJobTitle"></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p><strong>Status:</strong> <span id="modalJobStatus"></span></p>
                            <p><strong>Description:</strong> <span id="modalJobDescription"></span></p>
                            <p><strong>Employer Name:</strong> <span id="modalJobUsername"></span></p>
                            <p><strong>Location:</strong> <span id="modalJobLocation"></span></p>
                            <p><strong>Posted Date:</strong> <span id="modalJobPostedDate"></span></p>
                            <p><strong>Price:</strong> <span id="modalJobPrice"></span></p>
                            <p><strong>Pay Type:</strong> <span id="modalJobPayType"></span></p>
                            <p><strong>Job Type:</strong> <span id="modalJobType"></span></p>
                            <!-- Proposal Textarea -->
                            <div class="mb-3 mt-4">
                                <label for="proposalText"><strong>Your Proposal:</strong></label>
                                <textarea class="form-control" id="proposalText" rows="5" placeholder="Explain why you're the best fit..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-primary" id="submitProposal">Submit Proposal</button>
                            <button type="button" class="btn btn-success" id="editProposal" style="display:none;">Edit Proposal</button>
                            <button type="button" class="btn btn-danger" id="deleteApplication" style="display:none;">Delete Application</button>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Applied Jobs Section -->
            <section class="py-5 bg-light" id="applied-jobs">
        <div class="container">
            <h4 class="section-header">Your Applied Jobs</h4>
            <div class="job-listing">
                <?php if ($appliedJobs && $appliedJobs->num_rows > 0): ?>
                    <?php while ($job = $appliedJobs->fetch_assoc()): ?>
                        <div class="job-card" data-jobid="<?php echo htmlspecialchars($job['jobs_id']); ?>" data-quotation-id="<?php echo htmlspecialchars($job['quotations_id']); ?>">
                            <div class="job-content">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h5 class="mb-1"><?php echo htmlspecialchars($job['title']); ?></h5>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="status-badge <?php echo strtolower($job['application_status']); ?>">
                                                <?php echo htmlspecialchars($job['application_status']); ?>
                                            </span>
                                            <span class="text-muted">•</span>
                                            <span class="text-muted">Applied on <?php echo date('M j, Y', strtotime($job['applicationDate'])); ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="proposal-section bg-white p-3 rounded border mb-3">
                                    <h6 class="fw-bold mb-2">Your Proposal</h6>
                                    <p class="mb-0 text-secondary">
                                        <?php echo nl2br(htmlspecialchars($job['proposal'] ?? '')); ?>
                                    </p>
                                </div>

                                <?php if (isset($job['quotations_id'])): ?>
                                    <div class="quotation-section bg-light p-3 rounded border mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h6 class="fw-bold mb-0">Quotation Details</h6>
                                            <span class="status-badge <?php echo strtolower($job['quotation_status'] ?? 'pending'); ?>">
                                                <?php echo htmlspecialchars($job['quotation_status'] ?? 'Pending'); ?>
                                            </span>
                                        </div>
                                        <p class="mb-2"><strong>Amount:</strong> PHP <?php echo number_format($job['price'], 2); ?></p>
                                    </div>
                                <?php endif; ?>

                                <div class="button-container">
                                    <?php if (isset($job['quotations_id'])): ?>
                                        <button class="btn btn-primary btn-sm" onclick="viewQuotation(
                                            '<?php echo addslashes($job['title']); ?>',
                                            '<?php echo $job['quotations_id']; ?>',
                                            '<?php echo number_format($job['price'], 2); ?>',
                                            '<?php echo addslashes($job['quotation_description'] ?? 'No description provided'); ?>',
                                            '<?php echo addslashes($job['quotation_status'] ?? 'Pending'); ?>',
                                            '<?php echo $job['jobs_id']; ?>'
                                        )">View Details</button>
                                        <button class="btn btn-danger btn-sm delete-application" 
                                                data-application-id="<?php echo htmlspecialchars($job['applications_id']); ?>">
                                            Cancel Job
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-warning btn-sm edit-proposal" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editProposalModal"
                                                data-application-id="<?php echo htmlspecialchars($job['applications_id']); ?>"
                                                data-proposal="<?php echo htmlspecialchars($job['proposal'] ?? ''); ?>">
                                            Edit Proposal
                                        </button>
                                        <button class="btn btn-danger btn-sm delete-application" 
                                                data-application-id="<?php echo htmlspecialchars($job['applications_id']); ?>">
                                            Delete Application
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="alert alert-info">You haven't applied to any jobs yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Add Quotation Modal -->
    <div class="modal fade" id="quotationModal" tabindex="-1" aria-labelledby="quotationModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="quotationModalLabel">Quotation Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h6 id="quotationJobTitle" class="mb-3"></h6>
                    <p><strong>Amount:</strong> PHP <span id="quotationAmount"></span></p>
                    <p><strong>Description:</strong></p>
                    <p id="quotationDescription"></p>
                    <p><strong>Status:</strong> <span id="quotationStatus"></span></p>
                </div>
                <div class="modal-footer" id="quotationActions">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success" id="acceptQuotationBtn">Accept Quotation</button>
                    <button type="button" class="btn btn-danger" id="rejectQuotationBtn">Reject Quotation</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Update the quotation view modal -->
    <div class="modal fade" id="quotationViewModal" tabindex="-1" aria-labelledby="quotationViewModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="quotationViewModalLabel">Quotation Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h6 id="quotationJobTitle" class="mb-3"></h6>
                    <div class="quotation-details">
                        <p><strong>Amount:</strong> PHP <span id="quotationAmount">0.00</span></p>
                        <p><strong>Description:</strong></p>
                        <div id="quotationDescription" class="p-3 bg-light rounded"></div>
                        <p class="mt-3"><strong>Status:</strong> <span id="quotationStatus" class="badge bg-info">Pending</span></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <input type="hidden" id="currentQuotationId">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success" id="acceptQuotationBtn">Accept Quotation</button>
                    <button type="button" class="btn btn-danger" id="rejectQuotationBtn">Reject Quotation</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Proposal Modal -->
    <div class="modal fade" id="editProposalModal" tabindex="-1" aria-labelledby="editProposalModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editProposalModalLabel">Edit Proposal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editProposalText" class="form-label">Your Proposal</label>
                        <textarea class="form-control" id="editProposalText" rows="5"></textarea>
                        <input type="hidden" id="editApplicationId">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveProposalEdit">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="successModalLabel">Success!</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
                    <p class="mt-3 mb-0" id="successMessage"></p>
                </div>
            </div>
        </div>
    </div>
        </main>
    <!-- JavaScripts -->
    <script src="vendors/@popperjs/popper.min.js"></script>
        <script src="vendors/bootstrap/bootstrap.min.js"></script>
        <script src="vendors/is/is.min.js"></script>
        <script src="assets/js/theme.js"></script>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Job filtering function
                function filterJobs() {
                    const searchText = document.getElementById('searchGigInput')?.value.toLowerCase() || '';
                    const minPrice = parseFloat(document.getElementById('minPrice')?.value) || 0;
                    const maxPrice = parseFloat(document.getElementById('maxPrice')?.value) || Infinity;
                    const location = document.getElementById('locationFilter')?.value.toLowerCase() || '';
                    const payType = document.getElementById('payTypeFilter')?.value.toLowerCase() || '';

                    document.querySelectorAll('.job-card').forEach(card => {
                        const title = card.querySelector('h5')?.textContent.toLowerCase() || '';
                        const jobType = card.dataset.jobtype?.toLowerCase() || '';
                        const cardPrice = parseFloat(card.querySelector('p:nth-of-type(3)')?.textContent.match(/PHP\s+(\d+(\.\d+)?)/)?.[1]) || 0;
                        const cardLocation = card.querySelector('p:nth-of-type(2)')?.textContent.toLowerCase() || '';
                        const cardPayType = card.querySelector('p:nth-of-type(3)')?.textContent.toLowerCase() || '';

                        const matchesSearch = searchText === '' || title.includes(searchText) || jobType.includes(searchText);
                        const matchesPrice = (!minPrice || cardPrice >= minPrice) && (!maxPrice || cardPrice <= maxPrice);
                        const matchesLocation = !location || cardLocation.includes(location);
                        const matchesPayType = !payType || cardPayType.includes(payType);

                        if (matchesSearch && matchesPrice && matchesLocation && matchesPayType) {
                            card.style.display = 'block';
                        } else {
                            card.style.display = 'none';
                        }
                    });
                }

                // Add event listeners only if elements exist
                const searchInput = document.getElementById('searchGigInput');
                const minPriceInput = document.getElementById('minPrice');
                const maxPriceInput = document.getElementById('maxPrice');
                const locationFilter = document.getElementById('locationFilter');
                const payTypeFilter = document.getElementById('payTypeFilter');
                const resetFiltersBtn = document.getElementById('resetFilters');

                if (searchInput) searchInput.addEventListener('input', filterJobs);
                if (minPriceInput) minPriceInput.addEventListener('input', filterJobs);
                if (maxPriceInput) maxPriceInput.addEventListener('input', filterJobs);
                if (locationFilter) locationFilter.addEventListener('input', filterJobs);
                if (payTypeFilter) payTypeFilter.addEventListener('change', filterJobs);

                // Reset filters
                if (resetFiltersBtn) {
                    resetFiltersBtn.addEventListener('click', function() {
                        if (searchInput) searchInput.value = '';
                        if (minPriceInput) minPriceInput.value = '';
                        if (maxPriceInput) maxPriceInput.value = '';
                        if (locationFilter) locationFilter.value = '';
                        if (payTypeFilter) payTypeFilter.value = '';
                        
                        // Show all jobs
                        document.querySelectorAll('.job-card').forEach(card => {
                            card.style.display = 'block';
                        });
                    });
                }

                // Update price range validation
                if (maxPriceInput) {
                    maxPriceInput.addEventListener('input', function() {
                        const minPrice = parseFloat(minPriceInput?.value) || 0;
                        const maxPrice = parseFloat(this.value) || 0;
                        
                        if (maxPrice !== 0 && minPrice !== 0 && maxPrice < minPrice) {
                            alert('Maximum price should be greater than minimum price');
                            this.value = '';
                        }
                    });
                }

                if (minPriceInput) {
                    minPriceInput.addEventListener('input', function() {
                        const maxPrice = parseFloat(maxPriceInput?.value) || 0;
                        const minPrice = parseFloat(this.value) || 0;
                        
                        if (maxPrice !== 0 && minPrice !== 0 && minPrice > maxPrice) {
                            alert('Minimum price should be less than maximum price');
                            this.value = '';
                        }
                    });
                }
            });

            document.querySelectorAll('.apply-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const card = this.closest('.job-card');



                    
                    
                    // Set modal content using dataset values
                    document.getElementById('modalJobTitle').textContent = card.dataset.title || 'N/A';
                    document.getElementById('modalJobStatus').textContent = card.dataset.status || 'N/A';
                    document.getElementById('modalJobDescription').textContent = card.dataset.description || 'N/A';
                    document.getElementById('modalJobUsername').textContent = card.dataset.username || 'N/A';
                    document.getElementById('modalJobLocation').textContent = card.dataset.location || 'N/A';
                    document.getElementById('modalJobPostedDate').textContent = card.dataset.posteddate || 'N/A';
                    document.getElementById('modalJobPrice').textContent = `PHP ${card.dataset.price || 'N/A'}`;
                    document.getElementById('modalJobPayType').textContent = card.dataset.paytype || 'N/A';
                    document.getElementById('modalJobType').textContent = card.dataset.jobtype || 'N/A';
                    
                    // Set the job ID for the submit proposal button
                    document.getElementById('submitProposal').dataset.jobId = card.dataset.jobid;

                    // Clear any existing proposal text
                    document.getElementById('proposalText').value = '';

                    // Show the modal
                    const modal = new bootstrap.Modal(document.getElementById('jobDetailsModal'));
                    modal.show();
                });
            });
            document.getElementById('submitProposal').addEventListener('click', function () {
        const jobId = this.dataset.jobId;
        const proposal = document.getElementById('proposalText').value.trim();
        const job_seeker_id = <?php echo $_SESSION['user_id']; ?>; // Changed variable name to match backend

        if (proposal === "") {
            alert("Please write a proposal before submitting.");
            return;
        }

        fetch('applyjobs.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `jobId=${jobId}&proposal=${encodeURIComponent(proposal)}&job_seeker_id=${job_seeker_id}` // Changed parameter name
        })
            .then(response => response.text())
            .then(data => {
                if (data.includes('successfully')) {
                    // Show success message
                    alert(data);
                    
                    // Close the modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('jobDetailsModal'));
                    modal.hide();

                    // Save the applied job in localStorage
                    const appliedJobs = JSON.parse(localStorage.getItem('appliedJobs') || '[]');
                    appliedJobs.push(jobId);
                    localStorage.setItem('appliedJobs', JSON.stringify(appliedJobs));

                    // Force a complete page reload
                    window.location.href = window.location.href.split('#')[0] + '#applied-jobs';
                    window.location.reload(true);
                } else {
                    alert(data);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error submitting proposal');
            });
    });

    // Add this code at the beginning of your script section
    document.addEventListener('DOMContentLoaded', function() {
        // Get applied jobs from localStorage
        const appliedJobs = JSON.parse(localStorage.getItem('appliedJobs') || '[]');
        
        // Hide already applied jobs from Available Jobs section
        appliedJobs.forEach(jobId => {
            const jobCard = document.querySelector(`#jobs .job-card[data-jobid="${jobId}"]`);
            if (jobCard) {
                jobCard.remove();
            }
        });
    });

    // Update the loadAppliedJobs function
    function loadAppliedJobs() {
        fetch('fetchAppliedJobs.php')
        .then(response => response.text())
        .then(html => {
            const appliedJobsSection = document.querySelector('#applied-jobs .job-listing');
            appliedJobsSection.innerHTML = html;
            attachViewDetailsListeners();
            
            // Update localStorage with current applied jobs
            const appliedJobCards = document.querySelectorAll('#applied-jobs .job-card');
            const appliedJobIds = Array.from(appliedJobCards).map(card => card.dataset.jobid);
            localStorage.setItem('appliedJobs', JSON.stringify(appliedJobIds));
        })
        .catch(error => console.error('Error:', error));
    }

    // Function to reattach event listeners for dynamically added "Apply" buttons
    function attachViewDetailsListeners() {
        document.querySelectorAll('.view-details-btn').forEach(button => {
            button.addEventListener('click', function () {
                const card = this.closest('.job-card');
                document.getElementById('modalJobTitle').innerText = card.dataset.title;
                document.getElementById('proposalText').value = card.dataset.proposal;
                document.getElementById('submitProposal').style.display = 'none'; // Hide submit button
                document.getElementById('editProposal').style.display = 'inline-block';
                document.getElementById('deleteApplication').style.display = 'inline-block'; // Show delete button
                document.getElementById('editProposal').dataset.jobId = card.dataset.jobid;
                document.getElementById('deleteApplication').dataset.jobId = card.dataset.jobid; // Assign jobId to delete button

                const modal = new bootstrap.Modal(document.getElementById('jobDetailsModal'));
                modal.show();
            });
        });
    }

    // Call the function when the page initially loads
    document.addEventListener('DOMContentLoaded', () => {
        attachViewDetailsListeners();
    });
    // Edit proposal functionality
    document.getElementById('editProposal').addEventListener('click', function() {
        const jobId = this.dataset.jobId;
        const newProposal = document.getElementById('proposalText').value.trim();

        if (newProposal === "") {
            alert("Please write a proposal before updating.");
            return;
        }

        fetch('editproposal.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `jobId=${jobId}&proposal=${encodeURIComponent(newProposal)}`
        })
        .then(response => response.text())
        .then(data => {
            alert(data);
            location.reload(); // Refresh to reflect changes
        })
        .catch(error => console.error('Error:', error));
    });


    //delete application
    // Show Delete Button in 'View Details' Modal
    document.querySelectorAll('.view-details-btn').forEach(button => {
        button.addEventListener('click', function() {
            const card = this.closest('.job-card');
            document.getElementById('modalJobTitle').innerText = card.dataset.title;
            document.getElementById('proposalText').value = card.dataset.proposal;
            document.getElementById('submitProposal').style.display = 'none'; // Hide submit button
            document.getElementById('editProposal').style.display = 'inline-block';
            document.getElementById('deleteApplication').style.display = 'inline-block'; // Show delete button
            document.getElementById('editProposal').dataset.jobId = card.dataset.jobid;
            document.getElementById('deleteApplication').dataset.jobId = card.dataset.jobid; // Assign jobId to delete button

            const modal = new bootstrap.Modal(document.getElementById('jobDetailsModal'));
            modal.show();
        });
    });

    // Handle Deletion
    document.getElementById('deleteApplication').addEventListener('click', function() {
        const jobId = this.dataset.jobId;

        if (!confirm("Are you sure you want to delete this application?")) {
            return;
        }

        // Create form data
        const formData = new FormData();
        formData.append('jobId', jobId);

        // Send AJAX request to delete the application
        fetch('deleteapplication.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            alert(data); // Show response message
            if (data.includes("successfully")) {
                // Close the modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('jobDetailsModal'));
                modal.hide();
                cleanupModal();
                
                // Remove the job from Applied Jobs section
                const appliedJobCard = document.querySelector(`#applied-jobs .job-card[data-jobid="${jobId}"]`);
                if (appliedJobCard) {
                    appliedJobCard.remove();
                }

                // Remove from localStorage
                const appliedJobs = JSON.parse(localStorage.getItem('appliedJobs') || '[]');
                const updatedAppliedJobs = appliedJobs.filter(id => id !== jobId);
                localStorage.setItem('appliedJobs', JSON.stringify(updatedAppliedJobs));

                // Reload the page to refresh both sections
                location.reload();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error deleting application');
        });
    });


    document.getElementById('searchGigInput').addEventListener('keyup', function() {
    const filter = this.value.toLowerCase();
    const gigCards = document.querySelectorAll('.gig-card');

    gigCards.forEach(card => {
        const title = card.getAttribute('data-title').toLowerCase();
        const category = card.getAttribute('data-category').toLowerCase();

        if (title.includes(filter) || category.includes(filter)) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
});

function markAllAsRead() {
    fetch('mark_notifications_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove the notification badge
            const badge = document.querySelector('.notification-badge');
            if (badge) {
                badge.remove();
            }

            // Remove unread class from all notification items
            document.querySelectorAll('.notification-item').forEach(item => {
                item.classList.remove('unread');
            });

            // Optional: Show a success message
            console.log('All notifications marked as read');
            
            // Refresh the notifications dropdown
            location.reload();
        } else {
            console.error('Failed to mark notifications as read:', data.message);
        }
    })
    .catch(error => {
        console.error('Error marking notifications as read:', error);
    });
}

// Add event listener for the "Mark all as read" link
document.addEventListener('DOMContentLoaded', function() {
    const markAllAsReadLink = document.querySelector('.dropdown-item[onclick="markAllAsRead()"]');
    if (markAllAsReadLink) {
        markAllAsReadLink.addEventListener('click', function(e) {
            e.preventDefault();
            markAllAsRead();
        });
    }
});

// Auto-update notifications
setInterval(() => {
    fetch('get_notifications.php?user_id=<?php echo $_SESSION['user_id']; ?>')
    .then(response => response.json())
    .then(data => {
        // Update notification badge
        if (data.unreadCount > 0) {
            let badge = document.querySelector('.notification-badge');
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'notification-badge';
                document.querySelector('#notificationDropdown').appendChild(badge);
            }
            badge.textContent = data.unreadCount;
        }
    });
}, 30000); // Check every 30 seconds

function viewQuotation(title, quotationId, price, description, status, jobId) {
    // Redirect to the quotation details page
    window.location.href = `view_quotation.php?quotation_id=${quotationId}&job_id=${jobId}`;
}

// Update the accept button click handler
document.getElementById('acceptQuotationBtn').addEventListener('click', function() {
    const quotationId = document.getElementById('currentQuotationId').value;
    const jobId = document.querySelector('#quotationViewModal').dataset.jobId;

    console.log('Accept clicked:', { quotationId, jobId }); // Debug log

    if (confirm('Are you sure you want to accept this quotation? This will close the job.')) {
        console.log('Sending request with data:', {
            quotationId: quotationId,
            action: 'accept',
            jobId: jobId
        });

        fetch('handle_quotation_response.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `quotationId=${quotationId}&action=accept&jobId=${jobId}`
        })
        .then(response => {
            console.log('Raw response:', response);
            return response.text(); // Change to text() first to see raw response
        })
        .then(rawData => {
            console.log('Raw data:', rawData);
            try {
                const data = JSON.parse(rawData);
                console.log('Parsed data:', data);
                if (data.success) {
                    // Close the quotation modal
                    const quotationModal = bootstrap.Modal.getInstance(document.getElementById('quotationViewModal'));
                    quotationModal.hide();

                    // Show success message
                    const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                    document.getElementById('successMessage').textContent = 'Job accepted! The job has been closed.';
                    successModal.show();

                    // Reload the page after a short delay
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    alert(data.message || 'Error accepting quotation');
                }
            } catch (e) {
                console.error('Error parsing JSON:', e);
                console.log('Raw response that failed to parse:', rawData);
                alert('Error processing response from server');
            }
        })
        .catch(error => {
            console.error('Network Error:', error);
            alert('Error accepting quotation: ' + error.message);
        });
    }
});

document.getElementById('rejectQuotationBtn').addEventListener('click', function() {
    const quotationId = document.getElementById('currentQuotationId').value;
    handleQuotationResponse(quotationId, 'reject');
});

function handleQuotationResponse(quotationId, action) {
    if (!confirm(`Are you sure you want to ${action} this quotation?`)) {
        return;
    }

    fetch('handle_quotation_response.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `quotationId=${quotationId}&action=${action}`
    })
    .then(response => response.text())
    .then(data => {
        alert(data);
        if (data.includes('success')) {
            // Close the modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('quotationViewModal'));
            modal.hide();
            // Refresh the page
            window.location.reload();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert(`Error ${action}ing quotation`);
    });
}

// Add this to ensure modals are properly cleaned up
function cleanupModal() {
    document.body.classList.remove('modal-open');
    const modalBackdrop = document.querySelector('.modal-backdrop');
    if (modalBackdrop) {
        modalBackdrop.remove();
    }
}

// Add event listeners for modal cleanup
document.getElementById('quotationViewModal').addEventListener('hidden.bs.modal', cleanupModal);

// Edit Proposal Functionality
document.querySelectorAll('.edit-proposal').forEach(button => {
    button.addEventListener('click', function() {
        const applicationId = this.getAttribute('data-application-id');
        const proposal = this.getAttribute('data-proposal');
        
        document.getElementById('editProposalText').value = proposal;
        document.getElementById('editApplicationId').value = applicationId;
    });
});

document.getElementById('saveProposalEdit').addEventListener('click', function() {
    const applicationId = document.getElementById('editApplicationId').value;
    const newProposal = document.getElementById('editProposalText').value;

    if (!newProposal.trim()) {
        alert('Please enter a proposal');
        return;
    }

    fetch('edit_proposal.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `applicationId=${applicationId}&proposal=${encodeURIComponent(newProposal)}`
    })
    .then(response => response.text())
    .then(data => {
        alert(data);
        if (data.includes('success')) {
            location.reload(); // Refresh the page to show updated proposal
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating proposal');
    });
});

// Delete Application Functionality
document.querySelectorAll('.delete-application').forEach(button => {
    button.addEventListener('click', function() {
        const hasQuotation = this.previousElementSibling.innerText === 'View Quotation';
        const confirmMessage = hasQuotation ? 
            'Are you sure you want to cancel this job?' : 
            'Are you sure you want to delete this application?';
            
        if (confirm(confirmMessage)) {
            const applicationId = this.getAttribute('data-application-id');
            const jobCard = this.closest('.job-card');
            const jobId = jobCard.getAttribute('data-jobid');
            
            fetch('delete_application.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `applicationId=${applicationId}`
            })
            .then(response => response.text())
            .then(data => {
                if (data.includes('success')) {
                    // Remove from localStorage if exists
                    const appliedJobs = JSON.parse(localStorage.getItem('appliedJobs') || '[]');
                    const updatedAppliedJobs = appliedJobs.filter(id => id !== jobId);
                    localStorage.setItem('appliedJobs', JSON.stringify(updatedAppliedJobs));
                    
                    // Force a complete page reload to refresh all sections
                    window.location.href = window.location.href.split('#')[0] + '#jobs';
                    window.location.reload(true);
                } else {
                    alert(data);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error ' + (hasQuotation ? 'canceling job' : 'deleting application'));
            });
        }
    });
});

// Add this function to your existing JavaScript
function loadAvailableJobs() {
    fetch('fetch_available_jobs.php')
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                console.error('Error:', data.error);
                return;
            }

            const jobListing = document.querySelector('.job-listing');
            if (!jobListing) return;

            if (data.jobs.length === 0) {
                jobListing.innerHTML = '<div class="alert alert-info">No available jobs found at the moment.</div>';
                return;
            }

            let jobsHTML = '';
            data.jobs.forEach(job => {
                jobsHTML += `
                    <div class="job-card" 
                        data-title="${job.title}" 
                        data-description="${job.description}"
                        data-jobid="${job.jobs_id}"
                        data-username="${job.employer_name}"
                        data-location="${job.location}"
                        data-posteddate="${job.postedDate}"
                        data-price="${job.price}"
                        data-paytype="${job.payType}"
                        data-jobtype="${job.jobType}"
                        data-status="Open">
                        <div class="job-content">
                            <h5>${job.title}</h5>
                            <p><strong>Posted by:</strong> ${job.employer_name}</p>
                            <p><strong>Location:</strong> ${job.location}</p>
                            <p><strong>Pay:</strong> PHP ${job.price} (${job.payType})</p>
                            <p><strong>Job Type:</strong> ${job.jobType}</p>
                            <p><strong>Description:</strong> ${job.description}</p>
                            <div class="button-container">
                                <button class="btn btn-primary apply-btn">Apply Now</button>
                            </div>
                        </div>
                    </div>
                `;
            });

            jobListing.innerHTML = jobsHTML;
            
            // Reattach event listeners to new Apply buttons with full functionality
            document.querySelectorAll('.apply-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const card = this.closest('.job-card');
                    
                    // Set modal content using dataset values
                    document.getElementById('modalJobTitle').textContent = card.dataset.title || 'N/A';
                    document.getElementById('modalJobStatus').textContent = card.dataset.status || 'N/A';
                    document.getElementById('modalJobDescription').textContent = card.dataset.description || 'N/A';
                    document.getElementById('modalJobUsername').textContent = card.dataset.username || 'N/A';
                    document.getElementById('modalJobLocation').textContent = card.dataset.location || 'N/A';
                    document.getElementById('modalJobPostedDate').textContent = card.dataset.posteddate || 'N/A';
                    document.getElementById('modalJobPrice').textContent = `PHP ${card.dataset.price || 'N/A'}`;
                    document.getElementById('modalJobPayType').textContent = card.dataset.paytype || 'N/A';
                    document.getElementById('modalJobType').textContent = card.dataset.jobtype || 'N/A';
                    
                    // Set the job ID for the submit proposal button
                    document.getElementById('submitProposal').dataset.jobId = card.dataset.jobid;

                    // Clear any existing proposal text
                    document.getElementById('proposalText').value = '';

                    // Show the modal
                    const modal = new bootstrap.Modal(document.getElementById('jobDetailsModal'));
                    modal.show();
                });
            });
        })
        .catch(error => {
            console.error('Error:', error);
        });
}

// Call this when the page loads
document.addEventListener('DOMContentLoaded', function() {
    loadAvailableJobs();
    // Refresh jobs every 30 seconds
    setInterval(loadAvailableJobs, 30000);
});

        </script>
    </body>
    </html>

