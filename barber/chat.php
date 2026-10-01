<?php
/**
 * A barber replying to one customer.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/staff-boot.php';

$conversationId = (string) ($_GET['c'] ?? $_POST['conversation_id'] ?? '');
$conversation = $conversationId !== '' ? MessageDAO::ownedByBarber($conversationId, $barberId) : null;

if ($conversation === null) {
    Auth::flash('error', 'That conversation could not be found.');
    header('Location: ' . url('barber/messages.php'));
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!Auth::checkCsrf($_POST['csrf_token'] ?? null)) {
        Auth::flash('error', 'Your session expired. Please send that again.');
    } else {
        MessageDAO::send($conversationId, 'barber', null, (string) $profile['id'], (string) ($_POST['body'] ?? ''));
    }

    header('Location: ' . url('barber/chat.php?c=' . urlencode($conversationId) . '#latest'));
    exit;
}

MessageDAO::markRead($conversationId, 'barber');

$conversation = MessageDAO::byId($conversationId);
$messages = MessageDAO::messages($conversationId);

$customerRecord = AccountDAO::findCustomerById((string) $conversation['customer_id']);
$recent = $customerRecord !== null ? DB::all(
    'SELECT a.*, s.name AS service_name FROM appointments a JOIN services s ON s.id = a.service_id
     WHERE a.customer_id = ? ORDER BY a.starts_at DESC LIMIT 3',
    [$conversation['customer_id']]
) : [];

$pageTitle = 'Chat with ' . $conversation['customer_name'];
require __DIR__ . '/../includes/portal-header.php';
?>

<?php if ($recent !== []): ?>
  <section class="panel panel-slim">
    <p class="eyebrow">Customer history</p>
    <ul class="record-list">
      <?php foreach ($recent as $appointment): ?>
        <li class="record">
          <div>
            <p class="record-title"><?= e($appointment['service_name']) ?></p>
            <p class="record-meta"><?= e(date('D j M Y, H:i', strtotime((string) $appointment['starts_at']))) ?> &middot; <?= e(str_replace('_', ' ', $appointment['status'])) ?></p>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>

<?php
$viewer = 'barber';
$postUrl = url('barber/chat.php');
$backUrl = url('barber/messages.php');
require __DIR__ . '/../includes/chat-thread.php';
require __DIR__ . '/../includes/portal-footer.php';