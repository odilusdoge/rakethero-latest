<?php
include 'db_conn.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    die('Not authorized');
}

if (isset($_GET['quotationId'])) {
    $quotationId = $_GET['quotationId'];
    
    $conn = new mysqli($host, $user, $pass, $db);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Get employer contact details
    $query = "SELECT 
        u.email,
        ui.contact,
        j.location
    FROM quotations q
    JOIN applications a ON q.applications_id = a.applications_id
    JOIN jobs j ON a.jobId = j.jobs_id
    JOIN users u ON j.employerId = u.users_id
    LEFT JOIN user_info ui ON u.users_id = ui.userid
    WHERE q.quotations_id = ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $quotationId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            'email' => $row['email'],
            'contact' => $row['contact'] ?? 'Not provided',
            'location' => $row['location']
        ]);
    } else {
        echo json_encode(['error' => 'Contact information not found']);
    }
    
    $stmt->close();
    $conn->close();
}
?> 