<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['email']) || empty($_SESSION['email'])) {
    header('Location: ../index.html');
    exit();
}

include '../includes/functions.php';
$settings = getSettings();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings - Admin Dashboard</title>
    <link href="/assets/img/logo.png" rel="icon">
    <link href="/assets/img/logo.png" rel="apple-touch-icon">
    
    <!-- Google Fonts & Bootstrap Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #e84545;
            --primary-hover: #d33232;
            --bg-light: #f8f9fa;
            --card-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            --transition: all 0.2s ease-in-out;
        }

        body {
            background-color: #f4f6f9;
            color: #333333;
            font-family: 'Plus Jakarta Sans', sans-serif;
            margin: 0;
        }

        .sidebar {
            background-color: #ffffff;
            width: 260px;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            box-shadow: 0 0 15px rgba(0,0,0,0.05);
            transition: var(--transition);
        }

        .main {
            margin-left: 260px;
            padding: 2rem;
            min-height: 100vh;
        }

        /* Glassmorphism Header / Card Styling */
        .page-header {
            background: #ffffff;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            background: #ffffff;
            margin-bottom: 1.5rem;
        }

        .card-header {
            background: transparent;
            border-bottom: 1px solid #edf2f7;
            padding: 1.25rem 1.5rem;
        }

        .card-title {
            font-weight: 600;
            color: #1a202c;
            margin: 0;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Modern Tabs Navigation */
        .nav-tabs-custom {
            border-bottom: 2px solid #edf2f7;
            gap: 1rem;
        }

        .nav-tabs-custom .nav-link {
            border: none;
            color: #718096;
            font-weight: 500;
            padding: 0.75rem 1rem;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: var(--transition);
        }

        .nav-tabs-custom .nav-link:hover {
            color: var(--primary-color);
        }

        .nav-tabs-custom .nav-link.active {
            color: var(--primary-color);
            background: transparent;
            border-bottom-color: var(--primary-color);
            font-weight: 600;
        }

        /* Form Controls */
        .form-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: #4a5568;
        }

        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            padding: 0.625rem 0.875rem;
            font-size: 0.9375rem;
            transition: var(--transition);
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(232, 69, 69, 0.15);
        }

        .input-group-text {
            border-radius: 8px 0 0 8px;
            background-color: #f8fafc;
            border-color: #e2e8f0;
            color: #718096;
        }

        /* Avatar Upload Container */
        .logo-upload-wrapper {
            border: 2px dashed #e2e8f0;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            background: #f8fafc;
            transition: var(--transition);
        }

        .logo-upload-wrapper:hover {
            border-color: var(--primary-color);
            background: #fff;
        }

        .logo-preview-img {
            max-width: 140px;
            max-height: 80px;
            object-fit: contain;
            border-radius: 6px;
            margin-bottom: 0.75rem;
        }

        /* Buttons */
        .btn-primary-custom {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: #ffffff;
            padding: 0.625rem 1.5rem;
            font-weight: 600;
            border-radius: 8px;
            transition: var(--transition);
        }

        .btn-primary-custom:hover {
            background-color: var(--primary-hover);
            border-color: var(--primary-hover);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(232, 69, 69, 0.25);
        }

        /* Fixed Action Bar at Bottom on Mobile / Normal position on Desktop */
        .action-bar {
            position: sticky;
            bottom: 1.5rem;
            background: #ffffff;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            box-shadow: 0 -4px 20px rgba(0,0,0,0.05);
            z-index: 10;
        }

        @media (max-width: 768px) {
            .sidebar {
                margin-left: -260px;
            }
            .main {
                margin-left: 0;
                padding: 1rem;
            }
        }
    </style>
</head>
<body>

    <?php include 'includes/header.php'; ?>

    <div class="sidebar">
        <?php include '../includes/sidebar.php'; ?>
    </div>

    <main class="main">
        <!-- Page Title & Header -->
        <div class="page-header d-flex justify-content-between align-items-center">
            <div>
                <h2 class="h4 mb-1 fw-bold text-dark">System & Account Settings</h2>
                <p class="text-muted small mb-0">Manage global settings, currency options, and company credentials.</p>
            </div>
            <span class="badge bg-danger-subtle text-danger px-3 py-2 rounded-pill fw-medium">
                <i class="bi bi-shield-check me-1"></i> Admin Access
            </span>
        </div>

        <form action="update_account_settings.php" method="post" enctype="multipart/form-data">
            
            <!-- Tabbed Navigation Bar -->
            <ul class="nav nav-tabs nav-tabs-custom mb-4" id="settingsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="company-tab" data-bs-toggle="tab" data-bs-target="#company" type="button" role="tab">
                        <i class="bi bi-building me-2"></i>Company Profile
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="loans-tab" data-bs-toggle="tab" data-bs-target="#loans" type="button" role="tab">
                        <i class="bi bi-calculator me-2"></i>Loan Configurations
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="business-tab" data-bs-toggle="tab" data-bs-target="#business" type="button" role="tab">
                        <i class="bi bi-geo-alt me-2"></i>Location & Branding
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="settingsTabsContent">
                
                <!-- 1. COMPANY SETTINGS -->
                <div class="tab-pane fade show active" id="company" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="bi bi-sliders text-danger"></i> General Preferences</h3>
                        </div>
                        <div class="card-body p-4">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="company_name" class="form-label">Company Name</label>
                                    <input type="text" class="form-control" id="company_name" name="company_name" value="<?php echo htmlspecialchars($settings['company_name'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="country" class="form-label">Country</label>
                                    <input type="text" class="form-control" id="country" name="country" value="<?php echo htmlspecialchars($settings['country'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="timezone" class="form-label">Timezone</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-clock"></i></span>
                                        <input type="text" class="form-control" id="timezone" name="timezone" value="<?php echo htmlspecialchars($settings['timezone'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="date_format" class="form-label">Date Format</label>
                                    <input type="text" class="form-control" id="date_format" name="date_format" value="<?php echo htmlspecialchars($settings['date_format'] ?? ''); ?>" placeholder="Y-m-d">
                                </div>
                                <div class="col-md-4">
                                    <label for="currency" class="form-label">Currency Symbol</label>
                                    <input type="text" class="form-control" id="currency" name="currency" value="<?php echo htmlspecialchars($settings['currency'] ?? ''); ?>">
                                </div>
                                <div class="col-md-8">
                                    <label for="currency_words" class="form-label">Currency (in Words)</label>
                                    <input type="text" class="form-control" id="currency_words" name="currency_words" value="<?php echo htmlspecialchars($settings['currency_words'] ?? ''); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="decimal_separator" class="form-label">Decimal Separator</label>
                                    <input type="text" class="form-control" id="decimal_separator" name="decimal_separator" value="<?php echo htmlspecialchars($settings['decimal_separator'] ?? ''); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="thousand_separator" class="form-label">Thousand Separator</label>
                                    <input type="text" class="form-control" id="thousand_separator" name="thousand_separator" value="<?php echo htmlspecialchars($settings['thousand_separator'] ?? ''); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="results_per_page" class="form-label">Pagination Limit</label>
                                    <input type="number" class="form-control" id="results_per_page" name="results_per_page" value="<?php echo htmlspecialchars($settings['results_per_page'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. LOAN SETTINGS -->
                <div class="tab-pane fade" id="loans" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="bi bi-percent text-danger"></i> Loan Repayment Cycles</h3>
                        </div>
                        <div class="card-body p-4">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="monthly_repayment_cycle" class="form-label">Monthly Repayment Cycle</label>
                                    <input type="text" class="form-control" id="monthly_repayment_cycle" name="monthly_repayment_cycle" value="<?php echo htmlspecialchars($settings['monthly_repayment_cycle'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="yearly_repayment_cycle" class="form-label">Yearly Repayment Cycle</label>
                                    <input type="text" class="form-control" id="yearly_repayment_cycle" name="yearly_repayment_cycle" value="<?php echo htmlspecialchars($settings['yearly_repayment_cycle'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="days_in_month" class="form-label">Days in a Month</label>
                                    <input type="number" class="form-control" id="days_in_month" name="days_in_month" value="<?php echo htmlspecialchars($settings['days_in_month'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="days_in_year" class="form-label">Days in a Year</label>
                                    <input type="number" class="form-control" id="days_in_year" name="days_in_year" value="<?php echo htmlspecialchars($settings['days_in_year'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. BUSINESS INFORMATION -->
                <div class="tab-pane fade" id="business" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="bi bi-briefcase text-danger"></i> Address & Brand Assets</h3>
                        </div>
                        <div class="card-body p-4">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="business_registration_number" class="form-label">Registration Number</label>
                                    <input type="text" class="form-control" id="business_registration_number" name="business_registration_number" value="<?php echo htmlspecialchars($settings['business_registration_number'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="address" class="form-label">Street Address</label>
                                    <input type="text" class="form-control" id="address" name="address" value="<?php echo htmlspecialchars($settings['address'] ?? ''); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="city" class="form-label">City</label>
                                    <input type="text" class="form-control" id="city" name="city" value="<?php echo htmlspecialchars($settings['city'] ?? ''); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="province" class="form-label">Province / State</label>
                                    <input type="text" class="form-control" id="province" name="province" value="<?php echo htmlspecialchars($settings['province'] ?? ''); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="zipcode" class="form-label">Zip / Postal Code</label>
                                    <input type="text" class="form-control" id="zipcode" name="zipcode" value="<?php echo htmlspecialchars($settings['zipcode'] ?? ''); ?>">
                                </div>

                                <div class="col-12 mt-4">
                                    <label class="form-label">Company Logo</label>
                                    <div class="logo-upload-wrapper">
                                        <?php if (!empty($settings['logo'])): ?>
                                            <div class="mb-2">
                                                <img src="../assets/img/<?php echo htmlspecialchars($settings['logo']); ?>" id="logoPreview" alt="Company Logo" class="logo-preview-img">
                                            </div>
                                        <?php else: ?>
                                            <div class="mb-2">
                                                <i class="bi bi-cloud-arrow-up display-5 text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="mb-3">
                                            <input class="form-control form-control-sm d-inline-block w-auto" type="file" id="company_logo" name="company_logo" accept="image/*" onchange="previewImage(event)">
                                        </div>
                                        <?php if (!empty($settings['logo'])): ?>
                                            <div class="form-check d-inline-block">
                                                <input class="form-check-input" type="checkbox" name="delete_logo" id="delete_logo" value="on">
                                                <label class="form-check-label text-danger small" for="delete_logo">
                                                    Delete current logo on save
                                                </label>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Persistent Bottom Action Bar -->
            <div class="action-bar d-flex justify-content-between align-items-center">
                <span class="text-muted small"><i class="bi bi-info-circle me-1"></i> Make sure to review all values before saving.</span>
                <button type="submit" class="btn btn-primary-custom d-flex align-items-center gap-2">
                    <i class="bi bi-check-circle-fill"></i> Save Changes
                </button>
            </div>

        </form>
    </main>

    <!-- Vendor Scripts -->
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/vendor/aos/aos.js"></script>
    <script src="assets/vendor/glightbox/js/glightbox.min.js"></script>
    <script src="assets/vendor/swiper/swiper-bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    
    <script>
        // Live Preview Uploaded Image
        function previewImage(event) {
            const reader = new FileReader();
            reader.onload = function() {
                let output = document.getElementById('logoPreview');
                if(output) {
                    output.src = reader.result;
                }
            };
            if(event.target.files[0]) {
                reader.readAsDataURL(event.target.files[0]);
            }
        }
    </script>
</body>
</html>