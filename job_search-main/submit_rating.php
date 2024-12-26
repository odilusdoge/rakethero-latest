<?php
include 'db_conn.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$quotationId = $_POST['quotationId'] ?? null;
$jobId = $_POST['jobId'] ?? null;
$rating = $_POST['rating'] ?? null;
$feedback = $_POST['feedback'] ?? '';

try {
    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // Add at the beginning of the try block
        error_log("Submitting rating - JobID: " . $jobId . ", QuotationID: " . $quotationId . ", UserID: " . $_SESSION['user_id']);

        // Check if rating already exists
        $checkStmt = $conn->prepare("SELECT reviews_id FROM reviews 
                                   WHERE jobId = ? AND userId = ? AND quotation_id = ?");
        $checkStmt->bind_param("iii", $jobId, $_SESSION['user_id'], $quotationId);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            throw new Exception("You have already rated this job");
        }

        // Insert review
        $stmt = $conn->prepare("INSERT INTO reviews (userId, jobId, rating, comment, reviewDate, quotation_id) 
                               VALUES (?, ?, ?, ?, NOW(), ?)");
        $stmt->bind_param("iiiss", $_SESSION['user_id'], $jobId, $rating, $feedback, $quotationId);
        $stmt->execute();

        // After the insert
        error_log("Rating inserted successfully");

        $conn->commit();
        // After commit
        error_log("Transaction committed successfully");
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