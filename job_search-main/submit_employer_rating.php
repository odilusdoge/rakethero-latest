<?php
include 'db_conn.php';
session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$transactionId = $_POST['transactionId'] ?? null;
$employerId = $_POST['employerId'] ?? null;
$rating = $_POST['rating'] ?? null;
$comment = $_POST['comment'] ?? null;

error_log("Submitting rating with transaction ID: " . $transactionId);

// Verify transaction exists
$checkQuery = "SELECT transactions_id FROM transactions WHERE transactions_id = ?";
$stmt = $conn->prepare($checkQuery);
$stmt->bind_param("i", $transactionId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    error_log("Transaction not found: " . $transactionId);
    echo json_encode(['success' => false, 'message' => 'Invalid transaction ID']);
    exit;
}

// Check if user has already rated for this transaction
$checkRatingQuery = "SELECT rating_id FROM user_ratings 
                    WHERE transaction_id = ? AND rater_id = ?";
$stmt = $conn->prepare($checkRatingQuery);
$stmt->bind_param("ii", $transactionId, $_SESSION['user_id']);
$stmt->execute();
$ratingResult = $stmt->get_result();

if ($ratingResult->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'You have already rated this employer for this transaction']);
    exit;
}

try {
    $query = "INSERT INTO user_ratings (rated_user_id, rater_id, rating, comment, transaction_id) 
              VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iiisi", $employerId, $_SESSION['user_id'], $rating, $comment, $transactionId);
    
    if ($stmt->execute()) {
        // Update average rating in user_info
        $avgQuery = "UPDATE user_info ui 
                    SET ui.rating = (
                        SELECT AVG(r.rating) 
                        FROM user_ratings r 
                        WHERE r.rated_user_id = ?
                    )
                    WHERE ui.userid = ?";
        $avgStmt = $conn->prepare($avgQuery);
        $avgStmt->bind_param("ii", $employerId, $employerId);
        $avgStmt->execute();

        echo json_encode(['success' => true, 'message' => 'Rating submitted successfully']);
    } else {
        error_log("Error inserting rating: " . $stmt->error);
        throw new Exception("Error submitting rating");
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 