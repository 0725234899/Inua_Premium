<?php
session_start();
require_once '../includes/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Savings Template</title>
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .main { margin-left: 270px; padding: 24px; }
        .card { border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
    </style>
</head>
<body>
<?php include 'includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<main class="main">
    <div class="container-fluid">
        <div class="card p-4">
            <h2 class="mb-3">Savings Template</h2>
            <p class="text-muted">Use this template to register clients who want to save with the institution.</p>
            <div class="alert alert-info">You can now add savings clients from the new savings form linked from the dashboard.</div>
            <a href="add_savings_client.php" class="btn btn-primary">Open savings client form</a>
        </div>
    </div>
</main>
</body>
</html>
