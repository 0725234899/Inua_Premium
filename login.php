<?php
session_start();
require_once("includes/functions.php");
if (isset($_POST['login'])) {
    // Sanitize and validate inputs
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $role = filter_var($_POST['role'], FILTER_SANITIZE_STRING);

    if (filter_var($email, FILTER_VALIDATE_EMAIL) && !empty($password) && !empty($role)) {
        // Check login credentials
        $res = login($email, $password, $role);
        $sp = explode(",", $res);

        if ($sp[0] == '1') {
            $_SESSION['email'] = $email;
            $_SESSION['role'] = $role;

            // Redirect based on role
            switch ($role) {
                case '1':
                    // Admin
                    header("Location: admin/");
                    break;
                case '2':
                    // Another role, such as Manager
                    header("Location: loanOfficer/index.php");
                    break;
                case '4':
                    // Client
                    header("Location: manager/index.php");
                    break;
                default:
                    $error_message = "Invalid role.";
                    break;
            }
            exit();
        } else {
            $error_message = "Invalid email or password.";
        }
    } else {
        $error_message = "Please fill in all fields correctly.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Login - Inua Premium Services</title>
    <meta content="Login to access Inua Premium Services" name="description">
    <meta content="login, microfinance, financial services" name="keywords">
    
    <!-- Favicons -->
    <link href="assets/img/logo.png" rel="icon">
    <link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon">
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;500;600;700&family=Montserrat:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Vendor CSS Files -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/vendor/aos/aos.css" rel="stylesheet">
    <link href="assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">
    <link href="assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link href="assets/css/main.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color:hsl(232, 40.50%, 92.70%);
            --secondary-color: #2a2c39;
            --light-color:rgb(158, 161, 164);
            --dark-color: #212529;
        }
        
        body.login-page {
            background: colorrgb(31, 139, 255);;
            background-image: url('assets/img/hero.jpeg');
            background-size: cover;
            background-position: fixed;
            background-attachment: fixed;
            position: relative;
        }
        
        body.login-page::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(42, 44, 57, 0.8);
            z-index: -1;
        }
        
        .login-section {
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 80px 0;
        }
        
        .login-form {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
            border: none;
            background: rgba(255, 255, 255, 0.95);
        }
        
        .login-form:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }
        
        .login-form h2 {
            color: var(--dark-color);
            font-weight: 600;
            margin-bottom: 30px;
        }
        
        .form-control {
            padding: 12px 15px;
            border-radius: 8px;
            border: 1px solidrgb(7, 242, 23);
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(232, 69, 69, 0.25);
        }
        
        .btn-primary {
            background-color: var(--primary-color) !important;
            border-color: var(--primary-color) !important;
            padding: 12px 15px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background-color:rgb(20, 238, 74) !important;
            border-color: #d63030 !important;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(232, 69, 69, 0.3);
        }
        
        .login-footer {
            margin-top: 25px;
            text-align: center;
            color: #6c757d;
        }
        
        .login-footer a {
            color: var(--primary-color);
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .login-footer a:hover {
            color: #d63030;
            text-decoration: underline;
        }
        
        .login-header {
            background: linear-gradient(135deg, var(--primary-color), #ff6b6b);
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 15px 15px 0 0;
        }
        
        .login-header img {
            width: 80px;
            margin-bottom: 15px;
        }
        
        .login-header h2 {
            color: white;
            margin-bottom: 10px;
        }
        
        .login-header p {
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 0;
        }
        
        .form-content {
            padding: 30px;
        }
        
        .form-label {
            font-weight: 500;
            color: #495057;
        }
        
        .input-group {
            position: relative;
        }
        
        .input-group-text {
            background-color: #f8f9fa;
            border: 1px solid #ced4da;
            border-right: none;
        }
        
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 10;
            cursor: pointer;
            color: #6c757d;
        }
    </style>
</head>
<body class="login-page">
    <header id="header" class="header d-flex align-items-center sticky-top">
        <div class="container-fluid position-relative d-flex align-items-center justify-content-between">
            <a href="index.html" class="logo d-flex align-items-center me-auto me-xl-0">
                <img src="assets/img/logo.png" alt="Inua Logo" height="50">
                <h1 class="sitename ms-2">Inua Premium</h1><span>.</span>
            </a>
            <nav id="navmenu" class="navmenu">
                <ul>
                    <li><a href="index.html#hero">Home</a></li>
                    <li><a href="index.html#about">About</a></li>
                    <li><a href="index.html#services">Services</a></li>
                    <li><a href="index.html#pricing">Loans</a></li>
                    <li><a href="index.html#contact">Contact</a></li>
                </ul>
                <i class="mobile-nav-toggle d-xl-none bi bi-list"></i>
            </nav>
            <a class="btn-getstarted" href="login.php">Login</a>
        </div>
    </header>
    
    <main class="main">
        <section id="login" class="login-section">
            <div class="container" data-aos="fade-up">
                <div class="row justify-content-center">
                    <div class="col-lg-5 col-md-8 animate__animated animate__fadeIn">
                        <div class="login-form">
                            <div class="login-header">
                                <img src="assets/img/logo.png" alt="Inua Logo" class="img-fluid">
                                <h2>Welcome Back</h2>
                                <p>Sign in to access your account</p>
                            </div>
                            
                            <div class="form-content">
                                <?php if (isset($error_message)): ?>
                                    <div class="alert alert-danger" role="alert">
                                        <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error_message); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <form method="post" action="" class="needs-validation" novalidate>
                                    <div class="mb-4">
                                        <label for="role" class="form-label">User Type</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                                            <?php
                                            $roles = getRoles(); // Assuming getRoles() returns an array with role id and name
                                            ?>
                                            <select class="form-control" id="role" name="role" required>
                                                <option value="">Select user type</option>
                                                <?php
                                                foreach ($roles as $role) {
                                                    echo "<option value='" . htmlspecialchars($role['id']) . "'>" . htmlspecialchars($role['name']) . "</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label for="email" class="form-label">Email Address</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                            <input type="email" id="email" name="email" required class="form-control" placeholder="Enter your email" autocomplete="email">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label for="password" class="form-label">Password</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                            <input type="password" id="password" name="password" required class="form-control" placeholder="Enter your password" autocomplete="current-password">
                                            <span class="password-toggle" onclick="togglePassword()"><i class="bi bi-eye"></i></span>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-4 form-check">
                                        <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                        <label class="form-check-label" for="remember">Remember me</label>
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary btn-block" name="login">
                                            <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                                        </button>
                                    </div>
                                    
                                    <div class="login-footer">
                                        
                                        <p>Don't have an account? <a href="index.html#contact">Contact us</a></p>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
    <footer id="footer" class="footer position-relative light-background">
        <!-- Footer content here -->
    </footer>
    <a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center animate__animated animate__fadeIn"><i class="bi bi-arrow-up-short"></i></a>
    <div id="preloader"></div>
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/vendor/php-email-form/validate.js"></script>
    <script src="assets/vendor/aos/aos.js"></script>
    <script src="assets/vendor/glightbox/js/glightbox.min.js"></script>
    <script src="assets/vendor/purecounter/purecounter_vanilla.js"></script>
    <script src="assets/vendor/imagesloaded/imagesloaded.pkgd.min.js"></script>
    <script src="assets/vendor/isotope-layout/isotope.pkgd.min.js"></script>
    <script src="assets/vendor/swiper/swiper-bundle.min.js"></script>
    
    <script>
        // Password toggle functionality
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const icon = document.querySelector('.password-toggle i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }
        
        // Form validation
        (function () {
            'use strict'
            
            // Fetch all forms to apply validation
            var forms = document.querySelectorAll('.needs-validation')
            
            // Loop over forms and prevent submission if invalid
            Array.prototype.slice.call(forms)
                .forEach(function (form) {
                    form.addEventListener('submit', function (event) {
                        if (!form.checkValidity()) {
                            event.preventDefault()
                            event.stopPropagation()
                        }
                        
                        form.classList.add('was-validated')
                    }, false)
                })
        })()
    </script>
    
    <script src="assets/js/main.js"></script>
</body>
</html>
