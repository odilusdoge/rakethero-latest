<?php
include 'db_conn.php';
session_start();

// Set JSON header
header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $userType = $_POST['userType'];

    // Validate userType
    if (!in_array($userType, ['employer', 'jobseeker'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid user type selected'
        ]);
        exit;
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // First check if username exists
        $check_query = "SELECT username FROM users WHERE username = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("s", $username);
        $check_stmt->execute();
        $result = $check_stmt->get_result();

        if ($result->num_rows > 0) {
            throw new Exception("Username already exists");
        }

        // Insert into users table with correct userType
        $user_query = "INSERT INTO users (username, password, userType) VALUES (?, ?, ?)";
        $user_stmt = $conn->prepare($user_query);
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $user_stmt->bind_param("sss", $username, $hashedPassword, $userType);
        
        if (!$user_stmt->execute()) {
            throw new Exception("Error creating user account");
        }

        $user_id = $conn->insert_id;

        // Insert into user_info table (without email)
        $info_query = "INSERT INTO user_info (userid) VALUES (?)";
        $info_stmt = $conn->prepare($info_query);
        $info_stmt->bind_param("i", $user_id);
        
        if (!$info_stmt->execute()) {
            throw new Exception("Error creating user info");
        }

        // Set session variables after successful signup
        $_SESSION['user_id'] = $user_id;
        $_SESSION['username'] = $username;
        $_SESSION['userType'] = $userType;

        // Commit transaction
        $conn->commit();

        // Return success JSON response
        echo json_encode([
            'success' => true,
            'message' => 'Signup successful! Please log in.'
        ]);

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        // Return error JSON response
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    // Return error for invalid request method
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
?>
