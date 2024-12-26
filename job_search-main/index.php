<?php 
session_start();
include 'db_conn.php';    
if (isset($_SESSION['error'])) {
    echo '<script type="text/javascript">';
    echo 'alert("' . $_SESSION['error'] . '");';
    echo '</script>';

    // Clear the error message after displaying it
    unset($_SESSION['error']);
}
?>
<!DOCTYPE html>
<html lang="en-US" dir="ltr" class="chrome windows">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RaketHero | Be A Local Raket Hero Today</title>

    <!-- Favicons -->
    <link rel="apple-touch-icon" sizes="180x180" href="assets/img/favicons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/img/favicons/favicon.ico">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/img/favicons/favicon.ico">
    <link rel="shortcut icon" type="image/x-icon" href="assets/img/favicons/favicon.ico">
    <link rel="manifest" href="assets/img/favicons/manifest.json">
    <meta name="msapplication-TileImage" content="assets/img/favicons/favicon.ico">
    <meta name="theme-color" content="#ffffff">

    <!-- Stylesheets -->
    <link href="assets/css/theme.css" rel="stylesheet">

    <style>
        /* Floating form styles */
        #loginForm,
        #signupForm {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 650px;
            background-color: white;
            border: 1px solid #ccc;
            padding: 20px;
            box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            overflow-y: auto;
            max-height: 400px;
        }

        #loginOverlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }

        /* Button to close the forms */
        .close-btn {
            float: right;
            cursor: pointer;
            font-size: 18px;
        }
    </style>

</head>

<body>
<?php if (isset($_GET['error'])): ?>
    <div style="color: red; text-align: center; margin-top: 10px;">
        <?php echo htmlspecialchars(urldecode($_GET['error'])); ?>
    </div>
<?php endif; ?>
    <!-- Main Content -->
    <main class="main" id="top">
        <nav class="navbar navbar-expand-lg navbar-light fixed-top py-3 backdrop bg-light shadow-transition">
            <div class="container">
                <a class="navbar-brand d-flex align-items-center fw-bolder fs-2 fst-italic" href="#">
                    <div class="text-info">Raket</div>
                    <div class="text-warning">Hero</div>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                    data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="true"
                    aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
                <div class="navbar-collapse border-top border-lg-0 mt-4 mt-lg-0 collapse show"
                    id="navbarSupportedContent">
                    <ul class="navbar-nav ms-auto pt-2 pt-lg-0">
                        <li class="nav-item px-2"><a class="nav-link fw-medium active" aria-current="page" href="#">Home</a></li>
                        <li class="nav-item px-2"><a class="nav-link fw-medium" href="#features">Jobs</a></li>
                        <li class="nav-item px-2"><a class="nav-link fw-medium" href="#pricing">Contact</a></li>
                    </ul>
                    <form class="ps-lg-5">
                        <!-- Buttons for login and signup -->
                        <button class="btn btn-lg btn-primary rounded-pill bg-gradient order-0" type="button"
                            onclick="showLoginForm()">Log In</button>
                        <button class="btn btn-lg btn-primary rounded-pill bg-gradient order-0" type="button"
                            onclick="showSignupForm()">Sign Up</button>
                    </form>
                </div>
            </div>
        </nav>

        <!-- Login Overlay and Forms -->
        <div id="loginOverlay"></div>

        <!-- Login Form -->
        <!-- Login Form -->
        <div id="loginForm">
            <span class="close-btn" onclick="hideForms()">×</span>
            <h3>Log In</h3>
            <form id="login" method="POST" action="login.php">
                <div class="mb-3">
                    <label for="loginUsername" class="form-label">Username</label>
                    <input type="text" class="form-control" id="loginUsername" name="username" required>
                </div>
                <div class="mb-3">
                    <label for="loginPassword" class="form-label">Password</label>
                    <input type="password" class="form-control" id="loginPassword" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary">Log In</button>
            </form>
        </div>  


        
        <!-- Sign Up Form -->
       <div id="signupForm">
    <span class="close-btn" onclick="hideForms()">×</span>
    <h3>Sign Up</h3>
    <form id="signup" method="POST" onsubmit="submitSignup(event)">
        <div class="mb-3">
            <label for="signupUsername" class="form-label">Username</label>
            <input type="text" class="form-control" id="signupUsername" name="username" required>
        </div>
        <div class="mb-3">
            <label for="signupPassword" class="form-label">Password</label>
            <input type="password" class="form-control" id="signupPassword" name="password" required>
        </div>
        <div class="mb-3">
            <label for="confirmPassword" class="form-label">Confirm Password</label>
            <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" required>
        </div>
        <div class="mb-3">
            <label class="form-label">User Type</label>
            <select name="userType" class="form-select" required>
                <option value="">Select User Type</option>
                <option value="jobseeker">Job Seeker</option>
                <option value="employer">Employer</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Sign Up</button>
    </form>
</div>
</div>
        <section class="py-0" id="home">
            <div class="bg-holder"
                style="background-image:url(assets/img/illustrations/cartoon.svg);">
            </div>


            <div class="container">
                <div class="row align-items-center min-vh-75 min-vh-md-100">
                    <div class="col-md-7 col-lg-6 py-6 text-sm-start text-center">
                        <h1 class="mt-6 mb-sm-4 display-2 fw-semi-bold lh-sm fs-4 fs-lg-6 fs-xxl-8">Find A Racket <br
                                class="d-block d-lg-none d-xl-block">Today!</h1>
                        <p class="mb-4 fs-1">Connecting talent with opportunities</p>
                       
                    </div>
                </div>
            </div>
        </section>
        <!-- Additional content can go here -->
    </main>
    <!-- JavaScripts -->
    <script src="vendors/@popperjs/popper.min.js"></script>
    <script src="vendors/bootstrap/bootstrap.min.js"></script>
    <script src="vendors/is/is.min.js"></script>
    <script src="https://polyfill.io/v3/polyfill.min.js?features=window.scroll"></script>
    <script src="assets/js/theme.js"></script>

    <script>

    </script>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,200;1,300;1,400&display=swap"
        rel="stylesheet">

</body>

<script>
   

    function submitSignup(event) {
        event.preventDefault();

        const formData = new FormData(document.getElementById('signup'));
        
        // Validate passwords match
        if (formData.get('password') !== formData.get('confirmPassword')) {
            alert('Passwords do not match!');
            return;
        }

        fetch('signup.php', {
            method: 'POST',
            body: formData
        })
        .then(async response => {
            const text = await response.text();
            try {
                // Log the response for debugging
                console.log('Server response:', text);
                return JSON.parse(text);
            } catch (e) {
                console.error('Failed to parse JSON:', text);
                throw new Error('Server returned invalid JSON');
            }
        })
        .then(data => {
            if (data.success) {
                alert('Signup successful! Please log in.');
                hideForms();
                showLoginForm();
            } else {
                alert(data.message || 'Signup failed. Please try again.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error during signup: ' + error.message);
        });
    }

    // Functions to show/hide forms
    function hideForms() {
        document.getElementById('signupForm').style.display = 'none';
        document.getElementById('loginForm').style.display = 'none';
        document.getElementById('loginOverlay').style.display = 'none';
    }

    function showLoginForm() {
        document.getElementById('loginForm').style.display = 'block';
        document.getElementById('loginOverlay').style.display = 'block';
    }

    function showSignupForm() {
        document.getElementById('signupForm').style.display = 'block';
        document.getElementById('loginOverlay').style.display = 'block';
    }
 
</script>
</html>
