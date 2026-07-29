<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>Inua Premium Services</title>
  <meta content="" name="description">
  <meta content="" name="keywords">

  <!-- Favicons -->
  <link href="../assets/img/favicon.png" rel="icon">
  <link href="../assets/img/apple-touch-icon.png" rel="apple-touch-icon">

  <!-- Fonts -->
  <link href="https://fonts.googleapis.com" rel="preconnect">
  <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Open+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,300;1,400;1,500;1,600;1,700;1,800&family=Montserrat:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900&display=swap" rel="stylesheet">

  <!-- Vendor CSS Files -->
  <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="../assets/vendor/aos/aos.css" rel="stylesheet">
  <link href="../assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">
  <link href="../assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">

  <!-- Main CSS File -->
  <link href="../assets/css/main.css" rel="stylesheet">

</head>

<body>

  <header id="header" class="header d-flex align-items-center sticky-top">
    <div class="container-fluid position-relative d-flex align-items-center justify-content-between">

      <a href="../index.php" class="logo d-flex align-items-center me-auto me-xl-0">
        <h1 class="sitename">Inua premium Services</h1><span>.</span>
      </a>

      <nav id="navmenu" class="navmenu d-flex align-items-center justify-content-end">
        <button id="sidebarToggle" class="mobile-nav-toggle btn btn-link text-white p-0 me-3" type="button" aria-label="Toggle sidebar" style="font-size: 24px;">
          <i class="bi bi-list"></i>
        </button>
        <ul class="d-flex align-items-center mb-0" style="gap: 20px; list-style: none; margin: 0; padding: 0;">
          <li><a href="../index.php">Home</a></li>
          <li><a href="../login.php">Login</a></li>
          <li><a href="../register.php">Register</a></li>
        </ul>
      </nav>

    </div>
  </header>

  <style>
    .sidebar {
      transition: transform 0.3s ease;
    }
    .sidebar.collapsed {
      transform: translateX(-100%);
    }
    .main.sidebar-collapsed,
    main.sidebar-collapsed,
    #mainContent.sidebar-collapsed {
      margin-left: 0 !important;
    }
  </style>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      var toggleButton = document.getElementById('sidebarToggle');
      var sidebar = document.querySelector('.sidebar');
      var mainContent = document.querySelector('.main, main, #mainContent');

      if (!toggleButton || !sidebar) {
        return;
      }

      toggleButton.addEventListener('click', function () {
        sidebar.classList.toggle('collapsed');
        if (mainContent) {
          mainContent.classList.toggle('sidebar-collapsed');
        }
      });
    });
  </script>

  <main class="main">
    <div class="page-title" data-aos="fade">
      <div class="heading">
        <div class="container">
          <div class="row d-flex justify-content-center text-center">
            <div class="col-lg-8">
