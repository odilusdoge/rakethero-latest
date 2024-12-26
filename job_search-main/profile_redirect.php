<?php
session_start();

if (!isset($_SESSION['userType'])) {
    header("Location: index.php");
    exit();
}

if ($_SESSION['userType'] === 'employer') {
    header("Location: employersprofile.php");
} else {
    header("Location: profile.php");
}
exit();
?> 