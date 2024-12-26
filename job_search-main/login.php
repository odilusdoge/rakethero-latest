<?php
session_start();

include 'db_conn.php';
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['users_id']; // Assuming users_id is the primary key in the users table
            $_SESSION['username'] = $user['username'];
            $_SESSION['userType'] = $user['userType'];

            // Debugging output
            echo "Session username: " . $_SESSION['username'];
            echo "Session userType: " . $_SESSION['userType'];
            echo "Session user_id: " . $_SESSION['user_id'];
            // exit(); // Uncomment this to stop execution and check output in the browser

            // Redirect based on user type
            if ($user['userType'] == 'employer') {
                header("Location: employerlanding.php");
            } else {
                header("Location: jobseekerlanding.php");
            }
            
        } else {
            header("Location: index.php");
            $_SESSION['error'] = "Invalid password."; // Set error message
            
            exit();
        }
    } else {
        header("Location: index.php");
        $_SESSION['error'] = "No user found."; // Set error message
        
        exit();
    }
}
$conn->close();
?>
<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


// Check if there is an error message in the session
if (isset($_SESSION['error'])) {
    echo '<script type="text/javascript">';
    echo 'alert("' . $_SESSION['error'] . '");';
    echo '</script>';

    // Clear the error message from the session
    unset($_SESSION['error']);
}
?>