<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['send_email'] = 1;
$_POST['officer_email'] = 'all';
$_POST['day'] = 'all';
require 'C:\xampp\htdocs\inua_premium_services\manager\overdue_repayments.php';
