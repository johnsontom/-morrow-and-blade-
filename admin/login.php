<?php
/**
 * Admin sign-in lives at the same door as the barbers - the role decides
 * where you land. This page exists so old links keep working.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

header('Location: ' . url('barber/login.php'), true, 302);
exit;