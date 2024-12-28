<?php
session_start();
include 'db_conn.php';
// Logout logic
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_unset();  // Clear session variables
    session_destroy(); // Destroy session
    header("Location: index.php");
    exit(); 
}

// Check if user is not logged in; redirect to login page
if (!isset($_SESSION['user_id']) || !isset($_SESSION['userType']) || !isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}
 



$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


$jobOptions = '';

// Fetch job types from the job_type table
$result = $conn->query("SELECT jobTypeID, jobType FROM job_type ORDER BY jobType ASC");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $jobOptions .= "<option value='" . $row['jobTypeID'] . "'>" . htmlspecialchars($row['jobType']) . "</option>";
    }
} else {
    $jobOptions .= "<option value='' disabled>No job categories found</option>";
}

// Handle form submission for posting or updating a gig
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['jobTitle'];
    $description = $_POST['jobDescription'];
    $location = $_POST['jobLocation'];
    $price = $_POST['jobSalary'];
    $payType = $_POST['payType'];
    $duration = $_POST['duration'];
    $employerId = $_SESSION['user_id'];
    $postedDate = date('Y-m-d');
    $status = 'Open';
    $remarks = '';

    // Handle job category
    if ($_POST['jobCategory'] === 'other' && !empty($_POST['otherCategoryText'])) {
        // Insert new category
        $newCategory = trim($_POST['otherCategoryText']);
        $insertCatStmt = $conn->prepare("INSERT INTO job_type (jobType) VALUES (?)");
        $insertCatStmt->bind_param('s', $newCategory);
        
        if ($insertCatStmt->execute()) {
            $jobTypeID = $insertCatStmt->insert_id;
        } else {
            echo "<script>alert('Error adding new category');</script>";
            exit();
        }
        $insertCatStmt->close();
    } else {
        $jobTypeID = $_POST['jobCategory'];
    }

    // Check if updating an existing gig
    if (isset($_POST['updateGigId']) && !empty($_POST['updateGigId'])) {
        $updateGigId = intval($_POST['updateGigId']);
        
        // Update query
        $sql = "UPDATE jobs SET 
                title = ?, 
                description = ?, 
                location = ?, 
                price = ?, 
                payType = ?, 
                duration = ?, 
                jobTypeID = ?,
                status = 'Open'
                WHERE jobs_id = ? AND employerId = ?";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            die("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param('sssdssiii', 
            $title, 
            $description, 
            $location, 
            $price, 
            $payType, 
            $duration, 
            $jobTypeID, 
            $updateGigId, 
            $employerId
        );
        
        if ($stmt->execute()) {
            echo "<script>
                alert('Gig updated successfully!');
                window.location.href = 'employerlanding.php';
            </script>";
            exit();
        } else {
            echo "<script>alert('Error updating gig: " . $stmt->error . "');</script>";
        }
        $stmt->close();
    } else {
        // Insert new gig
        $sql = "INSERT INTO jobs (title, description, employerId, postedDate, price, payType, duration, location, status, remarks, jobTypeID) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssisssssssi', 
            $title, 
            $description, 
            $employerId, 
            $postedDate, 
            $price, 
            $payType, 
            $duration, 
            $location, 
            $status, 
            $remarks, 
            $jobTypeID
        );

        if ($stmt->execute()) {
            echo "<script>
                alert('Gig posted successfully!');
                window.location.href = 'employerlanding.php';
            </script>";
            exit();
        } else {
            echo "<script>
                alert('Error posting gig: " . $stmt->error . "');
                window.location.href = 'employerlanding.php';
            </script>";
            exit();
        }
     
    }
}

// Retrieve gigs for the employer
$employerId = $_SESSION['user_id'];
$jobs_query = "SELECT j.*, jt.jobType as jobTypeName,
    COUNT(DISTINCT a.applications_id) as application_count
FROM jobs j
LEFT JOIN job_type jt ON j.jobTypeID = jt.jobTypeID
LEFT JOIN applications a ON j.jobs_id = a.jobid
LEFT JOIN quotations q ON a.applications_id = q.applications_id
WHERE j.employerId = ?
AND NOT EXISTS (
    SELECT 1 
    FROM quotations q2 
    JOIN applications a2 ON q2.applications_id = a2.applications_id 
    WHERE a2.jobid = j.jobs_id AND q2.status = 'Accepted'
)
GROUP BY j.jobs_id
ORDER BY j.postedDate DESC";

$gigs = $conn->prepare($jobs_query);
if (!$gigs) {
    die("Error preparing statement: " . $conn->error);
}

$gigs->bind_param("i", $employerId);
$gigs->execute();
$gigs = $gigs->get_result();


// Handle Delete Operation
if (isset($_GET['deleteGigId'])) {
    $deleteGigId = intval($_GET['deleteGigId']);
    $employerId = $_SESSION['user_id'];

    // Start transaction
    $conn->begin_transaction();

    try {
        // First, check if the gig exists and belongs to this employer
        $checkStmt = $conn->prepare("SELECT jobs_id FROM jobs WHERE jobs_id = ? AND employerId = ?");
        $checkStmt->bind_param("ii", $deleteGigId, $employerId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($result->num_rows > 0) {
            // First delete related notifications
            $deleteNotificationsStmt = $conn->prepare("DELETE FROM notifications WHERE job_id = ?");
            $deleteNotificationsStmt->bind_param("i", $deleteGigId);
            $deleteNotificationsStmt->execute();

            // Then delete related quotations
            $deleteQuotationsStmt = $conn->prepare("
                DELETE q FROM quotations q 
                INNER JOIN applications a ON q.applications_id = a.applications_id 
                WHERE a.jobId = ?
            ");
            $deleteQuotationsStmt->bind_param("i", $deleteGigId);
            $deleteQuotationsStmt->execute();

            // Then delete related applications
            $deleteAppsStmt = $conn->prepare("DELETE FROM applications WHERE jobId = ?");
            $deleteAppsStmt->bind_param("i", $deleteGigId);
            $deleteAppsStmt->execute();

            // Finally delete the job
            $deleteStmt = $conn->prepare("DELETE FROM jobs WHERE jobs_id = ? AND employerId = ?");
            $deleteStmt->bind_param("ii", $deleteGigId, $employerId);
            $deleteStmt->execute();

            if ($deleteStmt->affected_rows > 0) {
                $conn->commit();
                echo "<script>
                    alert('Gig successfully deleted!');
                    window.location.href = 'employerlanding.php';
                </script>";
                exit();
            } else {
                throw new Exception('Failed to delete the gig');
            }
        } else {
            throw new Exception('Gig not found or you do not have permission to delete it');
        }
    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>
            alert('Error: " . $e->getMessage() . "');
            window.location.href = 'employerlanding.php';
        </script>";
        exit();
    }

    // Close all prepared statements
    if (isset($checkStmt)) $checkStmt->close();
    if (isset($deleteNotificationsStmt)) $deleteNotificationsStmt->close();
    if (isset($deleteQuotationsStmt)) $deleteQuotationsStmt->close();
    if (isset($deleteAppsStmt)) $deleteAppsStmt->close();
    if (isset($deleteStmt)) $deleteStmt->close();
}

// Fetch userType from the database
$query = "SELECT userType FROM users WHERE users_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$userType = $result->fetch_assoc()['userType'];  // 'employer' or 'jobseeker'
$stmt->close();

// Update the applications query
$applicationsQuery = "SELECT DISTINCT
    a.applications_id,
    a.jobId,
    a.userId,
    a.proposal,
    a.applicationDate,
    CASE 
        WHEN a.status IS NULL THEN 'Pending'
        WHEN a.status = '' THEN 'Pending'
        ELSE a.status 
    END as status,
    j.title,
    j.description,
    j.status as job_status,
    u.username,
    ui.image_path,
    latest_q.quotations_id,
    latest_q.price as quotation_price,
    latest_q.description as quotation_description,
    latest_q.status as quotation_status,
    latest_q.valid_until,
    latest_q.applications_id as quotation_application_id,
    j.jobs_id
FROM applications a
INNER JOIN jobs j ON a.jobId = j.jobs_id 
INNER JOIN users u ON a.userId = u.users_id
LEFT JOIN user_info ui ON a.userId = ui.userid
LEFT JOIN (
    SELECT q.*
    FROM quotations q
    INNER JOIN (
        SELECT applications_id, MAX(quotations_id) as latest_quotation_id
        FROM quotations
        GROUP BY applications_id
    ) latest ON q.quotations_id = latest.latest_quotation_id
) latest_q ON a.applications_id = latest_q.applications_id
WHERE j.employerId = ?
    AND NOT EXISTS (
        SELECT 1
        FROM quotations q3 
        WHERE q3.applications_id = a.applications_id 
        AND q3.status = 'Accepted'
    )
    AND (a.status IS NULL OR a.status != 'rejected')
    AND (a.status IS NULL OR a.status != 'Completed')
GROUP BY a.applications_id
ORDER BY a.applicationDate DESC";

// Prepare and execute
$stmt = $conn->prepare($applicationsQuery);
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    die("Error preparing statement: " . $conn->error);
}

$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$applications = $stmt->get_result();

// Store applications in an array for JavaScript use
$applicationsArray = array();
while ($row = $applications->fetch_assoc()) {
    $applicationsArray[] = array(
        'id' => $row['applications_id'],
        'jobId' => $row['jobId'],
        'title' => htmlspecialchars($row['title']),
        'description' => htmlspecialchars($row['description']),
        'status' => $row['status'],
        'quotation_status' => $row['quotation_status'],
        'valid_until' => $row['valid_until']
    );
}

// Reset pointer for PHP usage
$applications->data_seek(0);

// Add this code block to handle AJAX requests
if (isset($_POST['action']) && $_POST['action'] === 'rejectProposal') {
    // Prevent any output before headers
    ob_clean();
    error_log("Rejecting proposal for application ID: " . $_POST['applicationId']);
    $applicationId = $_POST['applicationId'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // First, delete any quotations
        $stmt = $conn->prepare("DELETE FROM quotations WHERE applications_id = ?");
        $stmt->bind_param("i", $applicationId);
        $stmt->execute();
        
        // Then, delete the application
        $stmt = $conn->prepare("DELETE FROM applications WHERE applications_id = ?");
        $stmt->bind_param("i", $applicationId);
        $stmt->execute();
        
        // Commit the transaction
        $conn->commit();
        
        error_log("Successfully deleted application ID: " . $applicationId);
        header('Content-Type: application/json');
        header("Cache-Control: no-cache, must-revalidate");
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error deleting application: " . $e->getMessage());
        header('Content-Type: application/json');
        header("Cache-Control: no-cache, must-revalidate");
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// Add this query where you fetch the jobseeker's details


?>




    <!DOCTYPE html>
    <html lang="en-US" dir="ltr" class="chrome windows">

    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>RaketHero | Employer Dashboard</title>

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
            .gig-listing {
                margin-bottom: 20px;
            }

            .gig-card {
                border: 1px solid #ddd;
                padding: 15px;
                border-radius: 8px;
                margin-bottom: 15px;
                background-color: #ffffff;
            }

            #searchGigInput {
                background-color: #f8f9fa;
                padding: 19px;
                border-radius: 8px;
                margin-top: -8px;
                margin-right: 24px;
            }

            /* Add this class for scrollable modals */
            .modal-body-scrollable {
                max-height: 70vh; /* Adjust the height as needed */
                overflow-y: auto;
            }

            .notification-badge {
                position: absolute;
                top: -5px;
                right: -5px;
                padding: 3px 6px;
                border-radius: 50%;
                background: red;
                color: white;
                font-size: 12px;
            }
            
            .notification-dropdown {
                min-width: 300px;
                max-height: 400px;
                overflow-y: auto;
            }
            
            .notification-item {
                padding: 10px;
                border-bottom: 1px solid #eee;
            }
            
            .notification-item.unread {
                background-color: #f8f9fa;
            }

            /* Update modal scrollable styles */
            .modal-body-scrollable {
                max-height: 70vh;
                overflow-y: auto;
                padding-right: 10px;
            }

            .modal-body-scrollable::-webkit-scrollbar {
                width: 6px;
            }

            .modal-body-scrollable::-webkit-scrollbar-track {
                background: #f1f1f1;
                border-radius: 3px;
            }

            .modal-body-scrollable::-webkit-scrollbar-thumb {
                background: #888;
                border-radius: 3px;
            }

            .modal-body-scrollable::-webkit-scrollbar-thumb:hover {
                background: #555;
            }

            /* Update these styles */
            .gig-card {
                background: #fff;
                border-radius: 10px;
                transition: transform 0.2s;
                border: 1px solid #dee2e6;
            }

            .gig-card:hover {
                transform: translateY(-2px);
            }

            .gig-card.border-success {
                border-left: 4px solid #28a745 !important;
            }

            .applicant-info img {
                border: 2px solid #fff;
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            }

            .status-badge .badge {
                padding: 8px 12px;
                font-size: 0.85rem;
            }

            .quotation-info {
                background-color: #f8f9fa;
                padding: 15px;
                border-radius: 8px;
                margin-top: 15px;
            }

            .alert {
                border-radius: 10px;
                padding: 1rem;
            }

            .alert-info {
                background-color: #e8f4f8;
                border-color: #bee5eb;
            }

            /* Add these styles to your existing style section in employerlanding.php */
            .job-card {
                background: #fff;
                border-radius: 10px;
                padding: 20px;
                margin-bottom: 20px;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                transition: transform 0.2s ease, box-shadow 0.2s ease;
                border: 1px solid #e0e0e0;
            }

            .job-content {
                width: 100%;
            }

            .status-badge {
                padding: 6px 12px;
                border-radius: 20px;
                font-size: 0.85rem;
                font-weight: 500;
                display: inline-block;
                margin-left: 5px;
            }

            .proposal-section, .quotation-section {
                background: #f8f9fa;
                border-radius: 8px;
                padding: 1rem;
                margin: 1rem 0;
                border: 1px solid #e9ecef;
            }

            .button-container {
                margin-top: 20px;
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
            }

            .button-container .btn {
                padding: 8px 20px;
                border-radius: 5px;
                font-weight: 500;
                text-transform: none;
                font-size: 0.9rem;
                min-width: 120px;
            }

            .fw-bold {
                font-weight: 600 !important;
            }

            .text-secondary {
                color: #6c757d !important;
            }

            /* Status badge styles */
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
                border: 1px solid #ffeeba;
            }

            .status-badge.accepted {
                background-color: #d4edda;
                color: #155724;
                border: 1px solid #c3e6cb;
            }

            .status-badge.rejected {
                background-color: #f8d7da;
                color: #721c24;
                border: 1px solid #f5c6cb;
            }

            .status-badge.completed {
                background-color: #cce5ff;
                color: #004085;
                border: 1px solid #b8daff;
            }
        </style>
    </head>

    <body>

        <!-- Main Content -->
        <main class="main" id="top">
            <nav class="navbar navbar-expand-lg navbar-light fixed-top py-3 backdrop bg-light shadow-transition">
                <div class="container">
                    <a class="navbar-brand d-flex align-items-center fw-bolder fs-2 fst-italic" href="#">
                        <div class="text-info">Raket</div>
                        <div class="text-warning">Hero</div>
                    </a>
                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                        data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="true"
                        aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
                    <div class="navbar-collapse border-top border-lg-0 mt-4 mt-lg-0 collapse show"
                        id="navbarSupportedContent">
                        <ul class="navbar-nav ms-auto pt-2 pt-lg-0">
                            <li class="nav-item px-2"><a class="nav-link fw-medium active" aria-current="page" href="#">Dashboard</a></li>
                            <li class="nav-item px-2"><a class="nav-link fw-medium" href="#gig-postings">Your Gigs</a></li>
                            <li class="nav-item px-2"><a class="nav-link fw-medium" href="#applications">Applications</a></li>
                            <li class="nav-item px-2">
                                <?php if ($_SESSION['userType'] === 'employer'): ?>
                                    <a class="nav-link fw-medium" href="employersprofile.php">Profile</a>
                                <?php else: ?>
                                    <a class="nav-link fw-medium" href="profile.php">Profile</a>
                                <?php endif; ?>
                            </li>
                            <li class="nav-item px-2"><a class="nav-link fw-medium" href="employer_transactions.php">Transaction History</a></li>
                            <li class="nav-item px-2"><a class="nav-link fw-medium" href="?action=logout">Logout</a></li>
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

            <!-- Your Gig Postings Section -->
            <section class="py-5" id="gig-postings">
            <div class="container">
                <h4 class="mb-4">Your Gigs</h4>
                <div class="gig-listing">
                    <?php if ($gigs && $gigs->num_rows > 0): ?>
                        <?php while ($gig = $gigs->fetch_assoc()): ?>
                            <div class="gig-card" data-title="<?php echo htmlspecialchars($gig['title']); ?>" data-category="<?php echo htmlspecialchars($gig['jobTypeID']); ?>">
                    <h5>Gig Title: <?php echo htmlspecialchars($gig['title']); ?></h5>
                    <p><strong>Status:</strong> <?php echo htmlspecialchars($gig['status']); ?></p>
                      <p><strong>Budget:</strong> <?php echo htmlspecialchars($gig['price']); ?></p>
                    <p><strong>Description:</strong> <?php echo htmlspecialchars($gig['description']); ?></p>
                    <button 
    class="btn btn-info" 
    onclick="openViewDetailsModal(
        '<?php echo addslashes(htmlspecialchars($gig['title'])); ?>',
        '<?php echo addslashes(htmlspecialchars($gig['description'])); ?>',
        '<?php echo addslashes(htmlspecialchars($gig['location'])); ?>',
        '<?php echo addslashes($gig['price']); ?>',
        '<?php echo addslashes(htmlspecialchars($gig['payType'])); ?>',
        '<?php echo addslashes(htmlspecialchars($gig['duration'])); ?>',
        '<?php echo addslashes(htmlspecialchars($gig['status'])); ?>',
        <?php echo $gig['jobs_id']; ?>,
        '<?php echo addslashes(htmlspecialchars($gig['jobTypeName'])); ?>'
    )">
    View Details
</button>

                </div>
               <?php endwhile; ?>
                    <?php else: ?>
                        <p>No gigs found. Start by posting a new gig!</p>
                    <?php endif; ?>
                </div>

        
        <!-- Button to trigger modal -->
        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createGigModal">
            Post a New Gig
        </button>
    </div>
</section>  
<!-- View Details Modal -->
<div class="modal fade" id="viewDetailsModal" tabindex="-1" aria-labelledby="viewDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewDetailsModalLabel">Gig Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body modal-body-scrollable">
                <div class="mb-3">
                    <h5 id="viewGigTitle" class="fw-bold"></h5>
                </div>
                <div class="mb-3">
                    <p><strong>Description:</strong></p>
                <p id="viewGigDescription"></p>
                </div>
                <div class="mb-3">
                <p><strong>Location:</strong> <span id="viewGigLocation"></span></p>
                </div>
                <div class="mb-3">
                    <p><strong>Budget:</strong> PHP <span id="viewGigSalary"></span></p>
                </div>
                <div class="mb-3">
                <p><strong>Pay Type:</strong> <span id="viewGigPayType"></span></p>
                </div>
                <div class="mb-3">
                <p><strong>Duration:</strong> <span id="viewGigDuration"></span></p>
                </div>
                <div class="mb-3">
                <p><strong>Status:</strong> <span id="viewGigStatus"></span></p>
                </div>
                <div class="mb-3">
                    <p><strong>Job Type:</strong> <span id="viewGigJobType"></span></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" onclick="openUpdateModal()">Update</button>
                <button type="button" class="btn btn-danger" onclick="deleteGig()">Delete</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

            <!-- Applications Section -->
            <section class="py-5 bg-light" id="applications">
                <div class="container">
                    <h4 class="section-header">Applications</h4>
                    <div class="job-listing">
                        <?php if ($applications && $applications->num_rows > 0): ?>
                            <?php while ($application = $applications->fetch_assoc()): ?>
                                <div class="job-card" data-jobid="<?php echo htmlspecialchars($application['jobs_id']); ?>">
                                    <div class="job-content">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <h5 class="mb-1"><?php echo htmlspecialchars($application['title']); ?></h5>
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="status-badge <?php echo strtolower($application['status']); ?>">
                                                        <?php echo htmlspecialchars($application['status']); ?>
                                                    </span>
                                                    <span class="text-muted">•</span>
                                                    <span class="text-muted">Applied on <?php echo date('M j, Y', strtotime($application['applicationDate'])); ?></span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Add clickable applicant profile section -->
                                        <div class="applicant-section mb-3">
                                            <a href="view_profile.php?user_id=<?php echo htmlspecialchars($application['userId']); ?>" 
                                               class="text-decoration-none">
                                                <div class="d-flex align-items-center p-2 bg-light rounded">
                                                    <img src="<?php echo !empty($application['image_path']) ? 
                                                        htmlspecialchars($application['image_path']) : 
                                                        'assets/img/default-image.png'; ?>" 
                                                        alt="Applicant Profile" 
                                                        class="rounded-circle me-3"
                                                        style="width: 50px; height: 50px; object-fit: cover;">
                                                    <div>
                                                        <h6 class="mb-0"><?php echo htmlspecialchars($application['username']); ?></h6>
                                                        <small class="text-muted">View Full Profile</small>
                                                    </div>
                                                </div>
                                            </a>
                                        </div>

                                        <div class="proposal-section bg-white p-3 rounded border mb-3">
                                            <h6 class="fw-bold mb-2">Applicant's Proposal</h6>
                                            <p class="mb-0 text-secondary">
                                                <?php echo nl2br(htmlspecialchars($application['proposal'] ?? '')); ?>
                                            </p>
                                        </div>

                                        <?php if (isset($application['quotations_id'])): ?>
                                            <div class="quotation-section bg-light p-3 rounded border mb-3">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <h6 class="fw-bold mb-0">Quotation Details</h6>
                                                    <span class="status-badge <?php echo strtolower($application['quotation_status'] ?? 'pending'); ?>">
                                                        <?php echo htmlspecialchars($application['quotation_status'] ?? 'Pending'); ?>
                                                    </span>
                                                </div>
                                                <p class="mb-2"><strong>Amount:</strong> PHP <?php echo number_format($application['quotation_price'], 2); ?></p>
                                                <p class="mb-0"><strong>Description:</strong> <?php echo htmlspecialchars($application['quotation_description'] ?? ''); ?></p>
                                            </div>
                                        <?php endif; ?>

                                        <div class="action-buttons">
                                            <?php if (empty($application['status']) || 
                                                      strtolower($application['status']) == 'pending'): ?>
                                                <!-- Initial proposal - show Accept/Decline -->
                                                <button class="btn btn-success btn-sm" 
                                                        onclick="handleProposal(<?php echo $application['applications_id']; ?>, 'accept')">
                                                    Accept Proposal
                                                </button>
                                                <button class="btn btn-danger btn-sm" 
                                                        onclick="rejectProposal('<?php echo $application['applications_id']; ?>')">
                                                    Decline Proposal
                                                </button>
                                            <?php elseif (strtolower($application['status']) == 'accepted'): ?>
                                                <!-- Only show Cancel Job button when accepted -->
                                                <button class="btn btn-danger btn-sm" 
                                                        onclick="cancelJob(<?php echo $application['applications_id']; ?>)">
                                                    Cancel Job
                                                </button>
                                            <?php elseif (strtolower($application['status']) == 'negotiation'): ?>
                                                <!-- Show negotiation status and buttons -->
                                                <div class="alert alert-info mb-2">
                                                    Negotiation in progress
                                                </div>
                                                <div class="d-flex gap-2">
                                                    <button class="btn btn-info btn-sm" 
                                                            onclick="viewQuotation({quotationId: '<?php echo $application['quotations_id']; ?>', jobId: '<?php echo $application['jobId']; ?>'})">
                                                        View Negotiation
                                                    </button>
                                                    <button class="btn btn-danger btn-sm" 
                                                            onclick="cancelJob(<?php echo $application['applications_id']; ?>)">
                                                        Cancel Job
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="alert alert-info">No applications received yet.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

        </main>

        <!-- Modal for Creating a New Gig -->
        <div class="modal fade" id="createGigModal" tabindex="-1" aria-labelledby="createGigModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="createGigModalLabel">Post a New Gig</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body modal-body-scrollable">
                    <form method="POST" action="" id="createGigForm">
        <div class="mb-3">
            <label for="jobTitle" class="form-label">Job Title</label>
            <input type="text" class="form-control" id="jobTitle" name="jobTitle" required>
        </div>

        <div class="mb-3">
            <label for="jobDescription" class="form-label">Job Description</label>
            <textarea class="form-control" id="jobDescription" name="jobDescription" rows="4" required></textarea>
        </div>

        <div class="mb-3">
            <label for="jobLocation" class="form-label">Location</label>
            <input type="text" class="form-control" id="jobLocation" name="jobLocation" required>
        </div>

        <div class="mb-3">
            <label for="jobSalary" class="form-label">Salary Offer (PHP)</label>
            <input type="number" class="form-control" id="jobSalary" name="jobSalary" required>
        </div>

        <div class="mb-3">
            <label for="payType" class="form-label">Pay Type</label>
            <select class="form-select" id="payType" name="payType" required>
                <option value="" disabled selected>Select Pay Type</option>
                <option value="Pay by day">Pay by day</option>
                <option value="Pay by hour">Pay by hour</option>
                <option value="Pay by project">Pay by project</option>
                <option value="Pay by week">Pay by week</option>
            </select>
        </div>

        <div class="mb-3">
            <label for="jobCategory" class="form-label">Job Category</label>
            <select class="form-select" id="jobCategory" name="jobCategory" onchange="toggleOtherCategory(this, 'otherCategoryInput')" required>
                <option value="" disabled selected>Select Job Category</option>
                <?php echo $jobOptions; ?>
                <option value="other">Others</option>
            </select>
            <div id="otherCategoryInput" style="display: none; margin-top: 10px;">
                <input type="text" class="form-control" id="otherCategoryText" name="otherCategoryText" placeholder="Enter new category">
            </div>
        </div>

        <div class="mb-3">
            <label for="duration" class="form-label">Duration</label>
            <input type="text" class="form-control" id="duration" name="duration" placeholder="e.g., 1 week, 3 days" required>
        </div>

        <div class="mb-3">
            <label for="applicationDeadline" class="form-label">Application Deadline</label>
            <input type="date" class="form-control" id="applicationDeadline" name="applicationDeadline" required>
        </div>

        <button type="submit" class="btn btn-primary">Post Gig</button>
    </form>
                    </div>
                </div>
            </div>
        </div>

    <!-- Update Gig Modal -->
    <div class="modal fade" id="updateGigModal" tabindex="-1" aria-labelledby="updateGigModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="updateGigModalLabel">Update Gig</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body modal-body-scrollable">
                    <form method="POST" action="">
                        <input type="hidden" name="updateGigId" id="updateGigId">
                        <div class="mb-3">
                            <label for="updateJobTitle" class="form-label">Job Title</label>
                            <input type="text" class="form-control" id="updateJobTitle" name="jobTitle" required>
                        </div>
                        <div class="mb-3">
                            <label for="updateJobDescription" class="form-label">Job Description</label>
                            <textarea class="form-control" id="updateJobDescription" name="jobDescription" rows="4" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="updateJobLocation" class="form-label">Location</label>
                            <input type="text" class="form-control" id="updateJobLocation" name="jobLocation" required>
                        </div>
                        <div class="mb-3">
                            <label for="updateJobSalary" class="form-label">Salary Offer (PHP)</label>
                            <input type="number" class="form-control" id="updateJobSalary" name="jobSalary" required>
                        </div>
                        <div class="mb-3">
                            <label for="updatePayType" class="form-label">Pay Type</label>
                            <select class="form-select" id="updatePayType" name="payType" required>
                                <option value="" disabled>Select Pay Type</option>
                                <option value="Pay by day">Pay by day</option>
                                <option value="Pay by hour">Pay by hour</option>
                                <option value="Pay by project">Pay by project</option>
                                <option value="Pay by week">Pay by week</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="updateJobCategory" class="form-label">Job Category</label>
                            <select class="form-select" id="updateJobCategory" name="jobCategory" onchange="toggleOtherCategory(this, 'updateOtherCategoryInput')" required>
                                <?php echo $jobOptions; ?>
                                <option value="other">Others</option>
                            </select>
                            <div id="updateOtherCategoryInput" style="display: none; margin-top: 10px;">
                                <input type="text" class="form-control" id="updateOtherCategoryText" name="otherCategoryText" placeholder="Enter new category">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="updateDuration" class="form-label">Duration</label>
                            <input type="text" class="form-control" id="updateDuration" name="duration" placeholder="e.g., 1 week, 3 days" required>
                        </div>
                        <div class="mb-3">
                            <label for="updateApplicationDeadline" class="form-label">Application Deadline</label>
                            <input type="date" class="form-control" id="updateApplicationDeadline" name="applicationDeadline" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Update Gig</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Proposal View Modal -->
    <div class="modal fade" id="proposalModal" tabindex="-1" aria-labelledby="proposalModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="proposalModalLabel">Application Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="applicant-info mb-4">
                        <a href="#" class="text-decoration-none d-flex align-items-center">
                            <img id="proposalApplicantPhoto" src="" alt="Applicant Photo" 
                                 class="rounded-circle me-3" style="width: 60px; height: 60px; object-fit: cover;">
                            <div>
                                <h6 class="mb-0">Applicant:</h6>
                                <p class="mb-0" id="proposalApplicant"></p>
                            </div>
                        </a>
                    </div>
                    <h6 id="proposalJobTitle" class="fw-bold"></h6>
                    <p><strong>Submitted On:</strong> <span id="proposalDate"></span></p>
                    <p><strong>Status:</strong> <span id="proposalStatus"></span></p>
                    <div class="mt-3">
                        <h6>Proposal:</h6>
                        <p id="proposalText"></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <input type="hidden" id="currentApplicationId">
                    <input type="hidden" id="currentJobId">
                    <button type="button" class="btn btn-success" onclick="updateApplicationStatus('Accepted')">Accept</button>
                    <button type="button" class="btn btn-danger" onclick="updateApplicationStatus('Rejected')">Reject</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Quotation Modal -->
    <div class="modal fade" id="viewQuotationModal" tabindex="-1" aria-labelledby="viewQuotationModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewQuotationModalLabel">Quotation Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h6 id="viewQuotationTitle" class="mb-3"></h6>
                    <div class="quotation-details">
                        <p><strong>Amount:</strong> PHP <span id="viewQuotationAmount"></span></p>
                        <p><strong>Description:</strong></p>
                        <div id="viewQuotationDescription" class="p-3 bg-light rounded"></div>
                        <p class="mt-3"><strong>Valid Until:</strong> <span id="viewQuotationValidUntil"></span></p>
                        <p><strong>Status:</strong> <span id="viewQuotationStatus" class="status-badge"></span></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScripts -->
    <script src="vendors/@popperjs/popper.min.js"></script>
    <script src="vendors/bootstrap/bootstrap.min.js"></script>
    <script src="vendors/is/is.min.js"></script>
    <script src="https://polyfill.io/v3/polyfill.min.js?features=window.scroll"></script>
    <script src="assets/js/theme.js"></script>

    <script>
        let currentGigId = null;
        let currentGigData = null;

        function openViewDetailsModal(title, description, location, price, payType, duration, status, gigId, jobType) {
            currentGigId = gigId;
            currentGigData = {
                title: title,
                description: description,
                location: location,
                price: price,
                payType: payType,
                duration: duration,
                status: status,
                jobType: jobType
            };

            // Update the modal content
            document.getElementById('viewGigTitle').textContent = title;
            document.getElementById('viewGigDescription').textContent = description;
            document.getElementById('viewGigLocation').textContent = location;
            document.getElementById('viewGigSalary').textContent = price;
            document.getElementById('viewGigPayType').textContent = payType;
            document.getElementById('viewGigDuration').textContent = duration;
            document.getElementById('viewGigStatus').textContent = status;
            document.getElementById('viewGigJobType').textContent = jobType;

            // Get the modal element and show it
            const viewDetailsModal = new bootstrap.Modal(document.getElementById('viewDetailsModal'));
            viewDetailsModal.show();
        }

        function openUpdateModal() {
            // Close view details modal
            bootstrap.Modal.getInstance(document.getElementById('viewDetailsModal')).hide();

            // Populate update modal with current gig data
            document.getElementById('updateGigId').value = currentGigId;
            document.getElementById('updateJobTitle').value = currentGigData.title;
            document.getElementById('updateJobDescription').value = currentGigData.description;
            document.getElementById('updateJobLocation').value = currentGigData.location;
            document.getElementById('updateJobSalary').value = currentGigData.price;
            document.getElementById('updatePayType').value = currentGigData.payType;
            document.getElementById('updateDuration').value = currentGigData.duration;
            
            // Set the job category if it exists
            if (currentGigData.jobType) {
                const categorySelect = document.getElementById('updateJobCategory');
                const options = Array.from(categorySelect.options);
                const optionToSelect = options.find(item => item.text === currentGigData.jobType);
                if (optionToSelect) {
                    categorySelect.value = optionToSelect.value;
                }
            }

            // Show update modal
            new bootstrap.Modal(document.getElementById('updateGigModal')).show();
        }

        function deleteGig() {
            if (confirm('Are you sure you want to delete this gig? This action cannot be undone.')) {
                // Close view details modal
                bootstrap.Modal.getInstance(document.getElementById('viewDetailsModal')).hide();
                
                // Redirect to delete action
                window.location.href = `employerlanding.php?deleteGigId=${currentGigId}`;
            }
        }

        // Keep your existing search functionality
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

        // Add this function with your other JavaScript
        function toggleOtherCategory(selectElement, inputDivId) {
            const otherInput = document.getElementById(inputDivId);
            if (selectElement.value === 'other') {
                otherInput.style.display = 'block';
                otherInput.querySelector('input').required = true;
            } else {
                otherInput.style.display = 'none';
                otherInput.querySelector('input').required = false;
            }
        }

        function viewProposal(jobTitle, applicantName, proposal, date, status, profilePhoto, applicationId, jobId, userId) {
            // Update the modal content
            document.getElementById('proposalJobTitle').innerText = jobTitle;
            document.getElementById('proposalApplicant').innerText = applicantName;
            document.getElementById('proposalDate').innerText = date;
            document.getElementById('proposalStatus').innerText = status;
            document.getElementById('proposalText').innerText = proposal;
            document.getElementById('proposalApplicantPhoto').src = profilePhoto;
            
            // Update the profile link in the modal
            const profileLink = document.querySelector('#proposalModal .applicant-info a');
            if (profileLink) {
                profileLink.href = `view_profile.php?user_id=${userId}`;
            }

            // Store both applicationId and jobId
            document.getElementById('currentApplicationId').value = applicationId;
            document.getElementById('currentJobId').value = jobId;

            // Show/hide accept/reject buttons based on current status
            const acceptBtn = document.querySelector('.btn-success');
            const rejectBtn = document.querySelector('.btn-danger');
            if (status === 'Pending') {
                acceptBtn.style.display = 'block';
                rejectBtn.style.display = 'block';
            } else {
                acceptBtn.style.display = 'none';
                rejectBtn.style.display = 'none';
            }

            // Show the proposal modal
            new bootstrap.Modal(document.getElementById('proposalModal')).show();
        }

        function updateApplicationStatus(newStatus) {
            const applicationId = document.getElementById('currentApplicationId').value;
            const jobId = document.getElementById('currentJobId').value;

            if (newStatus === 'Accepted') {
                // Pass both applicationId and jobId in the URL
                window.location.href = `quotations.php?applicationId=${applicationId}&jobId=${jobId}&status=Accepted`;
                return;
            } else if (newStatus === 'Rejected') {
                // Show confirmation dialog for rejection
                if (!confirm('Are you sure you want to reject this application? This action cannot be undone.')) {
                    return;
                }
            }

            // For rejection, proceed with the AJAX call
            fetch('update_application_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `applicationId=${applicationId}&status=${newStatus}`
            })
            .then(response => response.text())
            .then(data => {
                alert(data);
                // Close the modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('proposalModal'));
                if (modal) {
                    modal.hide();
                }
                location.reload(); // Reload to update the list
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating application status');
            });
        }

        function rejectApplication(applicationId) {
            if (confirm('Are you sure you want to reject this application?')) {
                fetch('reject_application.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `applicationId=${applicationId}`
                })
                .then(response => response.text())
                .then(data => {
                    alert(data);
                    if (data.includes('success')) {
                        window.location.reload();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error rejecting application');
                });
            }
        }

        function viewQuotation(data) {
            // Redirect to employer_view_quotation.php instead
            window.location.href = `employer_view_quotation.php?quotation_id=${data.quotationId}&job_id=${data.jobId}`;
        }

        function cancelJob(applicationId) {
            if (confirm('Are you sure you want to cancel this job? This action cannot be undone.')) {
                fetch('cancel_job.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `applicationId=${applicationId}`
                })
                .then(response => response.text())
                .then(data => {
                    if (data.includes('success')) {
                        alert('Job cancelled successfully');
                        location.reload();
                    } else {
                        alert(data);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error canceling job');
                });
            }
        }

        // Add this at the beginning of your script section
        const applications = <?php echo json_encode($applicationsArray); ?>;

        // Use the applications array in your JavaScript functions
        function viewApplication(id) {
            const app = applications.find(a => a.id === id);
            if (app) {
                // Use the safely encoded data
                // ...
            }
        }

        // Add this function to your existing JavaScript code
        function rejectProposal(applicationId) {
            if (confirm('Are you sure you want to decline this proposal?')) {
                fetch('employerlanding.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'Accept': 'application/json'
                    },
                    body: 'action=rejectProposal&applicationId=' + applicationId
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        alert('Proposal declined successfully');
                        window.location.reload();
                    } else {
                        alert(data.message || 'Error declining proposal');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error declining proposal: ' + error.message);
                });
            }
        }

        function handleProposal(applicationId, action) {
            const confirmMessage = action === 'accept' ? 
                'Are you sure you want to accept this proposal?' :
                'Are you sure you want to decline this proposal?';

            if (!confirm(confirmMessage)) {
                return;
            }

            fetch('handle_proposal.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `applicationId=${applicationId}&action=${action}`
            })
            .then(response => response.text())
            .then(data => {
                if (data.includes('success')) {
                    alert(action === 'accept' ? 
                        'Proposal accepted. Waiting for jobseeker counter offer.' : 
                        'Proposal declined.');
                    location.reload();
                } else {
                    alert('Error processing request: ' + data);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error processing request');
            });
        }
    </script>

        <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,200;1,300;1,400&display=swap"
            rel="stylesheet">

    </body>
    </html>
