<?php
require '../includes/init.php';

// Sign out (a form button with CSRF protection, so other sites can't sign staff out with a link)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_check();
    unset($_SESSION['staff']);
    session_regenerate_id(true);
}
redirect('login.php');
