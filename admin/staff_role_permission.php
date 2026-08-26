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
    include 'includes/header.php';

    $navigationConnection = db_connect();
    $advanceParentStatement = $navigationConnection->prepare('SELECT id FROM navigation_items WHERE title = ? AND parent_id IS NULL LIMIT 1');
    $advanceParentTitle = 'Advance';
    $advanceParentStatement->execute([$advanceParentTitle]);
    $advanceParentId = $advanceParentStatement->fetchColumn();
    $advanceParentStatement->closeCursor();

    if (!$advanceParentId) {
        $insertParentStatement = $navigationConnection->prepare('INSERT INTO navigation_items (title, url, icon, parent_id) VALUES (?, ?, ?, NULL)');
        $insertParentStatement->execute(['Advance', '#', 'bi bi-wallet2']);
        $advanceParentId = $navigationConnection->lastInsertId();
    }

    $advanceChildren = [
        ['Add Advance', '../manager/add_advance.php', 'bi bi-plus-circle'],
        ['View Advance', '../manager/view_Advance.php', 'bi bi-eye'],
        ['Advance Report', '../manager/advance_report.php', 'bi bi-file-earmark-text']
    ];
    $childStatement = $navigationConnection->prepare('SELECT id FROM navigation_items WHERE title = ? AND parent_id = ? LIMIT 1');
    $existingChildStatement = $navigationConnection->prepare('SELECT id FROM navigation_items WHERE title = ? LIMIT 1');
    $updateChildStatement = $navigationConnection->prepare('UPDATE navigation_items SET url = ?, parent_id = ?, icon = ? WHERE id = ?');
    $insertChildStatement = $navigationConnection->prepare('INSERT INTO navigation_items (title, url, icon, parent_id) VALUES (?, ?, ?, ?)');
    $navigationItemIds = [$advanceParentId];

    foreach ($advanceChildren as $advanceChild) {
        $childStatement->execute([$advanceChild[0], $advanceParentId]);
        $childId = $childStatement->fetchColumn();

        if (!$childId) {
            $existingChildStatement->execute([$advanceChild[0]]);
            $childId = $existingChildStatement->fetchColumn();

            if (!$childId) {
                $insertChildStatement->execute([$advanceChild[0], $advanceChild[1], $advanceChild[2], $advanceParentId]);
                $childId = $navigationConnection->lastInsertId();
            }
        }

        $updateChildStatement->execute([$advanceChild[1], $advanceParentId, $advanceChild[2], $childId]);

        $navigationItemIds[] = $childId;
    }

    $roleMappingStatement = $navigationConnection->prepare('INSERT IGNORE INTO navigation_item_roles (navigation_item_id, role_id) VALUES (?, ?)');
    foreach ($navigationItemIds as $navigationItemId) {
        foreach ([1, 4] as $advanceRoleId) {
            $roleMappingStatement->execute([$navigationItemId, $advanceRoleId]);
        }
    }

    $navigationConnection = null;
    ?>
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
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/vendor/aos/aos.js"></script>
    <script src="assets/vendor/glightbox/js/glightbox.min.js"></script>
    <script src="assets/vendor/swiper/swiper-bundle.min.js"></script>

    <!-- Main JS File -->
    <script src="assets/js/main.js"></script>
</body>
</html>
