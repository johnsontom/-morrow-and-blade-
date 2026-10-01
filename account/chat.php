<?php
/**
 * A single customer/barber thread.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

$customer = Auth::requireCustomer();
$customerId = (string) $customer['id'];
$conversationId = (string) ($_GET['c'] ?? $_POST['conversation_id'] ?? '');

$conversation = $conversationId !== '' ? MessageDAO::ownedByCustomer($conversationId, $customerId) : null;

if ($conversation === null) {
    Auth::flash('error', 'That conversation could not be found.');
    header('Location: ' . url('account/messages.php'));
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!Auth::checkCsrf($_POST['csrf_token'] ?? null)) {
        Auth::flash('error', 'Your session expired. Please send that again.');
    } else {
        MessageDAO::send($conversationId, 'customer', $customerId, null, (string) ($_POST['body'] ?? ''));
    }

    header('Location: ' . url('account/chat.php?c=' . urlencode($conversationId) . '#latest'));
    exit;
}

MessageDAO::markRead($conversationId, 'customer');

$conversation = MessageDAO::byId($conversationId);
$messages = MessageDAO::messages($conversationId);
$unread = MessageDAO::unreadForCustomer($customerId);

$pageTitle = 'Chat with ' . $conversation['barber_name'];
$portal = [
    'kind' => 'customer',
    'name' => $customer['name'],
    'root' => 'account',
    'subtitle' => 'My account',
    'badges' => ['messages.php' => $unread],
    'nav' => [
        'index.php' => 'Overview',
        'appointments.php' => 'My appointments',
        'messages.php' => 'Messages',
        'profile.php' => 'My details',
    ],
];
$viewer = 'customer';
$postUrl = url('account/chat.php');
$backUrl = url('account/messages.php');

require __DIR__ . '/../includes/portal-header.php';
require __DIR__ . '/../includes/chat-thread.php';
require __DIR__ . '/../includes/portal-footer.php';