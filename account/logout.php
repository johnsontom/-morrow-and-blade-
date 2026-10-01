<?php declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

Auth::boot();
Auth::logoutCustomer();
Auth::flash('success', 'You are signed out.');
header('Location: ' . url('index.php'));
exit;
