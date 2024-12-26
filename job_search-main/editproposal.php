<?php
include 'db_conn.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $jobId = $_POST['jobId'];
    $proposal = $_POST['proposal'];
    $userId = $_SESSION['user_id'];

    $stmt = $conn->prepare("UPDATE applications SET proposal = ? WHERE jobId = ? AND userId = ?");
    $stmt->bind_param("sii", $proposal, $jobId, $userId);

    if ($stmt->execute()) {
        echo "Proposal updated successfully!";
    } else {
        echo "Error: " . $stmt->error;
    }
}
?>
