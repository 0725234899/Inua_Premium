<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Roles and Permissions - Admin Dashboard</title>
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
    </style>
</head>
<body>
    <?php 
    include '../includes/functions.php';
    include 'includes/header.php'; ?>
    <div class="sidebar">
        <?php include '../includes/sidebar.php'; ?>
    </div>
    <main class="main">
        <section class="section">
            <div class="container">
                <h1>Staff Roles and Permissions</h1>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Staff Role Name</th>
                            <th>Permissions</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                                        $roles=getRoles();
                                        foreach($roles as $role)
                                        {
                                           ?>
                                              <tr>
                            <td><?php echo $role['name']; ?></td>
                            <td>
                                <ul>
                                    <?php
                                    $permisions=getNavigationItems($role['id']);
                                    foreach($permisions as $permision)
                                    {
                                        echo "<li>".$permision['title']."</li>";
                                    }
                                    ?>
                                 
                                </ul>
                            </td>
                            <td>
                            <form action='edit_roles.php' method='POST'>   
    <input type="hidden" name="role_id" value="<?php echo $role['id'] ?>">
    <button type="submit" class="btn">Edit</button>
</form>
</td>
                        </tr>
                                           <?php
                                        }
                                        ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>
