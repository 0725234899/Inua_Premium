<?php
include_once("../includes/functions.php");

// Get the list of loan officers, areas, and roles
$loanOfficers = getStaff();
$areas = getAreas(); // Assuming getAreas function is defined similarly
$roles = getRoles();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Loan Officers - Inua Premium Services</title>
    <link href="/assets/img/logo.png" rel="icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" integrity="sha512-jnSuA4Ss2PkkikSOLtYs8BlYIeeIK1h99ty4YfvRPAlzr377vr3CXDb7sb7eEEBYjDtcYj+AjBH3FLv5uSJuXg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="/assets/img/logo.png" rel="apple-touch-icon">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans&family=Montserrat&family=Poppins&display=swap" rel="stylesheet">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.min.js" integrity="sha512-ykZ1QQr0Jy/4ZkvKuqWn4iF3lqPZyij9iRv6sGqLRdTPkY69YX6+7wvVGmsdBbiIfN/8OdsI7HABjvEok6ZopQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <!-- Main JS File -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.js" integrity="sha512-+k1pnlgt4F1H8L7t3z95o3/KO+o78INEcXTbnoJQ/F2VqDVhWoaiVml/OEHv9HsVgxUaVW+IbiZPUJQfF/YxZw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <style>
        /* Additional or overridden styles specific to this page */
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
    </style>
</head>
<body class="admin-page">
    <!-- Header -->
    <?php
    include("includes/header.php");
    include("../includes/sidebar.php");
    ?>
    <main class="main">
        <section id="loan-officers" class="loan-officers section">
            <div class="container">
                <h1>Staff</h1>
                <button class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#addLoanOfficerModal">Add Staff</button>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Role</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($loanOfficers)): ?>
                                <?php foreach ($loanOfficers as $officer): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($officer['id']); ?></td>
                                        <td><?php echo htmlspecialchars($officer['name']); ?></td>
                                        <td><?php echo htmlspecialchars($officer['email']); ?></td>
                                        <td><?php echo htmlspecialchars($officer['phone']); ?></td>
                                        <td><?php echo getRole($officer['role_id'])['name'];?></td>
                                        <td>
                                            <button type="button" class="btn btn-primary btn-sm edit-staff-btn"
                                                    data-id="<?php echo htmlspecialchars($officer['id']); ?>"
                                                    data-name="<?php echo htmlspecialchars($officer['name'], ENT_QUOTES); ?>"
                                                    data-email="<?php echo htmlspecialchars($officer['email'], ENT_QUOTES); ?>"
                                                    data-phone="<?php echo htmlspecialchars($officer['phone'], ENT_QUOTES); ?>"
                                                    data-basic_salary="<?php echo htmlspecialchars(number_format(floatval($officer['basic_salary'] ?? 0), 2), ENT_QUOTES); ?>"
                                                    data-area="<?php echo htmlspecialchars($officer['area'] ?? '', ENT_QUOTES); ?>"
                                                    data-role="<?php echo htmlspecialchars($officer['role_id'] ?? '', ENT_QUOTES); ?>"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editStaffModal">
                                                Edit
                                            </button>
                                            <form action="delete_staff.php" method="POST" class="d-inline">
                                                <input type="hidden" name="id" value="<?php echo htmlspecialchars($officer['id']); ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete this staff member?')">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6">No loan officers found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
        
        <!-- Add Loan Officer Modal -->
        <div class="modal fade" id="addLoanOfficerModal" tabindex="-1" aria-labelledby="addLoanOfficerModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addLoanOfficerModalLabel">Add Staff</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="add_staff.php" method="POST">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="officerName" class="form-label">Name</label>
                                <input type="text" class="form-control" id="officerName" name="officerName" required>
                            </div>
                            <div class="mb-3">
                                <label for="officerEmail" class="form-label">Email</label>
                                <input type="email" class="form-control" id="officerEmail" name="officerEmail" required>
                            </div>
                            <div class="mb-3">
                                <label for="officerPhone" class="form-label">Phone</label>
                                <input type="text" class="form-control" id="officerPhone" name="officerPhone" required>
                            </div>
                            <div class="mb-3">
                                <label for="officerSalary" class="form-label">Basic Salary</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="officerSalary" name="basicSalary" value="0.00" required>
                            </div>
                            <div class="mb-3">
                                <label for="officerPassword" class="form-label">Password</label>
                                <input type="password" class="form-control" id="officerPassword" name="officerPassword" required>
                            </div>
                            <div class="mb-3">
                                <label for="areaId" class="form-label">Area</label>
                                <select class="form-select" id="areaId" name="areaId" required>
                                    <option value="">Select Area</option>
                                    <?php foreach ($areas as $area): ?>
                                        <option value="<?php echo htmlspecialchars($area['area_id']); ?>"><?php echo htmlspecialchars($area['area_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="roleId" class="form-label">Role</label>
                                <select class="form-select" id="roleId" name="roleId" required>
                                    <option value="">Select Role</option>
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?php echo htmlspecialchars($role['id']); ?>"><?php echo htmlspecialchars($role['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary" name="addStaff">Add Staff</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Edit Staff Modal -->
        <div class="modal fade" id="editStaffModal" tabindex="-1" aria-labelledby="editStaffModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editStaffModalLabel">Edit Staff</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="update_staff.php" method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="staffId" id="editStaffId">
                            <div class="mb-3">
                                <label for="editOfficerName" class="form-label">Name</label>
                                <input type="text" class="form-control" id="editOfficerName" name="officerName" required>
                            </div>
                            <div class="mb-3">
                                <label for="editOfficerEmail" class="form-label">Email</label>
                                <input type="email" class="form-control" id="editOfficerEmail" name="officerEmail" required>
                            </div>
                            <div class="mb-3">
                                <label for="editOfficerPhone" class="form-label">Phone</label>
                                <input type="text" class="form-control" id="editOfficerPhone" name="officerPhone" required>
                            </div>
                            <div class="mb-3">
                                <label for="editBasicSalary" class="form-label">Basic Salary</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="editBasicSalary" name="basicSalary" value="0.00" required>
                            </div>
                            <div class="mb-3">
                                <label for="editOfficerPassword" class="form-label">New Password (leave blank to keep current)</label>
                                <input type="password" class="form-control" id="editOfficerPassword" name="officerPassword">
                            </div>
                            <div class="mb-3">
                                <label for="editAreaId" class="form-label">Area</label>
                                <select class="form-select" id="editAreaId" name="areaId" required>
                                    <option value="">Select Area</option>
                                    <?php foreach ($areas as $area): ?>
                                        <option value="<?php echo htmlspecialchars($area['area_id']); ?>"><?php echo htmlspecialchars($area['area_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="editRoleId" class="form-label">Role</label>
                                <select class="form-select" id="editRoleId" name="roleId" required>
                                    <option value="">Select Role</option>
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?php echo htmlspecialchars($role['id']); ?>"><?php echo htmlspecialchars($role['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary" name="updateStaff">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
    <!-- Vendor JS Files -->
    
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/vendor/aos/aos.js"></script>
    <script src="assets/vendor/glightbox/js/glightbox.min.js"></script>
    <script src="assets/vendor/swiper/swiper-bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.min.js" integrity="sha512-ykZ1QQr0Jy/4ZkvKuqWn4iF3lqPZyij9iRv6sGqLRdTPkY69YX6+7wvVGmsdBbiIfN/8OdsI7HABjvEok6ZopQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <!-- Main JS File -->
    <script src="assets/js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.edit-staff-btn').forEach(function (button) {
                button.addEventListener('click', function () {
                    document.getElementById('editStaffId').value = this.getAttribute('data-id');
                    document.getElementById('editOfficerName').value = this.getAttribute('data-name');
                    document.getElementById('editOfficerEmail').value = this.getAttribute('data-email');
                    document.getElementById('editOfficerPhone').value = this.getAttribute('data-phone');
                    document.getElementById('editBasicSalary').value = this.getAttribute('data-basic_salary') || '0.00';
                    document.getElementById('editAreaId').value = this.getAttribute('data-area');
                    document.getElementById('editRoleId').value = this.getAttribute('data-role');
                    document.getElementById('editOfficerPassword').value = '';
                });
            });
        });
    </script>
</body>
</html>
