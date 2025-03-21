<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Admin Dashboard - Microfinance</title>
    <meta content="" name="description">
    <meta content="" name="keywords">

    <style>
        :root {
            --background-color: #ffffff;
            --default-color: #212529;
            --heading-color: #32353a;
            --accent-color: #e84545;
            --surface-color: #ffffff;
            --contrast-color: #ffffff;
            --nav-color: #3a3939;
            --nav-hover-color: #e84545;
            --nav-mobile-background-color: #ffffff;
            --nav-dropdown-background-color: #ffffff;
            --nav-dropdown-color: #3a3939;
            --nav-dropdown-hover-color: #e84545;
        }

        body {
            background-color: var(--background-color);
            color: var(--default-color);
            font-family: 'Open Sans', sans-serif;
            margin: 0;
        }

        .header {
            background-color: var(--accent-color);
            color: var(--contrast-color);
            padding: 10px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header .logo h1 {
            color: var(--contrast-color);
            margin: 0;
            font-size: 24px;
        }

        .header .navmenu ul {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
        }

        .header .navmenu ul li {
            margin-right: 20px;
        }

        .header .navmenu ul li a {
            color: var(--contrast-color);
            text-decoration: none;
        }

        .header .navmenu ul li a.active, .header .navmenu ul li a:hover {
            color: var(--nav-hover-color);
        }

        .sidebar {
            background-color: var(--nav-mobile-background-color);
            color: var(--nav-color);
            padding: 20px;
            width: 250px;
            position: fixed;
            height: 100%;
            overflow: auto;
        }

        .sidebar .nav-item .nav-link {
            color: var(--nav-color);
            padding: 10px 15px;
            text-decoration: none;
            display: block;
        }

        .sidebar .nav-item .nav-link.active, .sidebar .nav-item .nav-link:hover {
            color: var(--nav-hover-color);
        }

        .main {
            margin-left: 270px;
            padding: 20px;
        }

        .dashboard-metrics {
            display: flex;
            justify-content: space-around;
            margin-top: 20px;
        }

        .metric {
            background-color: var(--surface-color);
            border: 1px solid var(--default-color);
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            flex: 1;
            margin: 0 10px;
        }

        .metric h2 {
            margin: 0;
            font-size: 2em;
        }

        .metric p {
            margin: 5px 0 0;
        }
    </style>

    <!-- Favicons -->
    <link href="/assets/img/logo.png" rel="icon">
    <link href="/assets/img/logo.png" rel="apple-touch-icon">

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans&family=Montserrat&family=Poppins&display=swap" rel="stylesheet">

    <!-- Vendor CSS Files -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">

    <!-- Main CSS File -->
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<body class="admin-page">

    <!-- End Header -->
<?php
include("../includes/functions.php");
include("includes/header.php");
?>
    <!-- ======= Sidebar ======= -->
   <?php
   include("../includes/sidebar.php");

   ?>
    <!-- ======= Main ======= -->
    <main class="main">
        <section id="admin-dashboard" class="admin-dashboard section">
            <div class="container">

    <h2>Add Borrower</h2>
    <form action="insert_borrower.php" method="post" enctype="multipart/form-data">
        Loan Officer: <input type="text" name="loanOfficer" value="<?php echo $_SESSION['email']; ?>" class="form-control" required readonly><br>
        
        Full Name: <input type="text" name="full_name" class="form-control" required><br>
         <!-- Passport Photo -->
         Passport Photo:
        <input type="file" name="passport_photo" class="form-control" accept=".jpg, .jpeg, .png" required><br>

        
      
        
        ID Number: <input type="text" name="id_number" class="form-control" required><br>
         <!-- ID Upload -->
         Upload ID Document (PDF or Image): 
        <input type="file" name="id_upload" class="form-control" accept=".pdf, .jpg, .jpeg, .png" required><br>
        Business Name: <input type="text" name="business_name" class="form-control"><br>
        
        Mobile: <input type="text" name="mobile" class="form-control"><br>
        
        Email: <input type="email" name="email" class="form-control"><br>
        
        <!-- Hidden Fields for Loan Data -->
        <input type="number" step="0.01" value="0.00" class="form-control" name="total_paid" hidden><br>
        <input type="number" value="0.00" step="0.01" class="form-control" name="open_loans_balance" hidden><br>
        
        Working Status: <input type="text" name="status" class="form-control"><br>

       
        <input type="submit" value="Add Borrower" class="btn btn-primary">
    </form>
    </section><!-- End Admin Dashboard Section -->
    </main><!-- End Main -->

    <!-- Vendor JS Files -->
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/vendor/aos/aos.js"></script>
    <script src="assets/vendor/glightbox/js/glightbox.min.js"></script>
    <script src="assets/vendor/swiper/swiper-bundle.min.js"></script>

    <!-- Main JS File -->
    <script src="assets/js/main.js"></script>
</body>
</html>
