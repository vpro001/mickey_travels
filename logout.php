<?php
require 'includes/init.php';

// Sign out (a form button, so other sites can't sign people out with a link)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_check();
    unset($_SESSION['user'], $_SESSION['my_bookings'], $_SESSION['my_hotels'], $_SESSION['my_tours'], $_SESSION['visa_access']);
    session_regenerate_id(true);
    flash('You are signed out.');
}
redirect('index.php');
