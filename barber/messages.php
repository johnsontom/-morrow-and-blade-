<?php
/**
 * The barber's inbox: every customer who has messaged them.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/staff-boot.php';

$conversationId = trim((string) ($_GET['c'] ?? ''));

if ($conversationId !== '' && MessageDAO::ownedByBarber($conversationId, $barberId) !== null) {
    header('Location: ' . url('barber/chat.php?c=' . urlencode($conversationId)));
    exit;
}

$threads = MessageDAO::conversationsForBarber($barberId);

$pageTitle = 'Messages';
require __DIR__ . '/../includes/portal-header.php';
?>

<h1 class="portal-h1">Messages</h1>
<p class="lede">Customers asking for advice. Replying is part of the service - a quick answer wins a rebooking.</p>

<section class="panel">
  <?php if ($threads === []): ?>
    <p class="muted">No messages yet. Customers can start a chat from your public profile.</p>
  <?php else: ?>
    <ul class="thread-list">
      <?php foreach ($threads as $thread): ?>
        <li>
          <a class="thread" href="<?= e(url('barber/chat.php?c=' . urlencode((string) $thread['id']))) ?>">
            <span class="thread-avatar"><?= e(mb_substr((string) $thread['customer_name'], 0, 1)) ?></span>
            <span class="thread-body">
              <span class="thread-top">
                <strong><?= e($thread['customer_name']) ?></strong>
                <span class="thread-time"><?= e($thread['last_message_at'] !== null ? date('j M', strtotime((string) $thread['last_message_at'])) : 'New') ?></span>
              </span>
              <span class="thread-preview">
                <?php if (($thread['last_sender'] ?? '') === 'barber'): ?><em>You: </em><?php endif; ?>
                <?= e(mb_strimwidth((string) ($thread['last_body'] ?? 'New conversation'), 0, 90, '...')) ?>
              </span>
            </span>
            <?php if ((int) $thread['barber_unread'] > 0): ?>
              <span class="portal-badge"><?= (int) $thread['barber_unread'] ?></span>
            <?php endif; ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/portal-footer.php'; ?>