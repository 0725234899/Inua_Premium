<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['email']) || empty($_SESSION['email'])) {
    header("Location: ../index.html");
    exit();
}
// Include functions and database connection
?>
<!-- ======= Header ======= -->
<header id="header" class="header" style="background: linear-gradient(90deg, #00c6ff, #0072ff); 
    padding: 15px 0; position: fixed; top: 0; width: 100%; z-index: 1000;">
    
    <div class="container-fluid d-flex align-items-center justify-content-between">
        <!-- Logo / Site Name -->
        <h1 class="sitename text-white d-flex align-items-center" style="font-size: 24px; font-weight: bold; margin: 0;">
            <img src="../assets/img/logo.png" alt="Inua Premium Logo" style="height: 40px; width: auto; margin-right: 10px;">
            Inua Premium Services
        </h1>

        <!-- Navigation Menu -->
        <nav id="navmenu" class="navmenu">
            <ul class="d-flex align-items-center" style="gap: 20px; list-style: none; margin: 0; padding: 0;">
                <?php 
                $role = getRole($_SESSION['role']);
                if ($role['name'] == 'Admin') { ?>
                    <li>
                        <a href="admin.php" class="nav-link text-white" style="font-size: 18px;">
                            <i class="fas fa-user-shield"></i> Admin
                        </a>
                    </li>
                <?php } ?>
                
                <li>
                    <a href="../logout.php" class="nav-link text-white" style="font-size: 18px;">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </li>
            </ul>
            <i class="mobile-nav-toggle d-xl-none fas fa-bars text-white" style="font-size: 24px; cursor: pointer;"></i>
        </nav>
    </div>
</header>

<!-- Add Padding to Prevent Overlapping Content -->
<style>
    body {
        padding-top: 70px; /* Adjust to match header height */
    }
</style>

<!-- FontAwesome for Icons -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/js/all.min.js"></script>
<!-- FontAwesome CDN -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/js/all.min.js"></script>

