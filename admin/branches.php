<?php
// Include the database connection
include '../includes/functions.php'; // Ensure this file contains the db_connect function

// Fetch branches
$branches = getBranches();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Branches - Inua Premium Services</title>
    <link href="/assets/img/logo.png" rel="icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" integrity="sha512-jnSuA4Ss2PkkikSOLtYs8BlYIeeIK1h99ty4YfvRPAlzr377vr3CXDb7sb7eEEBYjDtcYj+AjBH3FLv5uSJuXg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="/assets/img/logo.png" rel="apple-touch-icon">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans&family=Montserrat&family=Poppins&display=swap" rel="stylesheet">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.min.js" integrity="sha512-ykZ1QQr0Jy/4ZkvKuqWn4iF3lqPZyij9iRv6sGqLRdTPkY69YX6+7wvVGmsdBbiIfN/8OdsI7HABjvEok6ZopQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <!-- Main JS File -->
    <link href="assets/css/styles.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Open Sans', sans-serif;
            margin: 0;
            background-color: #f4f4f4;
        }
        .header {
            background-color: #e84545;
            color: #ffffff;
            padding: 10px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
        }
        .header .logo h1 {
            color: #ffffff;
            margin: 0;
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
        .modal-content {
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <?php include("includes/header.php"); ?>
    
    <!-- Sidebar -->
        <div class="nav">
            <a href="branches.php" class="nav-link">Branches</a>
            <?php include '../includes/sidebar.php'; ?>
            <!-- Add more sidebar links as needed -->
        </div>
    </div>
    
    <main class="main">
        <div class="container">
            <h1>Branches</h1>
            <button class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#addBranchModal">Add Branch</button>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Loan Conditions</th>
                            <th>Branch Capital (KSh)</th>
                            <th>Created</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($branches)): ?>
                            <?php foreach ($branches as $branch): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($branch['name']); ?></td>
                                    <td><?php echo htmlspecialchars($branch['phone']); ?></td>
                                    <td>
                                        Min Loan Amount: <?php echo htmlspecialchars($branch['min_loan_amount']); ?><br>
                                        Max Loan Amount: <?php echo htmlspecialchars($branch['max_loan_amount']); ?><br>
                                        Min Interest Rate: <?php echo htmlspecialchars($branch['min_interest_rate']); ?><br>
                                        Max Interest Rate: <?php echo htmlspecialchars($branch['max_interest_rate']); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars(number_format($branch['branch_capital'], 2)); ?></td>
                                    <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($branch['created']))); ?></td>
                                    <td><?php echo htmlspecialchars($branch['status']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6">No branches found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    
    <!-- Add Branch Modal -->
    <div class="modal fade" id="addBranchModal" tabindex="-1" aria-labelledby="addBranchModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addBranchModalLabel">Add Branch</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="add_branch.php" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">Branch Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="text" class="form-control" id="phone" name="phone" required>
                        </div>
                        <div class="mb-3">
                            <label for="min_loan_amount" class="form-label">Min Loan Amount</label>
                            <input type="number" class="form-control" id="min_loan_amount" name="min_loan_amount" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label for="max_loan_amount" class="form-label">Max Loan Amount</label>
                            <input type="number" class="form-control" id="max_loan_amount" name="max_loan_amount" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label for="min_interest_rate" class="form-label">Min Interest Rate (%)</label>
                            <input type="number" class="form-control" id="min_interest_rate" name="min_interest_rate" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label for="max_interest_rate" class="form-label">Max Interest Rate (%)</label>
                            <input type="number" class="form-control" id="max_interest_rate" name="max_interest_rate" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label for="branch_capital" class="form-label">Branch Capital (KSh)</label>
                            <input type="number" class="form-control" id="branch_capital" name="branch_capital" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" name="addBranch">Add Branch</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js" integrity="sha512-ykZ1QQr0Jy/4ZkvKuqWn4iF3lqPZyij9iRv6sGqLRdTPkY69YX6+7wvVGmsdBbiIfN/8OdsI7HABjvEok6ZopQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
</body>
</html>
