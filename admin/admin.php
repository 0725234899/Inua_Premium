<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Admin Dashboard - Microfinance</title>
    <meta content="" name="description">
    <meta content="" name="keywords">

    <!-- CSS Variables -->
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

        .grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
        }

        .grid-container ul {
            list-style: none;
            padding: 0;
            margin: 0;
            border: 1px solid var(--default-color);
            border-radius: 8px;
            padding: 20px;
        }

        .grid-container ul li {
            margin: 10px 0;
        }

        .grid-container ul li a {
            color: blue;
            text-decoration: none;
        }

        .grid-container ul li a:hover {
            color: var(--nav-hover-color);
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
<body>
<?php
include '../includes/functions.php';
include 'includes/header.php';
include '../includes/sidebar.php';
?>
<main class="main">
    <section id="admin-dashboard" class="admin-dashboard section">
        <div class="container">
            <div class="grid-container">
                <ul>
                    <h3>Settings</h3>
                    <li><a href="account_settings.php">Account Settings</a></li>
                    <li><a href="custom_fields.php">Custom Fields</a></li>
                    <li><a href="api_settings.php">API Settings</a></li>
                    <li><a href="webform_builder.php">Webform builder and API</a></li>
                </ul>
                <ul>
                    <h3>Manage Staff</h3>
                    <li><a href="staff.php">Staff</a></li>
                    <li><a href="staff_role_permission.php">Staff role and permission</a></li>
                    <li><a href="staff-email-notifications.php">Staff email notifications</a></li>
                    <li><a href="audit_management.php">Audit management</a></li>
                </ul>
                <ul>
                    <h3>Loan</h3>
                    <li><a href="loan_products.php">Loan products</a></li>
                    <li><a href="loan_disbursed_by.php">Loan disbursed by</a></li>
                    <li><a href="loan_penalty_settings.php">Loan penalty settings</a></li>
                    <li><a href="loan_fees.php">Loan fees</a></li>
                    <li><a href="loan_repayment_cycle.php">Loan repayment cycle</a></li>
                    <li><a href="loan_reminder_settings.php">Loan reminder settings</a></li>
                    <li><a href="loan_templates.php">Loan templates: applications/agreements</a></li>
                    <li><a href="bulk_update_loan_schedules.php">Bulk update loan schedules</a></li>
                    <li><a href="bulk_update_loans_automated_payments.php">Bulk update loans with automated payments</a></li>
                    <li><a href="bulk_loans_extend_after_maturity.php">Bulk Loans with extend after maturity</a></li>
                    <li><a href="manage_loan_status_approval.php">Manage loan status and approval</a></li>
                    <li><a href="adjust_interest_rates.php">Adjust Interest rates and in middle</a></li>
                    <li><a href="send_otp_before_disbursement.php">Send OTP to borrowers before disbursement</a></li>
                </ul>
                <ul>
                    <h3>Manage Branch</h3>
                    <li><a href="branches.php">Branches</a></li>
                    <li><a href="branch_holidays.php">Branch holidays</a></li>
                </ul>
                <ul>
                    <h3>Borrowers</h3>
                    <li><a href="download_statement_schedule.php">Download statement/schedule</a></li>
                    <li><a href="format_borrowers_reports.php">Format Borrowers reports</a></li>
                    <li><a href="rename_borrowers_reports.php">Rename Borrowers reports</a></li>
                    <li><a href="manage_credit_officers.php">Manage Credit officers</a></li>
                    <li><a href="invite_borrowers_settings.php">Invite Borrowers Settings</a></li>
                    <li><a href="bulk_update_borrowers_loan_officers.php">Bulk Update Borrowers with loan officers</a></li>
                    <li><a href="bulk_move_borrowers_another_branch.php">Bulk move Borrowers to another Branch</a></li>
                    <li><a href="rename_borrowers_collection_sheets.php">Rename Borrowers Collection Sheets headings</a></li>
                </ul>
                <ul>
                    <h3>Repayments</h3>
                    <li><a href="loans_repayment_methods.php">Loans repayment methods</a></li>
                    <li><a href="manage_collectors.php">Manage Collectors</a></li>
                </ul>
                <ul>
                    <h3>Collateral</h3>
                    <li><a href="collateral_types.php">Collateral Types</a></li>
                </ul>
                <ul>
                    <h3>Payroll</h3>
                    <li><a href="payroll_templates.php">Payroll templates</a></li>
                </ul>
                <ul>
                    <h3>Bulk Upload</h3>
                    <li><a href="upload_borrowers_csv.php">Upload Borrowers From csv file</a></li>
                    <li><a href="upload_loans_csv.php">Upload Loans From csv file</a></li>
                    <li><a href="upload_repayments_csv.php">Upload repayments from csv file</a></li>
                    <li><a href="upload_expenses_csv.php">Upload Expenses from csv file</a></li>
                    <li><a href="upload_other_income_csv.php">Upload other income from csv file</a></li>
                    <li><a href="upload_savings_account_csv.php">Upload savings account from csv file</a></li>
                    <li><a href="upload_savings_transactions_csv.php">Upload savings transactions from csv file</a></li>
                    <li><a href="upload_loans_schedule_csv.php">Upload Loans Schedule From csv file</a></li>
                    <li><a href="upload_interbank_transfer_csv.php">Upload Inter-Bank Transfer from csv file</a></li>
                </ul>
                <ul>
                    <h3>Other Income</h3>
                    <li><a href="other_income_types.php">Other income types</a></li>
                </ul>
                <ul>
                    <h3>Expenses</h3>
                    <li><a href="expense_types.php">Expense Types</a></li>
                </ul>
                <ul>
                    <h3>Asset Management</h3>
                    <li><a href="asset_management_types.php">Asset Management Types</a></li>
                </ul>
                <ul>
                    <h3>SMS Settings</h3>
                    <li><a href="sms_credits.php">SMS credits</a></li>
                    <li><a href="sender_id.php">Sender Id</a></li>
                    <li><a href="sms_templates.php">SMS Templates</a></li>
                    <li><a href="auto_send_sms.php">Auto Send SMS</a></li>
                    <li><a href="collection_sheets_sms_templates.php">Collection Sheets SMS Templates</a></li>
                    <li><a href="sms_logs.php">SMS Logs</a></li>
                </ul>
                <ul>
                    <h3>Email Settings</h3>
                    <li><a href="email_accounts.php">Email Accounts</a></li>
                    <li><a href="email_templates.php">Email Templates</a></li>
                    <li><a href="auto_send_emails.php">Auto Send Emails</a></li>
                    <li><a href="collection_sheets_email_templates.php">Collection Sheets Email Templates</a></li>
                    <li><a href="email_logs.php">Email Logs</a></li>
                </ul>
                <ul>
                    <h3>Savings</h3>
                    <li><a href="savings_products.php">Savings Products</a></li>
                    <li><a href="savings_fees.php">Savings fee</a></li>
                    <li><a href="savings_transactions.php">Savings Transactions</a></li>
                </ul>
            </div>
        </div>
    </section>
</main>
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/vendor/aos/aos.js"></script>
    <script src="assets/vendor/glightbox/js/glightbox.min.js"></script>
    <script src="assets/vendor/swiper/swiper-bundle.min.js"></script>

    <!-- Main JS File -->
    <script src="assets/js/main.js"></script>
</body>
</html>
