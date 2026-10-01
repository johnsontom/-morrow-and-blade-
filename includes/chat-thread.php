<?php
/**
 * Shared chat thread renderer.
 *
 * Expects:
 *   $conversation array   row from MessageDAO::byId()
 *   $messages     array   messages in ascending id order
 *   $viewer       string  'customer' or 'barber'
 */

declare(strict_types=1);

$viewer = $viewer ?? 'customer';
$otherName = $viewer === 'customer' ? $conversation['barber_name'] : $conversation['customer_name'];
$otherPhoto = $viewer === 'customer' ? staff_photo_url($conversation['barber_photo'] ?? '') : '';
$otherSubtitle = $viewer === 'customer'
    ? 'Usually replies within a few hours'
    : (string) $conversation['customer_email'];
$lastId = 0;
foreach ($messages as $message) {
    $lastId = max($lastId, (int) $message['id']);
}
?>

<section class="chat">
  <header class="chat-head">
    <a class="chat-back" href="<?= e($backUrl) ?>" aria-label="Back to inbox">&larr;</a>
    <span class="thread-avatar">
      <?php if ($otherPhoto !== ''): ?>
        <img src="<?= e($otherPhoto) ?>" alt="">
      <?php else: ?>
        <?= e(mb_substr((string) $otherName, 0, 1)) ?>
      <?php endif; ?>
    </span>
    <span class="chat-head-body">
      <strong><?= e($otherName) ?></strong>
      <span class="chat-sub"><?= e($otherSubtitle) ?></span>
    </span>
  </header>

  <div class="chat-log" id="chat-log"
       data-conversation="<?= e($conversation['id']) ?>"
       data-after="<?= $lastId ?>"
       data-api="<?= e(url('api/chat.php')) ?>"
       data-viewer="<?= e($viewer) ?>">
    <?php if ($messages === []): ?>
      <p class="chat-empty" id="chat-empty">This is the start of your conversation. Say hello and tell them what you are after.</p>
    <?php endif; ?>
    <?php foreach ($messages as $message): ?>
      <?php $mine = $message['sender_type'] === $viewer; ?>
      <div class="msg <?= $mine ? 'msg-mine' : 'msg-theirs' ?>" data-id="<?= (int) $message['id'] ?>">
        <p class="msg-body"><?= nl2br(e($message['body'])) ?></p>
        <p class="msg-time"><?= e(date('j M, H:i', strtotime((string) $message['created_at']))) ?></p>
      </div>
    <?php endforeach; ?>
  </div>

  <form class="chat-composer" method="post" action="<?= e($postUrl) ?>">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="conversation_id" value="<?= e($conversation['id']) ?>">
    <label class="visually-hidden" for="body">Message</label>
    <textarea id="body" name="body" rows="2" maxlength="<?= MessageDAO::MAX_LENGTH ?>" placeholder="Write a message&hellip;" required></textarea>
    <button type="submit" class="btn btn-primary">Send</button>
  </form>
</section>
