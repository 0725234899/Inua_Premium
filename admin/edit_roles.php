<?php  session_start(); ?>
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
    include 'db.php'; 
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        

        if (isset($_POST['add_permission'])) {
            $new_role_id = $_POST['new_role_id'];
            $new_nav_item_id = $_POST['new_nav_item_id'];

            $sql = "INSERT INTO navigation_item_roles (navigation_item_id, role_id) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ii', $new_nav_item_id, $new_role_id);

            if ($stmt->execute()) {
                echo "Permission added successfully.";
                ?>
                <script>
                location.replace("staff_role_permission.php");
                </script>
                <?php
            } else {
                echo "Error adding permission: " . $conn->error;
            }
            $stmt->close();
        }

       // $conn->close();
    

        if (isset($_POST['update'])) {
            $permissions = isset($_POST['permissions']) ? $_POST['permissions'] : [];
            $permissions_str = implode(',', $permissions);

            $sql = "UPDATE roles SET permissions = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('si', $permissions_str, $role_id);

            if ($stmt->execute()) {
                echo "Role permissions updated successfully.";
                header(location:"staff_role_permission.php");
            } else {
                echo "Error updating role permissions: " . $conn->error;
            }
            $stmt->close();
        }

        if (isset($_POST['delete_role'])) {
            $sql = "DELETE FROM roles WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $role_id);

            if ($stmt->execute()) {
               
                ?>
                <script>
                     alert("Role deleted successfully.");
                location.replace("staff_role_permission.php");
                </script>
                <?php
            } else {
                echo "Error deleting role: " . $conn->error;
                location.replace("staff_role_permission.php");
                 echo "Role deleted successfully.";
            }
            //$stmt->close();
        }

        if (isset($_POST['delete_permission'])) {
            $permission_id = $_POST['permission_id'];

            $sql = "DELETE FROM navigation_item_roles where navigation_item_id= ? AND role_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ii', $permission_id,$_SESSION['role']);

            if ($stmt->execute()) {
                echo "Permission deleted successfully.";
                location.replace("staff_role_permission.php");
            } else {
                echo "Error deleting permission: " . $conn->error;
                ?>
                <script>
                    
                     alert("Role deleted failed.");
                location.replace("staff_role_permission.php");
                </script>
                <?php
            }
            $stmt->close();
        }

        //$conn->close();
    }

    // Fetch roles and permissions for display
    $allroles = getRoles();
    $allpermissions = getAllNavigationItems();
    // Check if 'role' is set in POST, and handle properly
if (isset($_POST['role_id']) && !empty($_POST['role_id'])) {
    $role_id = $_POST['role_id']; // Use 'role' from the form submission
    //echo "<script>alert('this ".$role_id."');</script>"; // Optional: For debugging purposes

    // Fetch the role using the role_id
    $role = getRole($role_id);

    // Fetch permissions related to the role
    $permissions = getNavigationItems($role_id);
} else {
    // Handle case where role is not provided
    echo "<script>alert('Role ID is not provided or is invalid.');</script>";
}
    ?>
    <div class="sidebar">
        <?php include '../includes/sidebar.php'; ?>
    </div>
    <main class="main">
        <section class="section">
            <div class="container">
                <h1>Staff Roles and Permissions</h1>
                <form action='edit_roles.php' method='POST'>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Staff Role Name</th>
                                <th>Permissions</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            
                            <tr>
                                <td><?php echo getRole($_SESSION['role']); ?></td>
                                <td>
                                    <ul>
                                        <?php
                                        //$role_permissions = explode(',', $role['permissions']);
                                        foreach ($permissions as $permission) {
                                            
                                            echo "<li>
                                                    <input type='checkbox' name='permissions[]' value='".htmlspecialchars($permission['id'])."' aria-label='".htmlspecialchars($permission['title'])."'> 
                                                    ".htmlspecialchars($permission['title'])."
                                                    <form action='edit_roles.php' method='POST' style='display:inline;'>
                                                        <input type='hidden' name='permission_id' value='".htmlspecialchars($permission['id'])."'>
                                                       
                                                        <button type='submit' name='delete_permission' class='btn btn-danger btn-sm'>Delete</button>
                                                    </form>
                                                  </li>";
                                        }
                                        ?>
                                    </ul>
                                </td>
                                <td>
                                    <input type="hidden" name="role_id" value="<?php echo htmlspecialchars($role['id']); ?>">
                                    <button type="submit" name="update" class="btn btn-primary">Update</button>
                                    <button type="submit" name="delete_role" class="btn btn-danger">Delete</button>
                                </td>
                            </tr>
                           
                        </tbody>
                    </table>
               </form>
               <h2>Add New Permission</h2>
                <form action="edit_roles.php" method="POST">
                    <div class="form-group">
                        <label for="new_role_id">Role:</label>
                        <select id="new_role_id" name="new_role_id" class="form-control" required>
                            
                                <option value="1">Admin</option>
                                <option value="2">Loan officer</option>
                                <option value="3">Client</option>
                                <option value="4">Manager</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="new_nav_item_id">Navigation Item:</label>
                        <select id="new_nav_item_id" name="new_nav_item_id" class="form-control" required>
                            <?php foreach ($allpermissions as $permission): ?>
                                <option value="<?php echo htmlspecialchars($permission['id']); ?>"><?php echo htmlspecialchars($permission['title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="add_permission" class="btn btn-primary">Add Permission</button>
                </form>
            </div>
        </section>
    </main>
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
