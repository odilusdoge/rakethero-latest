<?php
include 'db_conn.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    die('Not authorized');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $applicationId = $_POST['applicationId'];
    $proposal = $_POST['proposal'];
    
    // Create database connection
    $conn = new mysqli($host, $user, $pass, $db);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Use prepared statement to prevent SQL injection
    $stmt = $conn->prepare("UPDATE applications SET proposal = ? WHERE applications_id = ? AND userid = ?");
    $stmt->bind_param("sii", $proposal, $applicationId, $_SESSION['user_id']);
    
    if ($stmt->execute()) {
        echo "Proposal updated successfully";
    } else {
        echo "Error updating proposal: " . $conn->error;
    }
    
    $stmt->close();
    $conn->close();
}
?> 