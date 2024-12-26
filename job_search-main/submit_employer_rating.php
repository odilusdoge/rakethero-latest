<?php
include 'db_conn.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$transactionId = $_POST['transactionId'] ?? null;
$employerId = $_POST['employerId'] ?? null;
$rating = $_POST['rating'] ?? null;
$comment = $_POST['comment'] ?? '';

try {
    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // Check if rating already exists
        $checkStmt = $conn->prepare("SELECT rating_id FROM user_ratings WHERE transaction_id = ? AND rater_id = ?");
        $checkStmt->bind_param("ii", $transactionId, $_SESSION['user_id']);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            throw new Exception("You have already rated this user for this transaction");
        }

        // Insert user rating
        $stmt = $conn->prepare("INSERT INTO user_ratings (rater_id, rated_user_id, rating, comment, transaction_id) 
                               VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iiisi", $_SESSION['user_id'], $employerId, $rating, $comment, $transactionId);
        $stmt->execute();

        // Update average rating in user_info
        $avgQuery = "UPDATE user_info ui 
                    SET ui.rating = (
                        SELECT AVG(r.rating) 
                        FROM user_ratings r 
                        WHERE r.rated_user_id = ?
                    )
                    WHERE ui.userid = ?";
        $stmt = $conn->prepare($avgQuery);
        $stmt->bind_param("ii", $employerId, $employerId);
        $stmt->execute();

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Rating submitted successfully']);
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?> 