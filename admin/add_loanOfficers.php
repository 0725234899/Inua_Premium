<?php
include_once("../includes/functions.php");

// Get the list of loan officers with role_id = 2, areas, and roles
$loanOfficers = getLoanOfficers(); // Filter by role_id = 2
$areas = getAreas(); // Assuming getAreas function is defined similarly
$roles = getRoles();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<link href="/assets/img/logo.png" rel="icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" integrity="sha512-jnSuA4Ss2PkkikSOLtYs8BlYIeeIK1h99ty4YfvRPAlzr377vr3CXDb7sb7eEEBYjDtcYj+AjBH3FLv5uSJuXg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="/assets/img/logo.png" rel="apple-touch-icon">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans&family=Montserrat&family=Poppins&display=swap" rel="stylesheet">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.min.js" integrity="sha512-ykZ1QQr0Jy/4ZkvKuqWn4iF3lqPZyij9iRv6sGqLRdTPkY69YX6+7wvVGmsdBbiIfN/8OdsI7HABjvEok6ZopQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <!-- Main JS File -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.js" integrity="sha512-+k1pnlgt4F1H8L7t3z95o3/KO+o78INEcXTbnoJQ/F2VqDVhWoaiVml/OEHv9HsVgxUaVW+IbiZPUJQfF/YxZw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<style>
        /* Additional or overridden styles specific to this page */
        body {
            background-color: #ffffff;
            color: #212529;
            font-family: 'Open Sans', sans-serif;
            margin: 0;
        }
        .header {
            background-color: #e84545;
            color: #ffffff;
            padding: 10px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header .logo h1 {
            color: #ffffff;
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
            color: #ffffff;
            text-decoration: none;
        }
        .header .navmenu ul li a.active, .header .navmenu ul li a:hover {
            color: #e84545;
        }
        .sidebar {
            background-color: #ffffff;
            color: #3a3939;
            padding: 20px;
            width: 250px;
            position: fixed;
            height: 100%;
            overflow: auto;
        }
        .sidebar .nav-item .nav-link {
            color: #3a3939;
            padding: 10px 15px;
            text-decoration: none;
            display: block;
        }
        .sidebar .nav-item .nav-link.active, .sidebar .nav-item .nav-link:hover {
            color: #e84545;
        }
        .main {
            margin-left: 270px;
            padding: 20px;
        }
        .table-container {
            overflow-x: auto;
        }
    </style>
</head>
<body class="admin-page">
    <!-- Header -->
    <?php
    include("includes/header.php");
    include("../includes/sidebar.php");
    ?>
    <main class="main">
        <section id="loan-officers" class="loan-officers section">
            <div class="container">
                <h1>Staff</h1>
                <button class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#addLoanOfficerModal">Add Staff</button>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Role</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($loanOfficers)): ?>
                                <?php foreach ($loanOfficers as $officer): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($officer['id']); ?></td>
                                        <td><?php echo htmlspecialchars($officer['name']); ?></td>
                                        <td><?php echo htmlspecialchars($officer['email']); ?></td>
                                        <td><?php echo htmlspecialchars($officer['phone']); ?></td>
                                        <td><?php echo getRole($officer['role_id'])['name'];?></td>
                                        <td>
                                            <a href="edit_loan_officer.php?id=<?php echo htmlspecialchars($officer['id']); ?>" class="btn btn-primary">Edit</a>
                                            <a href="delete_loan_officer.php?id=<?php echo htmlspecialchars($officer['id']); ?>" class="btn btn-danger">Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6">No loan officers found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
        
        <!-- Add Loan Officer Modal -->
        <!-- Modal code as before -->
    </main>
    <!-- Vendor JS Files -->
    <!-- JS files as before -->
</body>
</html>
