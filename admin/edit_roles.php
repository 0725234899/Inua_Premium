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
            background: linear-gradient(135deg, #fff7f7 0%, #ffffff 100%);
            color: #2d2d2d;
            font-family: 'Poppins', sans-serif;
            margin: 0;
        }
        .sidebar {
            background-color: #ffffff;
            color: #3a3939;
            padding: 20px;
            width: 250px;
            position: fixed;
            height: 100%;
            overflow: auto;
            border-right: 1px solid #f3e8e8;
            box-shadow: 2px 0 12px rgba(0,0,0,0.03);
        }
        .sidebar .nav-item .nav-link {
            color: #3a3939;
            padding: 10px 15px;
            text-decoration: none;
            display: block;
            border-radius: 10px;
            margin-bottom: 4px;
        }
        .sidebar .nav-item .nav-link.active, .sidebar .nav-item .nav-link:hover {
            color: #e84545;
            background: #fff1f1;
        }
        .main {
            margin-left: 270px;
            padding: 30px 24px 40px;
        }
        .container {
            background: rgba(255,255,255,0.96);
            border-radius: 24px;
            padding: 24px;
            box-shadow: 0 20px 45px rgba(232, 69, 69, 0.09);
            border: 1px solid #f8ecec;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: #e84545;
            margin-bottom: 4px;
        }
        .page-subtitle {
            color: #6c757d;
            margin: 0;
        }
        .header-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #e84545, #ff6b6b);
            color: white;
            padding: 10px 14px;
            border-radius: 999px;
            font-weight: 600;
            box-shadow: 0 8px 20px rgba(232, 69, 69, 0.2);
        }
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: linear-gradient(135deg, #fff8f8, #ffffff);
            border: 1px solid #f5e4e4;
            border-radius: 18px;
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 8px 18px rgba(0,0,0,0.04);
        }
        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            background: #ffe8e8;
            color: #e84545;
            font-size: 1.15rem;
        }
        .stat-card h4 {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 700;
        }
        .stat-card p {
            margin: 2px 0 0;
            color: #6c757d;
            font-size: 0.95rem;
        }
        .card {
            background: #ffffff;
            border: 1px solid #f4eaea;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 10px 24px rgba(0,0,0,0.04);
            margin-bottom: 20px;
        }
        .card-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }
        .card-title h3 {
            margin: 0;
            font-size: 1.15rem;
            color: #e84545;
            font-weight: 700;
        }
        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            background: #fff0f0;
            color: #b63131;
            font-weight: 600;
        }
        .modern-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
            margin-bottom: 0;
        }
        .modern-table thead th {
            background: linear-gradient(135deg, #e84545, #ff6b6b);
            color: #fff;
            border: 0;
            padding: 12px 14px;
        }
        .modern-table tbody td {
            background: #fff;
            border-top: 1px solid #f5e4e4;
            border-bottom: 1px solid #f5e4e4;
            padding: 12px 14px;
            vertical-align: middle;
        }
        .modern-table tbody tr td:first-child {
            border-left: 1px solid #f5e4e4;
            border-radius: 12px 0 0 12px;
        }
        .modern-table tbody tr td:last-child {
            border-right: 1px solid #f5e4e4;
            border-radius: 0 12px 12px 0;
        }
        .permission-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .permission-pill {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            background: #fff8f8;
            border: 1px solid #f3e1e1;
            border-radius: 999px;
            padding: 8px 12px;
        }
        .permission-pill label {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0;
            color: #4b4b4b;
        }
        .permission-pill .btn {
            padding: 4px 9px;
            font-size: 0.8rem;
        }
        .form-control, .form-select {
            border-radius: 12px;
            border: 1px solid #e6cfcf;
            padding: 10px 12px;
        }
        .form-control:focus, .form-select:focus {
            border-color: #e84545;
            box-shadow: 0 0 0 0.2rem rgba(232, 69, 69, 0.15);
        }
        .btn-primary {
            background: linear-gradient(135deg, #e84545, #ff6b6b);
            border: none;
            border-radius: 999px;
            padding: 10px 16px;
            font-weight: 600;
        }
        .btn-danger {
            border-radius: 999px;
            padding: 10px 16px;
            font-weight: 600;
        }
        .btn:hover {
            transform: translateY(-1px);
            transition: all 0.2s ease;
        }
        .empty-state {
            padding: 20px;
            text-align: center;
            color: #6c757d;
            background: #fffaf9;
            border: 1px dashed #f0dede;
            border-radius: 14px;
        }
    </style>
</head>
<body>
    <?php 
    include '../includes/functions.php';
    include 'includes/header.php'; 
    include 'db.php'; 

    $role_id = null;
    if (isset($_POST['role_id']) && !empty($_POST['role_id'])) {
        $role_id = (int) $_POST['role_id'];
    } elseif (isset($_GET['role_id']) && !empty($_GET['role_id'])) {
        $role_id = (int) $_GET['role_id'];
    }

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        

        if (isset($_POST['add_permission'])) {
            $new_role_id = (int) ($_POST['new_role_id'] ?? 0);
            $new_nav_item_id = (int) ($_POST['new_nav_item_id'] ?? 0);

            if ($new_role_id > 0 && $new_nav_item_id > 0) {
                // check if mapping exists
                $checkSql = "SELECT 1 FROM navigation_item_roles WHERE navigation_item_id = ? AND role_id = ? LIMIT 1";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param('ii', $new_nav_item_id, $new_role_id);
                $checkStmt->execute();
                $checkStmt->store_result();

                if ($checkStmt->num_rows === 0) {
                    $sql = "INSERT INTO navigation_item_roles (navigation_item_id, role_id) VALUES (?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('ii', $new_nav_item_id, $new_role_id);

                    if ($stmt->execute()) {
                        echo "Permission added successfully.";
                        echo "<script>location.replace('staff_role_permission.php');</script>";
                        exit;
                    } else {
                        echo "Error adding permission: " . $conn->error;
                    }
                    $stmt->close();
                } else {
                    // already exists — redirect silently or with message
                    echo "<script>alert('Permission already exists for this role.'); location.replace('staff_role_permission.php');</script>";
                    exit;
                }
                $checkStmt->close();
            } else {
                echo "<script>alert('Invalid role or navigation item selected.'); location.replace('staff_role_permission.php');</script>";
                exit;
            }
        }

       // $conn->close();
    

        if (isset($_POST['update'])) {
            $permissions = isset($_POST['permissions']) ? $_POST['permissions'] : [];

            $deleteSql = "DELETE FROM navigation_item_roles WHERE role_id = ?";
            $deleteStmt = $conn->prepare($deleteSql);
            $deleteStmt->bind_param('i', $role_id);
            $deleteStmt->execute();
            $deleteStmt->close();

            if (!empty($permissions)) {
                $insertSql = "INSERT INTO navigation_item_roles (navigation_item_id, role_id) VALUES (?, ?)";
                $insertStmt = $conn->prepare($insertSql);
                foreach ($permissions as $permission_id) {
                    $permission_id = (int) $permission_id;
                    $insertStmt->bind_param('ii', $permission_id, $role_id);
                    $insertStmt->execute();
                }
                $insertStmt->close();
            }

            echo "<script>location.replace('staff_role_permission.php');</script>";
            exit;
        }

        if (isset($_POST['delete_role'])) {
            $sql = "DELETE FROM roles WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $role_id);

            if ($stmt->execute()) {
                echo "<script>alert('Role deleted successfully.'); location.replace('staff_role_permission.php');</script>";
                exit;
            } else {
                echo "Error deleting role: " . $conn->error;
            }
            $stmt->close();
        }

        if (isset($_POST['delete_permission'])) {
            $permission_id = (int) $_POST['delete_permission'];

            $sql = "DELETE FROM navigation_item_roles WHERE navigation_item_id = ? AND role_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ii', $permission_id, $role_id);

            if ($stmt->execute()) {
                echo "<script>alert('Permission deleted successfully.'); location.replace('staff_role_permission.php');</script>";
                exit;
            } else {
                echo "Error deleting permission: " . $conn->error;
            }
            $stmt->close();
        }

        //$conn->close();
    }

    // Ensure the new expense navigation items are available for assignment
    if (isset($conn) && $conn instanceof mysqli) {
        $expectedNavigationItems = [
            ['title' => 'View Expenses', 'url' => 'add_expenses.php', 'icon' => 'bi bi-cash-stack', 'parent_id' => null],
            ['title' => 'Add Expenses', 'url' => 'add_expenses.php', 'icon' => 'bi bi-cash-stack', 'parent_id' => null]
        ];

        foreach ($expectedNavigationItems as $navItem) {
            $checkStmt = $conn->prepare("SELECT id FROM navigation_items WHERE title = ? LIMIT 1");
            $checkStmt->bind_param('s', $navItem['title']);
            $checkStmt->execute();
            $checkStmt->store_result();

            if ($checkStmt->num_rows === 0) {
                $insertStmt = $conn->prepare("INSERT INTO navigation_items (title, url, icon, parent_id) VALUES (?, ?, ?, ?)");
                $insertStmt->bind_param('sssi', $navItem['title'], $navItem['url'], $navItem['icon'], $navItem['parent_id']);
                $insertStmt->execute();
                $insertStmt->close();
            }

            $checkStmt->close();
        }

        // Ensure Payroll parent and child items exist
        $payrollParentTitle = 'Payroll';
        $payrollParentUrl = 'payroll.php';
        $payrollParentIcon = 'bi bi-briefcase';
        $payrollParentId = null;

        $checkPayrollParent = $conn->prepare("SELECT id FROM navigation_items WHERE title = ? LIMIT 1");
        $checkPayrollParent->bind_param('s', $payrollParentTitle);
        $checkPayrollParent->execute();
        $checkPayrollParent->store_result();

        if ($checkPayrollParent->num_rows === 0) {
            $insertPayrollParent = $conn->prepare("INSERT INTO navigation_items (title, url, icon) VALUES (?, ?, ?)");
            $insertPayrollParent->bind_param('sss', $payrollParentTitle, $payrollParentUrl, $payrollParentIcon);
            $insertPayrollParent->execute();
            $payrollParentId = $conn->insert_id;
            $insertPayrollParent->close();
        } else {
            $checkPayrollParent->bind_result($payrollParentId);
            $checkPayrollParent->fetch();
        }
        $checkPayrollParent->close();

        $payrollChildren = [
            ['title' => 'Add Payroll', 'url' => 'add_payroll.php', 'icon' => 'bi bi-plus-circle'],
            ['title' => 'View Payroll', 'url' => 'view_payroll.php', 'icon' => 'bi bi-eye'],
            ['title' => 'Payroll Settings', 'url' => 'payroll_settings.php', 'icon' => 'bi bi-gear']
        ];

        foreach ($payrollChildren as $childItem) {
            $checkChild = $conn->prepare("SELECT id FROM navigation_items WHERE title = ? AND parent_id = ? LIMIT 1");
            $checkChild->bind_param('si', $childItem['title'], $payrollParentId);
            $checkChild->execute();
            $checkChild->store_result();

            if ($checkChild->num_rows === 0) {
                $insertChild = $conn->prepare("INSERT INTO navigation_items (title, url, icon, parent_id) VALUES (?, ?, ?, ?)");
                $insertChild->bind_param('sssi', $childItem['title'], $childItem['url'], $childItem['icon'], $payrollParentId);
                $insertChild->execute();
                $insertChild->close();
            }
            $checkChild->close();
        }

        // Ensure Resources parent and child items exist
        $resourcesParentTitle = 'Resources';
        $resourcesParentUrl = '#';
        $resourcesParentIcon = 'bi bi-folder2-open';
        $resourcesParentId = null;

        $checkResourcesParent = $conn->prepare("SELECT id FROM navigation_items WHERE title = ? LIMIT 1");
        $checkResourcesParent->bind_param('s', $resourcesParentTitle);
        $checkResourcesParent->execute();
        $checkResourcesParent->store_result();

        if ($checkResourcesParent->num_rows === 0) {
            $insertResourcesParent = $conn->prepare("INSERT INTO navigation_items (title, url, icon) VALUES (?, ?, ?)");
            $insertResourcesParent->bind_param('sss', $resourcesParentTitle, $resourcesParentUrl, $resourcesParentIcon);
            $insertResourcesParent->execute();
            $resourcesParentId = $conn->insert_id;
            $insertResourcesParent->close();
        } else {
            $checkResourcesParent->bind_result($resourcesParentId);
            $checkResourcesParent->fetch();
        }
        $checkResourcesParent->close();

        $resourcesChildren = [
            ['title' => 'Demand Letter', 'url' => 'demand_letter.php', 'icon' => 'bi bi-file-earmark-text'],
            ['title' => 'Application Form', 'url' => 'application_form.php', 'icon' => 'bi bi-file-text'],
            ['title' => 'Interview Letter', 'url' => 'interview_letter.php', 'icon' => 'bi bi-envelope-paper'],
            ['title' => 'Offer Letter', 'url' => 'offer_letter.php', 'icon' => 'bi bi-file-richtext'],
            ['title' => 'Performance Contract Letter', 'url' => 'performance_contract_letter.php', 'icon' => 'bi bi-journal-text'],
            ['title' => 'Asset Recovery Letter', 'url' => 'asset_recovery_letter.php', 'icon' => 'bi bi-shield-check'],
            ['title' => 'Termination Letter', 'url' => 'termination_letter.php', 'icon' => 'bi bi-person-x']
        ];

        foreach ($resourcesChildren as $childItem) {
            $checkResourcesChild = $conn->prepare("SELECT id FROM navigation_items WHERE title = ? AND parent_id = ? LIMIT 1");
            $checkResourcesChild->bind_param('si', $childItem['title'], $resourcesParentId);
            $checkResourcesChild->execute();
            $checkResourcesChild->store_result();

            if ($checkResourcesChild->num_rows === 0) {
                $insertResourcesChild = $conn->prepare("INSERT INTO navigation_items (title, url, icon, parent_id) VALUES (?, ?, ?, ?)");
                $insertResourcesChild->bind_param('sssi', $childItem['title'], $childItem['url'], $childItem['icon'], $resourcesParentId);
                $insertResourcesChild->execute();
                $insertResourcesChild->close();
            }
            $checkResourcesChild->close();
        }

        // Ensure Advance parent and 'advance' child exist
        $parentTitle = 'Advance';
        $parentUrl = '#';
        $parentIcon = 'bi bi-wallet2';

        $checkParent = $conn->prepare("SELECT id FROM navigation_items WHERE title = ? LIMIT 1");
        $checkParent->bind_param('s', $parentTitle);
        $checkParent->execute();
        $checkParent->store_result();

        if ($checkParent->num_rows === 0) {
            // Insert parent without parent_id
            $insertParent = $conn->prepare("INSERT INTO navigation_items (title, url, icon) VALUES (?, ?, ?)");
            $insertParent->bind_param('sss', $parentTitle, $parentUrl, $parentIcon);
            $insertParent->execute();
            $parent_id = $conn->insert_id;
            $insertParent->close();
        } else {
            $checkParent->bind_result($parent_id);
            $checkParent->fetch();
        }
        $checkParent->close();

        // child item
        $childTitle = 'advance';
        $childUrl = 'advance.php';
        $childIcon = 'bi bi-coin';

        $checkChild = $conn->prepare("SELECT id FROM navigation_items WHERE title = ? AND parent_id = ? LIMIT 1");
        $checkChild->bind_param('si', $childTitle, $parent_id);
        $checkChild->execute();
        $checkChild->store_result();

        if ($checkChild->num_rows === 0) {
            $insertChild = $conn->prepare("INSERT INTO navigation_items (title, url, icon, parent_id) VALUES (?, ?, ?, ?)");
            $insertChild->bind_param('sssi', $childTitle, $childUrl, $childIcon, $parent_id);
            $insertChild->execute();
            $insertChild->close();
        }
        $checkChild->close();

        // Ensure both parent and child are assigned to a default admin role (role_id = 1) if not already mapped
        $defaultRole = 1;
        // parent mapping
        if (!empty($parent_id)) {
            $checkMap = $conn->prepare("SELECT 1 FROM navigation_item_roles WHERE navigation_item_id = ? AND role_id = ? LIMIT 1");
            $checkMap->bind_param('ii', $parent_id, $defaultRole);
            $checkMap->execute();
            $checkMap->store_result();
            if ($checkMap->num_rows === 0) {
                $insMap = $conn->prepare("INSERT INTO navigation_item_roles (navigation_item_id, role_id) VALUES (?, ?)");
                $insMap->bind_param('ii', $parent_id, $defaultRole);
                $insMap->execute();
                $insMap->close();
            }
            $checkMap->close();
        }

        // get child id (by title and parent) to avoid matching parent when titles differ only by case
        $getChildId = $conn->prepare("SELECT id FROM navigation_items WHERE title = ? AND parent_id = ? LIMIT 1");
        $getChildId->bind_param('si', $childTitle, $parent_id);
        $getChildId->execute();
        $getChildId->bind_result($child_id);
        $getChildId->fetch();
        $getChildId->close();

        if (!empty($child_id)) {
            $checkMapC = $conn->prepare("SELECT 1 FROM navigation_item_roles WHERE navigation_item_id = ? AND role_id = ? LIMIT 1");
            $checkMapC->bind_param('ii', $child_id, $defaultRole);
            $checkMapC->execute();
            $checkMapC->store_result();
            if ($checkMapC->num_rows === 0) {
                $insMapC = $conn->prepare("INSERT INTO navigation_item_roles (navigation_item_id, role_id) VALUES (?, ?)");
                $insMapC->bind_param('ii', $child_id, $defaultRole);
                $insMapC->execute();
                $insMapC->close();
            }
            $checkMapC->close();
        }

        if (!empty($resourcesParentId)) {
            $checkResourcesMap = $conn->prepare("SELECT 1 FROM navigation_item_roles WHERE navigation_item_id = ? AND role_id = ? LIMIT 1");
            $checkResourcesMap->bind_param('ii', $resourcesParentId, $defaultRole);
            $checkResourcesMap->execute();
            $checkResourcesMap->store_result();
            if ($checkResourcesMap->num_rows === 0) {
                $insResourcesMap = $conn->prepare("INSERT INTO navigation_item_roles (navigation_item_id, role_id) VALUES (?, ?)");
                $insResourcesMap->bind_param('ii', $resourcesParentId, $defaultRole);
                $insResourcesMap->execute();
                $insResourcesMap->close();
            }
            $checkResourcesMap->close();
        }

        foreach ($resourcesChildren as $childItem) {
            $getResourcesChildId = $conn->prepare("SELECT id FROM navigation_items WHERE title = ? AND parent_id = ? LIMIT 1");
            $getResourcesChildId->bind_param('si', $childItem['title'], $resourcesParentId);
            $getResourcesChildId->execute();
            $getResourcesChildId->bind_result($resourcesChildId);
            $getResourcesChildId->fetch();
            $getResourcesChildId->close();

            if (!empty($resourcesChildId)) {
                $checkResourcesChildMap = $conn->prepare("SELECT 1 FROM navigation_item_roles WHERE navigation_item_id = ? AND role_id = ? LIMIT 1");
                $checkResourcesChildMap->bind_param('ii', $resourcesChildId, $defaultRole);
                $checkResourcesChildMap->execute();
                $checkResourcesChildMap->store_result();
                if ($checkResourcesChildMap->num_rows === 0) {
                    $insResourcesChildMap = $conn->prepare("INSERT INTO navigation_item_roles (navigation_item_id, role_id) VALUES (?, ?)");
                    $insResourcesChildMap->bind_param('ii', $resourcesChildId, $defaultRole);
                    $insResourcesChildMap->execute();
                    $insResourcesChildMap->close();
                }
                $checkResourcesChildMap->close();
            }
        }
    }

    // Fetch roles and permissions for display
    $allroles = getRoles();
    $allpermissions = getAllNavigationItems();
    // Fetch the selected role and permissions
    if ($role_id !== null) {
        $role = getRole($role_id);
        $permissions = getNavigationItems($role_id);
    } else {
        echo "<script>alert('Role ID is not provided or is invalid.');</script>";
        $role = null;
        $permissions = [];
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
                                <td><?php echo htmlspecialchars($role['name'] ?? 'Unknown Role', ENT_QUOTES); ?></td>
                                <td>
                                    <ul>
                                        <?php
                                        //$role_permissions = explode(',', $role['permissions']);
                                        foreach ($permissions as $permission) {
                                            
                                                                                echo "<li>
                                                                                                        <input type='checkbox' name='permissions[]' value='".htmlspecialchars($permission['id'])."' aria-label='".htmlspecialchars($permission['title'])."'> 
                                                                                                        ".htmlspecialchars($permission['title'])."
                                                                                                        <button type='submit' name='delete_permission' value='".htmlspecialchars($permission['id'])."' class='btn btn-danger btn-sm' onclick=\"return confirm('Delete this permission?');\">Delete</button>
                                                                                                    </li>";
                                        }
                                        ?>
                                    </ul>
                                </td>
                                <td>
                                    <input type="hidden" name="role_id" value="<?php echo htmlspecialchars($role_id); ?>">
                                    <button type="submit" name="update" class="btn btn-primary">Update</button>
                                    <button type="submit" name="delete_role" class="btn btn-danger" onclick="return confirm('Delete this role?');">Delete</button>
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
                            <?php foreach ($allroles as $roleOption): ?>
                                <option value="<?php echo htmlspecialchars($roleOption['id']); ?>"><?php echo htmlspecialchars($roleOption['name']); ?></option>
                            <?php endforeach; ?>
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
