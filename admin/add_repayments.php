<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Repayments</title>
    <link href="/assets/img/logo.png" rel="icon">
    <link href="/assets/img/logo.png" rel="apple-touch-icon">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans&family=Montserrat&family=Poppins&display=swap" rel="stylesheet">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
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
            margin-bottom: 20px;
        }
        .form-group label {
            font-weight: bold;
        }
        .loader {
            border: 8px solid #f3f3f3;
            border-radius: 50%;
            border-top: 8px solid #3498db;
            width: 40px;
            height: 40px;
            animation: spin 2s linear infinite;
            display: none;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <?php 
    include '../includes/functions.php';
    include 'includes/header.php'; 
    ?>
    <div class="sidebar">
        <?php include '../includes/sidebar.php'; ?>
    </div>
    <main class="main">
        <section class="section">
            <div class="container">
                <h1>Add Repayments</h1>
                <div class="row">
                    <div class="col-md-6">
                        <h2>Single or Multiple Repayments</h2>
                        <form id="repaymentForm">
                            <div id="repaymentContainer">
                                <div class="repayment-entry">
                                    <div class="form-group">
                                        <label for="borrower">Borrower</label>
                                        <input type="text" class="form-control" name="borrower[]" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="loan_product">Loan Product</label>
                                        <input type="text" class="form-control" name="loan_product[]" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="amount">Amount</label>
                                        <input type="number" class="form-control" name="amount[]" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="repayment_date">Repayment Date</label>
                                        <input type="date" class="form-control" name="repayment_date[]" required>
                                    </div>
                                </div>
                            </div>
                            <button type="button" id="addRepayment" class="btn btn-secondary">Add Another Repayment</button>
                            <button type="submit" class="btn btn-primary">Submit Repayments</button>
                        </form>
                        <div class="loader" id="formLoader"></div>
                    </div>
                    <div class="col-md-6">
                        <h2>Bulk Repayments</h2>
                        <form id="bulkRepaymentForm" enctype="multipart/form-data">
                            <div class="form-group">
                                <label for="bulk_file">Upload CSV File</label>
                                <input type="file" class="form-control" id="bulk_file" name="bulk_file" accept=".csv" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Upload</button>
                        </form>
                        <div class="loader" id="bulkFormLoader"></div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('addRepayment').addEventListener('click', function() {
            const container = document.getElementById('repaymentContainer');
            const newEntry = document.createElement('div');
            newEntry.classList.add('repayment-entry');
            newEntry.innerHTML = `
                <div class="form-group">
                    <label for="borrower">Borrower</label>
                    <input type="text" class="form-control" name="borrower[]" required>
                </div>
                <div class="form-group">
                    <label for="loan_product">Loan Product</label>
                    <input type="text" class="form-control" name="loan_product[]" required>
                </div>
                <div class="form-group">
                    <label for="amount">Amount</label>
                    <input type="number" class="form-control" name="amount[]" required>
                </div>
                <div class="form-group">
                    <label for="repayment_date">Repayment Date</label>
                    <input type="date" class="form-control" name="repayment_date[]" required>
                </div>
            `;
            container.appendChild(newEntry);
        });

        document.getElementById('repaymentForm').addEventListener('submit', function(e) {
            e.preventDefault();
            document.getElementById('formLoader').style.display = 'block';
            const formData = new FormData(this);
            fetch('process_multiple_repayments.php', {
                method: 'POST',
                body: formData
            }).then(response => response.text())
            .then(data => {
                alert(data);
                location.reload();
            }).catch(error => console.error('Error:', error))
            .finally(() => {
                document.getElementById('formLoader').style.display = 'none';
            });
        });

        document.getElementById('bulkRepaymentForm').addEventListener('submit', function(e) {
            e.preventDefault();
            document.getElementById('bulkFormLoader').style.display = 'block';
            const formData = new FormData(this);
            fetch('process_bulk_repayments.php', {
                method: 'POST',
                body: formData
            }).then(response => response.text())
            .then(data => {
                alert(data);
                location.reload();
            }).catch(error => console.error('Error:', error))
            .finally(() => {
                document.getElementById('bulkFormLoader').style.display = 'none';
            });
        });
    </script>
</body>
</html>
