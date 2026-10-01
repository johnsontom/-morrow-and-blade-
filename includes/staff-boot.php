<?php
/**
 * Shared bootstrap for every /barber page.
 *
 * Signs the visitor in, resolves the barber record their login is attached to
 * and builds the portal navigation. A login without a linked barber record
 * (the owner, for example) is sent to the admin area instead.
 *
 * Sets: $profile, $barberId, $barber, $staffStats, $portal
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

$profile = Auth::requireProfile();

if ($profile['barber_id'] === null) {
    header('Location: ' . url('admin/index.php'));
    exit;
}

$barberId = (string) $profile['barber_id'];
$barber = StaffDAO::barber($barberId);

if ($barber === null) {
    Auth::logout();
    http_response_code(500);
    exit('This login is not linked to a team member any more. Please ask the owner to re-issue it.');
}

$staffStats = StaffDAO::stats($barberId);

$portal = [
    'kind' => 'staff',
    'name' => $barber['name'],
    'root' => 'barber',
    'subtitle' => 'Barber portal',
    'badges' => ['messages.php' => $staffStats['unread']],
    'nav' => [
        'index.php' => 'Today',
        'appointments.php' => 'Appointments',
        'messages.php' => 'Messages',
        'schedule.php' => 'My rota',
        'status.php' => 'My status',
        'profile.php' => 'My profile',
    ],
];