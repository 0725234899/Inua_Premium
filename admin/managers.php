<?php
include_once("../includes/functions.php");

// Fetch managers
function getManagers() {
    global $conn;
    $query = "SELECT manager_id AS id, name, email, phone FROM managers";
    $result = mysqli_query($conn, $query);
    
    if (!$result) {
        die('Query failed: ' . mysqli_error($conn));
    }
    
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

$managers = getManagers();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Managers - Inua Premium Services</title>
    <link href="/assets/img/logo.png" rel="icon">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="admin-page">
    <?php include("includes/header.php"); ?>
    <?php include("../includes/sidebar.php"); ?>
    
    <main class="main">
        <section id="managers" class="managers section">
            <div class="container">
                <h1>Managers</h1>
                <button class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#addManagerModal">Add Manager</button>
                
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($managers)): ?>
                                <?php foreach ($managers as $manager): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($manager['id']); ?></td>
                                        <td><?php echo htmlspecialchars($manager['name']); ?></td>
                                        <td><?php echo htmlspecialchars($manager['email']); ?></td>
                                        <td><?php echo htmlspecialchars($manager['phone']); ?></td>
                                        <td>
                                            <a href="edit_manager.php?id=<?php echo htmlspecialchars($manager['id']); ?>" class="btn btn-primary">Edit</a>
                                            <a href="delete_manager.php?id=<?php echo htmlspecialchars($manager['id']); ?>" class="btn btn-danger">Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5">No managers found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
        
        <!-- Add Manager Modal -->
        <div class="modal fade" id="addManagerModal" tabindex="-1" aria-labelledby="addManagerModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addManagerModalLabel">Add Manager</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="add_manager.php" method="POST">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="managerName" class="form-label">Name</label>
                                <input type="text" class="form-control" id="managerName" name="managerName" required>
                            </div>
                            <div class="mb-3">
                                <label for="managerEmail" class="form-label">Email</label>
                                <input type="email" class="form-control" id="managerEmail" name="managerEmail" required>
                            </div>
                            <div class="mb-3">
                                <label for="managerPhone" class="form-label">Phone</label>
                                <input type="text" class="form-control" id="managerPhone" name="managerPhone" required>
                            </div>
                            <div class="mb-3">
                                <label for="managerPassword" class="form-label">Password</label>
                                <input type="password" class="form-control" id="managerPassword" name="managerPassword" required>
                            </div>
                            <div class="mb-3">
                                <label for="areaId" class="form-label">Area</label>
                                <select class="form-select" id="areaId" name="areaId" required>
                                    <option value="">Select Area</option>
                                    <!-- Add area options dynamically here -->
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Add Manager</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
