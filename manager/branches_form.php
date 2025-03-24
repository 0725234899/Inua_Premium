<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Branches</title>
    <link rel="stylesheet" href="assets/css/styles.css"> <!-- Link to your CSS file -->
    <style>
        /* Include the existing styles and add new styles for the form */
        .form-container {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            margin-top: 20px;
        }
        .form-container h2 {
            margin-top: 0;
        }
        .form-container .form-group {
            margin-bottom: 15px;
        }
        .form-container label {
            display: block;
            margin-bottom: 5px;
        }
        .form-container input, .form-container select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .form-container button {
            background-color: #e84545;
            border: none;
            color: #fff;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
        }
        .form-container button:hover {
            background-color: #d73434;
        }
 
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f4f4f4;
        }
        h1 {
            color: #333;
        }
        a {
            text-decoration: none;
            color: #007bff;
        }
        a:hover {
            text-decoration: underline;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background-color: #fff;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color:  #e84545;
            color: #fff;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
    </style>
</head>
<body>
    <h1>Branches</h1>
    <a href="index.php">Back to Admin</a>

    <div class="form-container">
        <h2>Add New Branch</h2>
        <form action="add_branch.php" method="POST">
            <div class="form-group">
                <label for="name">Branch Name</label>
                <input type="text" id="name" name="name" required>
            </div>
            <div class="form-group">
                <label for="phone">Phone</label>
                <input type="text" id="phone" name="phone" required>
            </div>
            <div class="form-group">
                <label for="min_loan_amount">Min Loan Amount</label>
                <input type="number" id="min_loan_amount" name="min_loan_amount" step="0.01" required>
            </div>
            <div class="form-group">
                <label for="max_loan_amount">Max Loan Amount</label>
                <input type="number" id="max_loan_amount" name="max_loan_amount" step="0.01" required>
            </div>
            <div class="form-group">
                <label for="min_interest_rate">Min Interest Rate (%)</label>
                <input type="number" id="min_interest_rate" name="min_interest_rate" step="0.01" required>
            </div>
            <div class="form-group">
                <label for="max_interest_rate">Max Interest Rate (%)</label>
                <input type="number" id="max_interest_rate" name="max_interest_rate" step="0.01" required>
            </div>
            <div class="form-group">
                <label for="branch_capital">Branch Capital (KSh)</label>
                <input type="number" id="branch_capital" name="branch_capital" step="0.01" required>
            </div>
            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status" required>
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </div>
            <button type="submit">Add Branch</button>
        </form>
    </div>

    <!-- Existing table and other code -->
</body>
</html>
