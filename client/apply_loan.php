<?php
include '../includes/header.php'; // Include header file
include '../includes/functions.php'; // Include functions file

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate and apply loan using the apply_loan function from functions.php
    if (isset($_POST['client_id'], $_POST['amount'], $_POST['term'], $_POST['interest_rate'])) {
        // Sanitize inputs before using them
        $client_id = $_POST['client_id'];
        $amount = $_POST['amount'];
        $term = $_POST['term'];
        $interest_rate = $_POST['interest_rate'];

        // Apply loan
        apply_loan($client_id, $amount, $term, $interest_rate);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <!-- Meta tags, title, links to CSS files, etc. -->
</head>
<body class="starter-page-page">

  <header id="header" class="header d-flex align-items-center sticky-top">
    <!-- Header content -->
  </header>

  <main class="main">
    <!-- Page content -->

    <!-- Loan Application Section -->
    <section id="loan-application" class="section">
      <div class="container">
        <h2>Apply for Loan</h2>
        <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
          <input type="hidden" name="client_id" value="1"> <!-- Change this to dynamically fetch client_id -->
          <input type="number" name="amount" placeholder="Loan Amount" required>
          <input type="number" name="term" placeholder="Loan Term (months)" required>
          <input type="number" name="interest_rate" placeholder="Interest Rate (%)" required>
          <button type="submit">Apply</button>
        </form>
      </div>
    </section><!-- /Loan Application Section -->

  </main>

  <?php include '../includes/footer.php'; // Include footer file ?>
</body>
</html>
