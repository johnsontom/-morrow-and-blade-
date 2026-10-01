<?php declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

Auth::boot();
Auth::logout();
Auth::flash('success', 'You are signed out.');
header('Location: ' . url('barber/login.php'));
exit;
