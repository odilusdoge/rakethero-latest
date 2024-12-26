<?php
include 'db_conn.php';
session_start();

// Redirect jobseekers to their profile page
if (isset($_SESSION['userType']) && $_SESSION['userType'] === 'jobseeker') {
    header("Location: profile.php");
    exit();
}

// Logout logic
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit();
}

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$userid = $_SESSION['user_id'];
$hasProfile = false;
$fname = $lname = $middle_initial = $email = $contactNo = $age = $location = $overview = $title = "";
$profile_image = "assets/img/default-image.png";
$posted_jobs = [];
$reviews = [];
$average_rating = 0;

// Fetch employer data
$query = "SELECT u.*, ui.* 
          FROM users u 
          LEFT JOIN user_info ui ON u.users_id = ui.userid 
          WHERE u.users_id = ? AND u.userType = 'employer'";
$stmt = $conn->prepare($query);
if (!$stmt) {
    error_log("Query preparation failed: " . $conn->error);
    $hasProfile = false;
} else {
    $stmt->bind_param("i", $userid);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $userInfo = $result->fetch_assoc();
        if (!empty($userInfo['fname']) || !empty($userInfo['lname'])) { // Check if basic profile info exists here
            $hasProfile = true;
            $fname = htmlspecialchars($userInfo['fname']);
            $lname = htmlspecialchars($userInfo['lname']);
            $middle_initial = htmlspecialchars($userInfo['middle_initial']);
            $email = htmlspecialchars($userInfo['email']);
            $contactNo = htmlspecialchars($userInfo['contactNo']);
            $age = htmlspecialchars($userInfo['age']);
            $location = htmlspecialchars($userInfo['location']);
            $overview = htmlspecialchars($userInfo['overview']);
            $title = htmlspecialchars($userInfo['title']);

            if (!empty($userInfo['image_path'])) {
                $profile_image = htmlspecialchars($userInfo['image_path']);
                error_log("Image path from database: " . $userInfo['image_path']);
            } else {
                error_log("No image path found in database");
            }
        }
    }
    $stmt->close();
}

// Fetch posted jobs and their ratings
if ($hasProfile) {
    $jobs_query = "SELECT j.*, 
                   (SELECT AVG(r.rating) 
                    FROM user_ratings r 
                    WHERE r.transaction_id IN (
                        SELECT a.applications_id 
                        FROM applications a 
                        WHERE a.jobId = j.jobs_id
                    )) as job_rating,
                   (SELECT COUNT(*) 
                    FROM applications a 
                    WHERE a.jobId = j.jobs_id) as application_count,
                   (SELECT GROUP_CONCAT(
                        CONCAT(r.rating, '|', r.comment, '|', r.created_at)
                        ORDER BY r.created_at DESC
                        LIMIT 3
                    )
                    FROM user_ratings r
                    WHERE r.transaction_id IN (
                        SELECT a.applications_id 
                        FROM applications a 
                        WHERE a.jobId = j.jobs_id
                    )) as recent_reviews
                   FROM jobs j 
                   WHERE j.employerId = ? 
                   ORDER BY j.postedDate DESC";
    
    $stmt = $conn->prepare($jobs_query);
    if ($stmt) {
        $stmt->bind_param("i", $userid);
        $stmt->execute();
        $jobs_result = $stmt->get_result();
        while ($row = $jobs_result->fetch_assoc()) {
            $reviews_data = [];
            if ($row['recent_reviews']) {
                $reviews_array = explode(',', $row['recent_reviews']);
                foreach ($reviews_array as $review) {
                    list($rating, $comment, $date) = explode('|', $review);
                    $reviews_data[] = [
                        'rating' => $rating,
                        'comment' => $comment,
                        'date' => $date
                    ];
                }
            }
            
            $posted_jobs[] = [
                'title' => htmlspecialchars($row['title']),
                'description' => htmlspecialchars($row['description']),
                'price' => htmlspecialchars($row['price']),
                'status' => htmlspecialchars($row['status']),
                'created_at' => $row['postedDate'],
                'job_rating' => round($row['job_rating'], 1),
                'application_count' => $row['application_count'],
                'reviews' => $reviews_data
            ];
        }
        $stmt->close();
    }

    // Get average rating
    $rating_query = "SELECT AVG(rating) as avg_rating FROM user_ratings WHERE rated_user_id = ?";
    $stmt = $conn->prepare($rating_query);
    if ($stmt) {
        $stmt->bind_param("i", $userid);
        $stmt->execute();
        $rating_result = $stmt->get_result();
        if ($row = $rating_result->fetch_assoc()) {
            $average_rating = round($row['avg_rating'], 1);
        }
        $stmt->close();
    }

    // Get top 3 reviews
    $reviews_query = "SELECT comment, rating, created_at 
                     FROM user_ratings 
                     WHERE rated_user_id = ? 
                     ORDER BY rating DESC, created_at DESC 
                     LIMIT 3";
    $stmt = $conn->prepare($reviews_query);
    if ($stmt) {
        $stmt->bind_param("i", $userid);
        $stmt->execute();
        $reviews_result = $stmt->get_result();
        while ($row = $reviews_result->fetch_assoc()) {
            $reviews[] = [
                'comment' => htmlspecialchars($row['comment']),
                'rating' => $row['rating'],
                'date' => $row['created_at']
            ];
        }
        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile | RaketHero</title>
    <link href="assets/css/theme.css" rel="stylesheet">
    <style>
        /* Page styles */
        .container-fluid {
            max-width: 100%;
            padding-top: 5rem;
        }

        .card {
            border-radius: 15px;
            overflow: hidden;
            margin-top: 2rem;
        }

        .card-body {
            padding: 2rem;
        }

        .badge {
            font-size: 0.85rem;
            padding: 0.5em 0.7em;
            border-radius: 12px;
        }

        body {
            background-color: #f8f9fa;
            margin: 0;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top py-3 backdrop bg-light shadow-transition">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center fw-bolder fs-2 fst-italic" href="index.html">
                <div class="text-info">Raket</div><div class="text-warning">Hero</div>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav ms-auto">
                <li class="nav-item px-2"><a class="nav-link fw-medium active" aria-current="page" href="employerlanding.php">Dashboard</a></li>
                            <li class="nav-item px-2"><a class="nav-link fw-medium" href="employerlanding.php#gig-postings">Your Gigs</a></li>
                            <li class="nav-item px-2"><a class="nav-link fw-medium" href="employerlanding.php#applications">Applications</a></li>
                            <li class="nav-item px-2"><a class="nav-link fw-medium" href="employersprofile.php">Profile</a></li>
                            <li class="nav-item px-2"><a class="nav-link fw-medium" href="employer_transactions.php">Transaction History</a></li>
                            <li class="nav-item px-2"><a class="nav-link fw-medium" href="?action=logout">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Profile Section -->
    <div class="container-fluid py-5 bg-light">
        <div class="row justify-content-center">
            <div class="col-12 col-md-10 col-lg-8">
                <div class="card shadow-lg border-0">
                    <div class="card-body text-center">
                        <?php if ($hasProfile): ?>
                            <!-- Profile Picture -->
                            <img src="<?php echo !empty($profile_image) && file_exists($profile_image) ? $profile_image : 'assets/img/default-image.png'; ?>" 
                                 alt="Profile Picture" 
                                 class="rounded-circle mb-3" 
                                 loading="lazy" 
                                 style="width: 120px; height: 120px; object-fit: cover;">

                            <!-- Name and Contact Info -->
                            <h4 class="card-title mb-1"><?php echo $fname . " " . $lname; ?></h4>
                            
                            
                            <!-- Contact Details -->
                            <div class="d-flex justify-content-center gap-3 mb-3 text-muted small">
                                <span><i class="fas fa-phone me-1"></i><?php echo $contactNo; ?></span>
                                <span>|</span>
                                <span><i class="fas fa-envelope me-1"></i><?php echo $email; ?></span>
                                <span>|</span>
                                <span><i class="fas fa-map-marker-alt me-1"></i><?php echo $location; ?></span>
                            </div>

                            <!-- Rating Section -->
                            <div class="mb-3">
                                <div class="d-flex justify-content-center align-items-center" style="font-size: 1.5rem; color: #f1c40f;">
                                    <?php
                                    $full_stars = floor($average_rating);
                                    $half_star = $average_rating - $full_stars >= 0.5;
                                    $empty_stars = 5 - ceil($average_rating);
                                    
                                    for ($i = 0; $i < $full_stars; $i++) echo "★";
                                    if ($half_star) echo "½";
                                    for ($i = 0; $i < $empty_stars; $i++) echo "☆";
                                    ?>
                                    <span style="font-size: 1rem; color: #6c757d; margin-left: 0.5rem;">(<?php echo $average_rating ?: '0.0'; ?>)</span>
                                </div>
                            </div>

                            <!-- Overview -->
                            <h6 class="text-start">Overview</h6>
                            <p class="text-start" style="font-size: 0.95rem;">
                                <?php echo $overview ?: 'No overview provided yet.'; ?>
                            </p>

                            <!-- Reviews Section -->
                            <h6 class="text-start mt-4">Recent Reviews</h6>
                            <?php if (!empty($reviews)): ?>
                                <div class="text-start">
                                    <?php foreach ($reviews as $review): ?>
                                        <div class="mb-3 p-3 bg-light rounded">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <div style="color: #f1c40f;">
                                                    <?php for ($i = 0; $i < $review['rating']; $i++) echo "★"; ?>
                                                    <?php for ($i = $review['rating']; $i < 5; $i++) echo "☆"; ?>
                                                </div>
                                                <small class="text-muted">
                                                    <?php echo date('M d, Y', strtotime($review['date'])); ?>
                                                </small>
                                            </div>
                                            <p class="mb-0" style="font-size: 0.9rem;"><?php echo $review['comment']; ?></p>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No reviews yet</p>
                            <?php endif; ?>

                            <!-- Edit Profile Button -->
                            <a href="employer-edit-profile.php" class="btn btn-primary btn-sm w-100 mb-3">Edit Profile</a>
                        <?php else: ?>
                            <!-- Show message and Add Profile button if no profile exists -->
                            <div class="text-center mb-4">
                                <img src="assets/img/default-image.png" 
                                     alt="Default Profile Picture" 
                                     class="rounded-circle mb-3" 
                                     style="width: 120px; height: 120px; object-fit: cover;">
                                <h5 class="mb-3">Complete Your Profile</h5>
                                <p class="text-muted">Add your professional details to help job seekers know more about you.</p>
                                <a href="employer-add-profile.php" class="btn btn-primary btn-sm w-100">Add Profile</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="vendors/@popperjs/popper.min.js"></script>
    <script src="vendors/bootstrap/bootstrap.min.js"></script>
    <script src="vendors/is/is.min.js"></script>
    <script src="assets/js/theme.js"></script>
    <!-- Add Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</body>
</html> 