<?php
include 'db_conn.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Check if user_id is provided in URL
if (!isset($_GET['user_id'])) {
    header("Location: employerlanding.php");
    exit();
}

$user_id = $_GET['user_id'];

// Fetch user profile data
$query = "SELECT u.username, ui.*, 
          (SELECT GROUP_CONCAT(skill_name SEPARATOR ', ') 
           FROM user_skills 
           WHERE userid = u.users_id) as skills
          FROM users u
          LEFT JOIN user_info ui ON u.users_id = ui.userid
          WHERE u.users_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$profile = $result->fetch_assoc();

if (!$profile) {
    header("Location: employerlanding.php");
    exit();
}

// Update the reviews query to prevent duplicates
$reviewsQuery = "SELECT DISTINCT
    ur.rating_id,
    ur.rated_user_id,
    ur.rater_id,
    ur.rating,
    ur.comment,
    ur.created_at,
    ui.fname as rater_fname,
    ui.lname as rater_lname,
    j.title as job_title
FROM user_ratings ur
LEFT JOIN user_info ui ON ur.rater_id = ui.userid
LEFT JOIN transactions t ON ur.transaction_id = t.transactions_id
LEFT JOIN jobs j ON t.jobid = j.jobs_id
WHERE ur.rated_user_id = ?
GROUP BY ur.rating_id
ORDER BY ur.created_at DESC";

$reviewStmt = $conn->prepare($reviewsQuery);
if (!$reviewStmt) {
    error_log("Review query preparation failed: " . $conn->error);
    $reviews = array();
    $averageRating = 0;
    $totalReviews = 0;
} else {
    $reviewStmt->bind_param("i", $user_id);
    if (!$reviewStmt->execute()) {
        error_log("Review query execution failed: " . $reviewStmt->error);
        $reviews = array();
        $averageRating = 0;
        $totalReviews = 0;
    } else {
        $result = $reviewStmt->get_result();
        $reviews = $result->fetch_all(MYSQLI_ASSOC);
        
        // Get the total reviews and average rating in a separate query
        $ratingQuery = "SELECT 
            COUNT(*) as total_reviews,
            COALESCE(ROUND(AVG(rating), 1), 0) as average_rating
        FROM user_ratings 
        WHERE rated_user_id = ?";
        
        $ratingStmt = $conn->prepare($ratingQuery);
        $ratingStmt->bind_param("i", $user_id);
        $ratingStmt->execute();
        $ratingResult = $ratingStmt->get_result();
        $ratingData = $ratingResult->fetch_assoc();
        
        $totalReviews = $ratingData['total_reviews'];
        $averageRating = $ratingData['average_rating'];
    }
}

// Add this line for debugging
error_log("Reviews data: " . print_r($reviews, true));
error_log("Average Rating: " . $averageRating);
error_log("Total Reviews: " . $totalReviews);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Profile | RaketHero</title>
     <!-- Favicons -->
     <link rel="apple-touch-icon" sizes="180x180" href="assets/img/favicons/apple-touch-icon.png">
        <link rel="icon" type="image/png" sizes="32x32" href="assets/img/favicons/favicon.ico">
        <link rel="icon" type="image/png" sizes="16x16" href="assets/img/favicons/favicon.ico">
        <link rel="shortcut icon" type="image/x-icon" href="assets/img/favicons/favicon.ico">
        <link rel="manifest" href="assets/img/favicons/manifest.json">
        <meta name="msapplication-TileImage" content="assets/img/favicons/favicon.ico">
        <meta name="theme-color" content="#ffffff">

    <link href="assets/css/theme.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        .profile-section {
            padding-top: 6rem;
        }
        .profile-image {
            width: 150px;
            height: 150px;
            object-fit: cover;
        }
        .skill-badge {
            font-size: 0.9rem;
            padding: 0.5em 1em;
            margin: 0.2em;
        }
        .reviews-section {
            background: #fff;
            border-radius: 10px;
        }

        .overall-rating {
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
        }

        .review-item {
            background-color: #fff;
            transition: all 0.2s ease;
        }

        .review-item:hover {
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .rating {
            color: #ffc107;
        }

        .review-text {
            color: #6c757d;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .bi-star-fill, .bi-star-half, .bi-star {
            font-size: 1.2rem;
            margin-right: 2px;
        }

        .recent-reviews {
            max-height: 600px;
            overflow-y: auto;
        }

        .recent-reviews::-webkit-scrollbar {
            width: 6px;
        }

        .recent-reviews::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .recent-reviews::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 3px;
        }

        .recent-reviews::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        .star-yellow {
            color: #FFD700; /* This is pure yellow/gold */
        }
        
        /* If you want to adjust the hover effect */
        .review-item:hover .star-yellow {
            color: #FFD700;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top py-3 backdrop bg-light shadow-transition">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center fw-bolder fs-2 fst-italic" href="#">
                <div class="text-info">Raket</div><div class="text-warning">Hero</div>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="employerlanding.php">Back to Dashboard</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Profile Section -->
    <section class="profile-section">
        <div class="container">
            <div class="card shadow">
                <div class="card-body">
                    <div class="text-center mb-4">
                        <img src="<?php echo !empty($profile['image_path']) ? 
                            htmlspecialchars($profile['image_path']) : 
                            'assets/img/default-image.png'; ?>" 
                            alt="Profile Picture" 
                            class="rounded-circle profile-image mb-3">
                        <h3><?php echo htmlspecialchars($profile['fname'] . ' ' . $profile['lname']); ?></h3>
                        <p class="text-muted"><?php echo htmlspecialchars($profile['title'] ?? 'No title set'); ?></p>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <h5>Contact Information</h5>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($profile['email']); ?></p>
                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($profile['contactNo']); ?></p>
                            <p><strong>Location:</strong> <?php echo htmlspecialchars($profile['location']); ?></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <h5>Skills</h5>
                            <div class="skills-container">
                                <?php
                                if (!empty($profile['skills'])) {
                                    $skills = explode(', ', $profile['skills']);
                                    foreach ($skills as $skill) {
                                        echo '<span class="badge bg-primary skill-badge">' . 
                                             htmlspecialchars($skill) . '</span>';
                                    }
                                } else {
                                    echo '<p class="text-muted">No skills listed</p>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <h5>Overview</h5>
                        <p><?php echo nl2br(htmlspecialchars($profile['overview'] ?? 'No overview provided')); ?></p>
                    </div>
 <!-- Add this where you want to show the reviews section -->
 <div class="reviews-section mt-4">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-4">Reviews and Ratings</h4>
                
                <!-- Overall Rating -->
                <div class="overall-rating mb-4">
                    <div class="d-flex align-items-center">
                        <div class="h1 mb-0 me-2"><?php echo number_format($averageRating, 1); ?></div>
                        <div class="d-flex align-items-center">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <?php if ($i <= $averageRating): ?>
                                    <i class="bi bi-star-fill star-yellow"></i>
                                <?php elseif ($i - 0.5 <= $averageRating): ?>
                                    <i class="bi bi-star-half star-yellow"></i>
                                <?php else: ?>
                                    <i class="bi bi-star star-yellow"></i>
                                <?php endif; ?>
                            <?php endfor; ?>
                            <span class="ms-2 text-muted">(<?php echo $totalReviews; ?> reviews)</span>
                        </div>
                    </div>
                </div>

                <!-- Recent Reviews -->
                <div class="recent-reviews">
                    <h5 class="mb-3">Recent Reviews</h5>
                    <?php if (empty($reviews)): ?>
                        <p class="text-muted">No reviews yet</p>
                    <?php else: ?>
                        <?php foreach ($reviews as $review): ?>
                            <div class="review-item mb-4 p-3 border rounded">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($review['rater_fname'] . ' ' . $review['rater_lname']); ?></h6>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($review['job_title']); ?>
                                        </small>
                                    </div>
                                    <div class="text-muted small">
                                        <?php echo date('M d, Y', strtotime($review['created_at'])); ?>
                                    </div>
                                </div>
                                <div class="rating mb-2">
                                    <?php 
                                    $rating = $review['rating'];
                                    for ($i = 1; $i <= 5; $i++): 
                                    ?>
                                        <i class="bi <?php echo ($i <= $rating) ? 'bi-star-fill' : 'bi-star'; ?> star-yellow"></i>
                                    <?php endfor; ?>
                                </div>
                                <p class="review-text mb-0">
                                    <?php echo nl2br(htmlspecialchars($review['comment'])); ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <br> 
  
                    <div class="text-center">
                        <a href="employerlanding.php" class="btn btn-primary">Back to Dashboard</a>
                    </div>
                </div>
            </div>
        </div>
        
    </section>

   

    <script src="vendors/@popperjs/popper.min.js"></script>
    <script src="vendors/bootstrap/bootstrap.min.js"></script>
    <script src="vendors/is/is.min.js"></script>
    <script src="assets/js/theme.js"></script>
</body>
</html> 