<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Email Notifications - Admin Dashboard</title>
    <link href="/assets/img/logo.png" rel="icon">
    <link href="/assets/img/logo.png" rel="apple-touch-icon">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans&family=Montserrat&family=Poppins&display=swap" rel="stylesheet">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
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
        .container h1 {
            font-size: 28px;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .form-group button {
            background-color: #e84545;
            color: #ffffff;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
        }
        .form-group button:hover {
            background-color: #d73434;
        }
        .section {
            padding: 20px 0;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .table th, .table td {
            border: 1px solid #ddd;
            padding: 8px;
        }
        .table th {
            background-color: #f2f2f2;
            text-align: left;
        }
        .table td .btn {
            background-color: #e84545;
            color: #ffffff;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
        }
        .table td .btn:hover {
            background-color: #d73434;
        }
        .container .notification-section {
            margin-bottom: 20px;
        }
        .container .notification-section h2 {
            font-size: 24px;
            margin-bottom: 10px;
        }
        .container .notification-section p {
            margin-bottom: 10px;
        }
        .container .notification-section ul {
            list-style: none;
            padding: 0;
            margin-bottom: 10px;
        }
        .container .notification-section ul li {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <?php
    include '../includes/functions.php';
    include 'includes/header.php'; ?>
    <nav class="sidebar">
        <?php include '../includes/sidebar.php'; ?>
    </nav>
    <main class="main">
        <section class="section">
            <div class="container">
                <h1>Staff Email Notifications</h1>
                <div class="notification-section">
                    <h2>Daily Reports:</h2>
                    <p>The owner of the account will receive reports for all branches. Other staff will only receive reports for the branches that have been assigned to them.</p>
                    <p>Receive Daily Reports on:</p>
                    <ul>
                        <li><input type="checkbox" id="loans-due"><label for="loans-due"> Loans Due Today</label></li>
                        <li><input type="checkbox" id="loans-expiring"><label for="loans-expiring"> Loans Expiring Today</label></li>
                        <li><input type="checkbox" id="loans-past-maturity"><label for="loans-past-maturity"> Loans Past Maturity Date</label></li>
                        <li><input type="checkbox" id="new-loans"><label for="new-loans"> New Loans Added</label></li>
                        <li><input type="checkbox" id="new-repayments"><label for="new-repayments"> New Repayments Added</label></li>
                    </ul>
                    <p>Select Staff who will receive Daily Reports:</p>
                    <ul>
                        <li><input type="checkbox" id="staff-antonio"><label for="staff-antonio"> antonio cheruiyot</label></li>
                        <li><input type="checkbox" id="staff-vincent"><label for="staff-vincent"> vincent kipkurui</label></li>
                    </ul>
                </div>

                <div class="notification-section">
                    <h2>Weekly Reports:</h2>
                    <p>Select Staff who will receive Weekly Reports:</p>
                    <ul>
                        <li><input type="checkbox" id="staff-antonio-weekly"><label for="staff-antonio-weekly"> antonio cheruiyot</label></li>
                        <li><input type="checkbox" id="staff-vincent-weekly"><label for="staff-vincent-weekly"> vincent kipkurui</label></li>
                    </ul>
                </div>

                <div class="notification-section">
                    <h2>Approve Repayments:</h2>
                    <p>If you have restricted staff so they can only add payments for approval, you can check the staff below who should receive a notification when a repayment is added for approval. The email will be sent once per hour with all the new repayments pending for approval. The email will only be sent for the branches that have been assigned to the staff member.</p>
                    <p>To restrict staff, you can visit Admin(top menu) → Manage Staff → Staff → Edit. There, check Restrict Add/Edit Repayments for Approval.</p>
                    <p>Select Staff who will receive notifications for pending repayments:</p>
                    <ul>
                        <li><input type="checkbox" id="staff-antonio-repayments"><label for="staff-antonio-repayments"> antonio cheruiyot</label></li>
                        <li><input type="checkbox" id="staff-vincent-repayments"><label for="staff-vincent-repayments"> vincent kipkurui</label></li>
                    </ul>
                </div>

                <div class="notification-section">
                    <h2>Approve Savings Transactions:</h2>
                    <p>If you have restricted staff so they can only add savings transactions for approval, you can check the staff below who should receive a notification when a savings transaction is added for approval. The email will be sent once per hour with all the new savings transactions pending for approval. The email will only be sent for the branches that have been assigned to the staff member.</p>
                    <p>To restrict staff, you can visit Admin(top menu) → Manage Staff → Staff → Edit. There, check Restrict Add/Edit Savings Transactions for Approval.</p>
                    <p>Select Staff who will receive notifications for pending savings transactions:</p>
                    <ul>
                        <li><input type="checkbox" id="staff-antonio-savings"><label for="staff-antonio-savings"> antonio cheruiyot</label></li>
                        <li><input type="checkbox" id="staff-vincent-savings"><label for="staff-vincent-savings"> vincent kipkurui</label></li>
                    </ul>
                </div>

                <div class="notification-section">
                    <h2>Loan Reminder Notifications:</h2>
                    <p>To setup staff email reminders for your loans, please visit Admin(top menu) → Loans → Loan Reminder Settings.</p>
                </div>

                <div class="notification-section">
                    <h2>Loan Approvals:</h2>
                    <p>To setup staff email reminders when your loan moves from one status to another, please visit Admin(top menu) → Loans → Manage Loan Status and Approvals.</p>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
