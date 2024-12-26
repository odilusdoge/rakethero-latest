<?php
function createNotification($conn, $user_id, $from_user_id, $job_id, $type, $message = '') {
    $stmt = $conn->prepare("
        INSERT INTO notifications (
            user_id, 
            from_user_id, 
            job_id, 
            type, 
            message, 
            created_at, 
            is_read
        ) VALUES (?, ?, ?, ?, ?, NOW(), 0)
    ");

    if (!$stmt) {
        error_log("Failed to prepare notification statement: " . $conn->error);
        return false;
    }

    $stmt->bind_param("iiiss", $user_id, $from_user_id, $job_id, $type, $message);
    $result = $stmt->execute();

    if (!$result) {
        error_log("Failed to create notification: " . $stmt->error);
    }

    $stmt->close();
    return $result;
}
?> 