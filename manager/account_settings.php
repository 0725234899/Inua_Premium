<?php
include '../includes/functions.php';
$settings = getSettings();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings - Admin Dashboard</title>
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

        .table-container {
            overflow-x: auto;
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

        .form-group small {
            display: block;
            margin-top: 5px;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="sidebar">
        <?php include '../includes/sidebar.php'; ?>
    </div>
    <main class="main">
        <section class="section">
            <div class="container">
                <h1>Account Settings</h1>
                <form action="update_account_settings.php" method="post" enctype="multipart/form-data">
                    <h3>Company Settings</h3>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label for="company_name">Company Name</label>
                            <input type="text" id="company_name" name="company_name" value="<?php echo htmlspecialchars($settings['company_name']); ?>">
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="country">Country</label>
                            <input type="text" id="country" name="country" value="<?php echo htmlspecialchars($settings['country']); ?>">
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="timezone">Timezone</label>
                            <input type="text" id="timezone" name="timezone" value="<?php echo htmlspecialchars($settings['timezone']); ?>">
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="currency">Currency</label>
                            <input type="text" id="currency" name="currency" value="<?php echo htmlspecialchars($settings['currency']); ?>">
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="currency_words">Currency in Words</label>
                            <input type="text" id="currency_words" name="currency_words" value="<?php echo htmlspecialchars($settings['currency_words']); ?>">
                    
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="date_format">Date Format</label>
                            <input type="text" id="date_format" name="date_format" value="<?php echo htmlspecialchars($settings['date_format']); ?>">
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="decimal_separator">Decimal Separator</label>
                            <input type="text" id="decimal_separator" name="decimal_separator" value="<?php echo htmlspecialchars($settings['decimal_separator']); ?>">
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="thousand_separator">Thousand Separator</label>
                            <input type="text" id="thousand_separator" name="thousand_separator" value="<?php echo htmlspecialchars($settings['thousand_separator']); ?>">
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="results_per_page">Show Results Per Page</label>
                            <input type="number" id="results_per_page" name="results_per_page" value="<?php echo htmlspecialchars($settings['results_per_page']); ?>">
                        </div>
                    </div>
                    
                    <h3>Loan Settings</h3>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label for="monthly_repayment_cycle">Monthly Loans Repayment Cycle</label>
                            <input type="text" id="monthly_repayment_cycle" name="monthly_repayment_cycle" value="<?php echo htmlspecialchars($settings['monthly_repayment_cycle']); ?>">
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="yearly_repayment_cycle">Yearly Loans Repayment Cycle</label>
                            <input type="text" id="yearly_repayment_cycle" name="yearly_repayment_cycle" value="<?php echo htmlspecialchars($settings['yearly_repayment_cycle']); ?>">
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="days_in_month">Days In A Month</label>
                            <input type="number" id="days_in_month" name="days_in_month" value="<?php echo htmlspecialchars($settings['days_in_month']); ?>">
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="days_in_year">Days In A Year</label>
                            <input type="number" id="days_in_year" name="days_in_year" value="<?php echo htmlspecialchars($settings['days_in_year']); ?>">
                        </div>
                    </div>
                    
                    <h3>Business Information</h3>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label for="business_registration_number">Business Registration Number</label>
                            <input type="text" id="business_registration_number" name="business_registration_number" value="<?php echo htmlspecialchars($settings['business_registration_number']); ?>">
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="address">Address</label>
                            <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($settings['address']); ?>">
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="city">City</label>
                            <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($settings['city']); ?>">
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="province">Province</label>
                            <input type="text" id="province" name="province" value="<?php echo htmlspecialchars($settings['province']); ?>">
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="zipcode">Zip Code</label>
                            <input type="text" id="zipcode" name="zipcode" value="<?php echo htmlspecialchars($settings['zipcode']); ?>">
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="company_logo">Company Logo</label>
                            <input type="file" id="company_logo" name="company_logo">
                            <?php if (!empty($settings['logo'])): ?>
                                <small>Current logo:</small><br>
                                <img src="../assets/img/<?php echo htmlspecialchars($settings['logo']); ?>" alt="Logo" style="width: 150px;">
                            <?php endif; ?>
                            <label><input type="checkbox" name="delete_logo" value="on"> Delete current logo</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <button type="submit">Save Changes</button>
                    </div>
                </form>
            </div>
        </section>
    </main>

    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/vendor/aos/aos.js"></script>
    <script src="assets/vendor/glightbox/js/glightbox.min.js"></script>
    <script src="assets/vendor/swiper/swiper-bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
