<?php
session_start();
require_once 'db.php';

$selectedLoanOfficerEmail = isset($_SESSION['email']) ? $_SESSION['email'] : '';
$officerStmt = $conn->prepare("SELECT email, name AS full_name FROM users WHERE role_id = '2' ORDER BY name");
$officerStmt->execute();
$officerResult = $officerStmt->get_result();
$loanOfficers = $officerResult->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Add Borrower</title>
    <meta content="" name="description">
    <meta content="" name="keywords">

    <style>
        body { background: #f8f8f8; color: #282828; font-family: 'Segoe UI', Tahoma, sans-serif; }
        .main { box-sizing: border-box; height: 100vh; margin-left: 250px; overflow: hidden; padding: 88px 24px 24px; }
        .page-container { display: flex; flex-direction: column; height: 100%; margin: 0 auto; max-width: 1180px; min-height: 0; }
        .shell { background: #fff; border: 0; border-radius: 18px; box-shadow: 0 12px 28px rgba(0, 0, 0, .08); display: flex; flex: 1; flex-direction: column; min-height: 0; }
        .company-header { display: flex; flex: 0 0 auto; justify-content: space-between; align-items: center; gap: 18px; border-bottom: 2px solid #ef4444; padding-bottom: 18px; margin-bottom: 24px; }
        .borrower-page-heading { flex: 0 0 auto; }
        .borrower-scroll-area { flex: 1 1 auto; min-height: 0; overflow-y: auto; overscroll-behavior: contain; padding: 0 8px 12px 0; }
        .company-header img { height: 64px; width: auto; object-fit: contain; }
        .section-panel { background: #fff7f7; border: 1px solid #f4d1d1; border-radius: 12px; padding: 20px; height: 100%; }
        .summary-panel { background: #fff; border: 1px solid #e7e7e7; border-radius: 16px; padding: 24px; height: 100%; }
        .section-title { color: #0b2f9f; font-size: 1.05rem; font-weight: 700; margin-bottom: 18px; padding-bottom: 10px; border-bottom: 2px solid #ef4444; }
        .form-label { font-weight: 600; }
        .form-control, .form-select { border-radius: 8px; border-color: #d9d9d9; min-height: 44px; }
        .form-control:focus, .form-select:focus { border-color: #0b2f9f; box-shadow: 0 0 0 .2rem rgba(11, 47, 159, .12); }
        .btn-primary { background: linear-gradient(135deg, #e84545, #ff6b6b); border: 0; }
        .btn-primary:hover { background: linear-gradient(135deg, #d93d3d, #eb5a5a); }
        .summary-item { border-left: 4px solid #0b2f9f; background: #f7f9ff; padding: 13px 15px; margin-bottom: 12px; }
        .summary-item span { color: #64748b; display: block; font-size: .85rem; }
        .summary-item strong { color: #0b2f9f; display: block; margin-top: 4px; overflow-wrap: anywhere; }
        @media (max-width: 1199px) { .main { margin-left: 0; padding: 88px 16px 20px; } }
        @media (max-width: 768px) { .main { padding: 78px 10px 12px; } .shell { padding: 18px !important; } .company-header img { height: 52px; } }
    </style>

    <!-- Favicons -->
    <link href="/assets/img/logo.png" rel="icon">
    <link href="/assets/img/logo.png" rel="apple-touch-icon">

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans&family=Montserrat&family=Poppins&display=swap" rel="stylesheet">

    <!-- Vendor CSS Files -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">

    <!-- Main CSS File -->
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<body class="admin-page">

<?php
include("../includes/functions.php");
include("includes/header.php");
?>
<?php include "../includes/sidebar.php"; ?>
<main class="main">
    <div class="page-container">
        <div class="shell p-4">
            <div class="company-header">
                <div>
                    <div class="fw-bold text-uppercase text-secondary" style="letter-spacing: 1px;">Inua Premium Services</div>
                    <div class="text-muted small">Borrower Management</div>
                </div>
                <img src="../assets/img/logo.png" alt="Inua Premium Services Logo">
            </div>

            <div class="borrower-page-heading d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                <div>
                    <h2 class="mb-1">Add Borrower</h2>
                    <div class="text-muted">Register borrower details and assign a loan officer.</div>
                </div>
                <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
            </div>

            <div class="borrower-scroll-area">
            <form action="insert_borrower.php" method="post" enctype="multipart/form-data" id="borrowerForm">
                <div class="row g-4">
                    <div class="col-lg-7">
                        <div class="section-panel">
                            <div class="section-title">Borrower and Guarantor Details</div>
                            <div class="mb-3">
                                <label for="loanOfficer" class="form-label">Loan Officer</label>
                                <select name="loanOfficer" id="loanOfficer" class="form-select" required>
                                    <option value="">Select loan officer</option>
                                    <?php foreach ($loanOfficers as $officer): ?>
                                        <option value="<?php echo htmlspecialchars($officer['email'], ENT_QUOTES); ?>" data-name="<?php echo htmlspecialchars($officer['full_name'], ENT_QUOTES); ?>" <?php echo ($selectedLoanOfficerEmail === $officer['email']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($officer['full_name'] . ' (' . $officer['email'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="fullName" class="form-label">Full Name</label>
                                    <input type="text" id="fullName" name="full_name" class="form-control" autocomplete="name" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="mobile" class="form-label">Mobile Number</label>
                                    <input type="tel" id="mobile" name="mobile" class="form-control" autocomplete="tel">
                                </div>
                                <div class="col-12">
                                    <label for="idNumber" class="form-label">ID Number</label>
                                    <input type="text" id="idNumber" name="id_number" class="form-control" required>
                                </div>
                            </div>

                            <div class="section-title mt-4">Guarantor and Business</div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="guarantorName" class="form-label">Guarantor Name</label>
                                    <input type="text" id="guarantorName" name="guarantor_name" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label for="guarantorPhone" class="form-label">Guarantor Phone Number</label>
                                    <input type="tel" id="guarantorPhone" name="guarantor_phone" class="form-control">
                                </div>
                                <div class="col-12">
                                    <label for="businessName" class="form-label">Business Name</label>
                                    <input type="text" id="businessName" name="business_name" class="form-control">
                                </div>
                            </div>

                            <input type="hidden" name="total_paid" value="0.00">
                            <input type="hidden" name="open_loans_balance" value="0.00">
                            <button type="submit" class="btn btn-primary btn-lg w-100 mt-4">Add Borrower</button>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="summary-panel">
                            <div class="section-title">Assignment Summary</div>
                            <div class="summary-item">
                                <span>Selected Loan Officer</span>
                                <strong id="selectedOfficerName"><?php echo htmlspecialchars($selectedLoanOfficerEmail !== '' ? $selectedLoanOfficerEmail : 'Not selected'); ?></strong>
                            </div>
                            <div class="summary-item">
                                <span>Borrower Profile</span>
                                <strong>Individual registration</strong>
                            </div>
                            <div class="summary-item">
                                <span>Registration Status</span>
                                <strong>New borrower</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
            </div>
        </div>
    </div>
</main>

<script>
    const loanOfficerSelect = document.getElementById('loanOfficer');
    const selectedOfficerName = document.getElementById('selectedOfficerName');
    function updateSelectedOfficer() {
        const option = loanOfficerSelect.options[loanOfficerSelect.selectedIndex];
        selectedOfficerName.textContent = option && option.value ? option.dataset.name + ' (' + option.value + ')' : 'Not selected';
    }
    loanOfficerSelect.addEventListener('change', updateSelectedOfficer);
    updateSelectedOfficer();
</script>

    <!-- Vendor JS Files -->
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/vendor/aos/aos.js"></script>
    <script src="assets/vendor/glightbox/js/glightbox.min.js"></script>
    <script src="assets/vendor/swiper/swiper-bundle.min.js"></script>

    <!-- Main JS File -->
    <script src="assets/js/main.js"></script>
</body>
</html>
