<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['email']) || empty($_SESSION['email'])) {
    header('Location: ../index.html');
    exit();
}

include '../includes/functions.php';

$companyId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$company = $companyId ? getCompanyById($companyId) : null;
$companies = getCompanies();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $logoPath = '';
    $uploadDir = dirname(__DIR__) . '/uploads/company_logos';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK && !empty($_FILES['logo']['name'])) {
        $fileExtension = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
        $safeName = time() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', basename($_FILES['logo']['name']));
        $targetPath = $uploadDir . '/' . $safeName;

        if (move_uploaded_file($_FILES['logo']['tmp_name'], $targetPath)) {
            $logoPath = 'company_logos/' . $safeName;
        }
    }

    $companyData = [
        'id' => $_POST['id'] ?? null,
        'name' => $_POST['name'] ?? $_POST['company_name'] ?? '',
        'registration_number' => $_POST['registration_number'] ?? '',
        'phone' => $_POST['phone'] ?? '',
        'email' => $_POST['email'] ?? '',
        'country' => $_POST['country'] ?? '',
        'timezone' => $_POST['timezone'] ?? '',
        'currency' => $_POST['currency'] ?? '',
        'address' => $_POST['address'] ?? '',
        'city' => $_POST['city'] ?? '',
        'province' => $_POST['province'] ?? '',
        'zipcode' => $_POST['zipcode'] ?? '',
        'logo' => $logoPath ?: ($_POST['existing_logo'] ?? ''),
        'status' => $_POST['status'] ?? 'Active',
    ];

    $saved = saveCompany($companyData);
    $successMessage = $saved ? 'Company saved successfully.' : 'Unable to save company. Please check the required fields.';
    $company = $companyData;
    $companies = getCompanies();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company Settings</title>
    <link href="/assets/img/logo.png" rel="icon">
    <link href="/assets/img/logo.png" rel="apple-touch-icon">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans&family=Montserrat&family=Poppins&display=swap" rel="stylesheet">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; font-family: 'Open Sans', sans-serif; }
        .main { margin-left: 270px; padding: 30px; }
        .section-card { background: #fff; border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.04); }
        .table td, .table th { vertical-align: middle; }
        .status-badge { display: inline-block; padding: 5px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
        .status-active { background: #d1fae5; color: #065f46; }
        .status-inactive { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="sidebar">
        <?php include '../includes/sidebar.php'; ?>
    </div>

    <main class="main">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="mb-0">Company Settings</h1>
                <a href="company_settings.php" class="btn btn-outline-primary">Refresh</a>
            </div>

            <?php if (isset($successMessage)): ?>
                <div class="alert alert-info"><?php echo htmlspecialchars($successMessage); ?></div>
            <?php endif; ?>

            <div class="row g-4">
                <div class="col-lg-5">
                    <div class="section-card">
                        <h4><?php echo $company ? 'Edit Company' : 'Add New Company'; ?></h4>
                        <form method="POST" action="company_settings.php" enctype="multipart/form-data">
                            <input type="hidden" name="id" value="<?php echo htmlspecialchars((string) ($company['id'] ?? '')); ?>">
                            <?php if (!empty($company['logo'] ?? '')): ?>
                                <input type="hidden" name="existing_logo" value="<?php echo htmlspecialchars($company['logo']); ?>">
                            <?php endif; ?>
                            <div class="mb-3">
                                <label class="form-label">Company Name</label>
                                <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($company['name'] ?? $company['company_name'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Registration Number</label>
                                <input type="text" class="form-control" name="registration_number" value="<?php echo htmlspecialchars($company['registration_number'] ?? ''); ?>">
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Phone</label>
                                    <input type="text" class="form-control" name="phone" value="<?php echo htmlspecialchars($company['phone'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($company['email'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Country</label>
                                    <input type="text" class="form-control" name="country" value="<?php echo htmlspecialchars($company['country'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Timezone</label>
                                    <input type="text" class="form-control" name="timezone" value="<?php echo htmlspecialchars($company['timezone'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Currency</label>
                                    <input type="text" class="form-control" name="currency" value="<?php echo htmlspecialchars($company['currency'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status">
                                        <option value="Active" <?php echo (($company['status'] ?? 'Active') === 'Active') ? 'selected' : ''; ?>>Active</option>
                                        <option value="Inactive" <?php echo (($company['status'] ?? 'Active') === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Address</label>
                                <textarea class="form-control" name="address" rows="3"><?php echo htmlspecialchars($company['address'] ?? ''); ?></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">City</label>
                                    <input type="text" class="form-control" name="city" value="<?php echo htmlspecialchars($company['city'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Province</label>
                                    <input type="text" class="form-control" name="province" value="<?php echo htmlspecialchars($company['province'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Zip Code</label>
                                <input type="text" class="form-control" name="zipcode" value="<?php echo htmlspecialchars($company['zipcode'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Company Logo</label>
                                <input type="file" class="form-control" name="logo" accept="image/*">
                                <?php if (!empty($company['logo'] ?? '')): ?>
                                    <div class="mt-3">
                                        <img src="../uploads/<?php echo htmlspecialchars($company['logo']); ?>" alt="Company Logo" style="max-width: 120px; max-height: 120px; border-radius: 8px; object-fit: cover;">
                                    </div>
                                <?php endif; ?>
                            </div>
                            <button class="btn btn-primary" type="submit"><?php echo $company ? 'Update Company' : 'Save Company'; ?></button>
                            <?php if ($company): ?>
                                <a href="company_settings.php" class="btn btn-secondary ms-2">Cancel</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="section-card">
                        <h4>Company List</h4>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Contact</th>
                                        <th>Location</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($companies)): ?>
                                        <?php foreach ($companies as $item): ?>
                                            <tr>
                                                <td>
                                                    <?php if (!empty($item['logo'])): ?>
                                                        <img src="../uploads/<?php echo htmlspecialchars($item['logo']); ?>" alt="Logo" style="width: 34px; height: 34px; border-radius: 50%; object-fit: cover; margin-right: 10px;">
                                                    <?php endif; ?>
                                                    <?php echo htmlspecialchars($item['name']); ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($item['registration_number'] ?: 'N/A'); ?><br>
                                                    <?php echo htmlspecialchars($item['email'] ?: 'N/A'); ?>
                                                </td>
                                                <td><?php echo htmlspecialchars(trim(($item['city'] ?: '') . ' ' . ($item['country'] ?: '')) ?: 'N/A'); ?></td>
                                                <td>
                                                    <span class="status-badge <?php echo ($item['status'] ?? 'Active') === 'Active' ? 'status-active' : 'status-inactive'; ?>">
                                                        <?php echo htmlspecialchars($item['status'] ?? 'Active'); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="company_settings.php?id=<?php echo (int) $item['id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center">No companies found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
