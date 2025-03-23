<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../includes/functions.php'; // Ensure this file contains necessary database connection functions

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and sanitize input values
    $companyName = htmlspecialchars($_POST['company_name']);
    $country = htmlspecialchars($_POST['country']);
    $timezone = htmlspecialchars($_POST['timezone']);
    $currency = htmlspecialchars($_POST['currency']);
    $currencyWords = htmlspecialchars($_POST['currency_words']);
    $dateFormat = htmlspecialchars($_POST['date_format']);
    $decimalSeparator = htmlspecialchars($_POST['decimal_separator']);
    $thousandSeparator = htmlspecialchars($_POST['thousand_separator']);
    $resultsPerPage = intval($_POST['results_per_page']);
    $monthlyRepaymentCycle = htmlspecialchars($_POST['monthly_repayment_cycle']);
    $yearlyRepaymentCycle = htmlspecialchars($_POST['yearly_repayment_cycle']);
    $daysInMonth = intval($_POST['days_in_month']);
    $daysInYear = intval($_POST['days_in_year']);
    $businessRegistrationNumber = htmlspecialchars($_POST['business_registration_number']);
    $address = htmlspecialchars($_POST['address']);
    $city = htmlspecialchars($_POST['city']);
    $province = htmlspecialchars($_POST['province']);
    $zipcode = htmlspecialchars($_POST['zipcode']);
    
    // Handle logo upload
    $logoPath = '';
    if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] == UPLOAD_ERR_OK) {
        $targetDir = '../assets/img/';
        $targetFile = $targetDir . basename($_FILES['company_logo']['name']);

        move_uploaded_file($_FILES['company_logo']['tmp_name'], $targetFile);
        $logoPath = basename($_FILES['company_logo']['name']);
    }
    
    // Handle delete logo checkbox
    if (isset($_POST['delete_logo']) && $_POST['delete_logo'] === 'on') {
        $logoPath = '';
    }

    // Update the settings in the database
    $db =db_connect(); // Ensure this function returns a PDO connection
    $stmt = $db->prepare("UPDATE settings SET 
        company_name = :company_name,
        country = :country,
        timezone = :timezone,
        currency = :currency,
        currency_words = :currency_words,
        date_format = :date_format,
        decimal_separator = :decimal_separator,
        thousand_separator = :thousand_separator,
        results_per_page = :results_per_page,
        monthly_repayment_cycle = :monthly_repayment_cycle,
        yearly_repayment_cycle = :yearly_repayment_cycle,
        days_in_month = :days_in_month,
        days_in_year = :days_in_year,
        business_registration_number = :business_registration_number,
        address = :address,
        city = :city,
        province = :province,
        zipcode = :zipcode,
        logo = :logo
        WHERE id = 1"); // Assuming you have a single record with ID 1

    $stmt->execute([
        ':company_name' => $companyName,
        ':country' => $country,
        ':timezone' => $timezone,
        ':currency' => $currency,
        ':currency_words' => $currencyWords,
        ':date_format' => $dateFormat,
        ':decimal_separator' => $decimalSeparator,
        ':thousand_separator' => $thousandSeparator,
        ':results_per_page' => $resultsPerPage,
        ':monthly_repayment_cycle' => $monthlyRepaymentCycle,
        ':yearly_repayment_cycle' => $yearlyRepaymentCycle,
        ':days_in_month' => $daysInMonth,
        ':days_in_year' => $daysInYear,
        ':business_registration_number' => $businessRegistrationNumber,
        ':address' => $address,
        ':city' => $city,
        ':province' => $province,
        ':zipcode' => $zipcode,
        ':logo' => $logoPath
    ]);

    header('Location: account_settings.php'); // Redirect back to settings page
    exit;
}
?>
