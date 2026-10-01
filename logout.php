<?php
require __DIR__ . '/includes/bootstrap.php';
verify_post();
$_SESSION = [];
session_regenerate_id(true);
flash('success', 'You have signed out.');
redirect('login.php');