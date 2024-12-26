<?php
include 'db_conn.php';
session_start();

$userId = $_SESSION['user_id']; // Ensure user ID is set
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$query = "SELECT 
            jobs.jobs_id, 
            jobs.title, 
            applications.status AS app_status,
            applications.applicationDate, 
            applications.proposal
          FROM 
            applications
          JOIN 
            jobs ON applications.jobId = jobs.jobs_id
          WHERE 
            applications.userId = $userId";

$result = $conn->query($query);

if ($result && $result->num_rows > 0):
    while ($job = $result->fetch_assoc()):
?>
        <div class="job-card" data-jobid="<?php echo $job['jobs_id']; ?>" 
             data-title="<?php echo htmlspecialchars($job['title']); ?>" 
             data-proposal="<?php echo htmlspecialchars($job['proposal']); ?>" 
             data-status="<?php echo htmlspecialchars($job['app_status']); ?>" >
            <h5><?php echo htmlspecialchars($job['title']); ?></h5>
            <p><strong>Application Status:</strong> <?php echo htmlspecialchars($job['app_status']); ?></p>
            <p><strong>Applied On:</strong> <?php echo htmlspecialchars($job['applicationDate']); ?></p>
            <button class="btn btn-info view-details-btn">View Details</button>
        </div>
<?php
    endwhile;
else:
    echo "<p>No applied jobs found.</p>";
endif;
$conn->close();
?>
