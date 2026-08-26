<?php
// Start session only if not already started and no headers have been sent.
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

if (!isset($_SESSION['email']) || empty($_SESSION['email'])) {
    if (!headers_sent()) {
        header("Location: ../index.html");
        exit();
    }
    return;
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
        <nav id="navmenu" class="navmenu d-flex align-items-center justify-content-end">
            <button id="sidebarToggle" class="mobile-nav-toggle btn btn-link text-white p-0 me-3" type="button" aria-label="Toggle sidebar" style="font-size: 24px;">
                <i class="fas fa-bars"></i>
            </button>
            <ul class="d-flex align-items-center mb-0" style="gap: 20px; list-style: none; margin: 0; padding: 0;">
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
        </nav>
    </div>
</header>

<!-- Add Padding to Prevent Overlapping Content -->
<style>
    body {
        padding-top: 70px; /* Adjust to match header height */
    }

    .sidebar {
        transition: transform 0.3s ease;
    }

    .sidebar.collapsed {
        transform: translateX(-100%);
    }

    .main.sidebar-collapsed,
    main.sidebar-collapsed,
    #mainContent.sidebar-collapsed {
        margin-left: 0 !important;
    }

    @media (max-width: 1199px) {
        .sidebar {
            position: fixed;
            top: 70px;
            left: 0;
            z-index: 1050;
            width: 250px;
            height: calc(100vh - 70px);
            background-color: #f8f9fa;
        }
    }
</style>

<!-- FontAwesome for Icons -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/js/all.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/js/all.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var toggleButton = document.getElementById('sidebarToggle');
        var sidebar = document.querySelector('.sidebar');
        var mainContent = document.querySelector('.main, main, #mainContent');

        if (!toggleButton || !sidebar) {
            return;
        }

        toggleButton.addEventListener('click', function () {
            sidebar.classList.toggle('collapsed');
            if (mainContent) {
                mainContent.classList.toggle('sidebar-collapsed');
            }
        });
    });
</script>

