<?php
/**
 * index.php — Entry point for Tiptop Apparel Payroll System (PHPDesktop)
 * Redirects to login page on first visit, or to the appropriate dashboard if already logged in.
 */
session_start();

if (isset($_SESSION['Login_Type'])) {
    if ($_SESSION['Login_Type'] === 'admin') {
        header('Location: a_dashboard.php');
    } else {
        header('Location: e_dashboard.php');
    }
    exit;
}

header('Location: login.php');
exit;