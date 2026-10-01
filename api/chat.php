<?php
/**
 * Polling endpoint for the chat window. Returns new messages in a thread.
 *
 * The viewer is worked out from the session, so a customer can only read
 * their own threads and a barber only theirs.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

Auth::boot();

$conversationId = (string) ($_GET['c'] ?? '');
$after = max(0, (int) ($_GET['after'] ?? 0));

if ($conversationId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing conversation.']);
    exit;
}

$viewer = null;
$conversation = null;
$customer = Auth::customer();

if ($customer !== null) {
    $conversation = MessageDAO::ownedByCustomer($conversationId, (string) $customer['id']);
    $viewer = 'customer';
}

if ($conversation === null) {
    $profile = Auth::profile();

    if ($profile !== null && $profile['barber_id'] !== null) {
        $conversation = MessageDAO::ownedByBarber($conversationId, (string) $profile['barber_id']);
        $viewer = 'barber';
    }
}

if ($conversation === null || $viewer === null) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Not your conversation.']);
    exit;
}

$rows = MessageDAO::messages($conversationId, $after);
$messages = [];

foreach ($rows as $row) {
    $messages[] = [
        'id' => (int) $row['id'],
        'sender' => $row['sender_type'],
        'mine' => $row['sender_type'] === $viewer,
        'body' => (string) $row['body'],
        'time' => date('j M, H:i', strtotime((string) $row['created_at'])),
    ];
}

if ($rows !== []) {
    MessageDAO::markRead($conversationId, $viewer);
}

echo json_encode(['ok' => true, 'messages' => $messages, 'last' => $messages === [] ? $after : $messages[count($messages) - 1]['id']]);