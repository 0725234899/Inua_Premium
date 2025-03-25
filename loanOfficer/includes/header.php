<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$email=$_SESSION['email'];
?>
<!-- ======= Header ======= -->
<header id="header" class="header" style="background-color:red">
        <div class="container-fluid d-flex align-items-center justify-content-between">
          
                <h1 class="sitename">Inua Premium Services</h1><span>.</span>
            

            <nav id="navmenu" class="navmenu">
                <ul>
                <?php $role=getRole($_SESSION['role']);
               
                if($role['name']=='Admin')
                {
                ?>
                    <li><a href="admin.php" class="active">Admin</a></li>
                    <?php } ?>
                    <li><a href="../index.html?logout=1">Logout</a></li>
                </ul>
                <i class="mobile-nav-toggle d-xl-none bi bi-list"></i>
            </nav>
        </div>
    </header>
