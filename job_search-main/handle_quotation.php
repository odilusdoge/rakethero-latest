<?php
session_start();
include 'db_conn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quotation_id = $_POST['quotation_id'];
    $action = $_POST['action'];
    $user_id = $_SESSION['user_id'];

    // Verify that this quotation belongs to the current user
    $stmt = $conn->prepare("
        SELECT q.* 
        FROM quotations q
        JOIN applications a ON q.application_id = a.applications_id
        WHERE q.quotation_id = ? AND a.userId = ?
    ");
    $stmt->bind_param("ii", $quotation_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo "Unauthorized access";
        exit;
    }

    // Update quotation status
    $status = ($action === 'accept') ? 'Accepted' : 'Rejected';
    $updateStmt = $conn->prepare("UPDATE quotations SET status = ? WHERE quotation_id = ?");
    $updateStmt->bind_param("si", $status, $quotation_id);

    if ($updateStmt->execute()) {
        echo "Quotation has been " . strtolower($status);
    } else {
        echo "Error updating quotation status";
    }
} else {
    echo "Invalid request method";
}
?> 