<?php
/**
 * Customer inbox. ?barber=<id> opens (or starts) the thread with that person.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

$customer = Auth::requireCustomer();
$customerId = (string) $customer['id'];

$requestedBarber = trim((string) ($_GET['barber'] ?? ''));

if ($requestedBarber !== '') {
    $barber = BarberDAO::find($requestedBarber);

    if ($barber !== null) {
        $conversation = MessageDAO::openConversation($customerId, (string) $barber['id']);
        header('Location: ' . url('account/chat.php?c=' . urlencode((string) $conversation['id'])));
        exit;
    }
}

$threads = MessageDAO::conversationsForCustomer($customerId);
$team = BarberDAO::all();
$unread = MessageDAO::unreadForCustomer($customerId);

$pageTitle = 'Messages';
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
require __DIR__ . '/../includes/portal-header.php';
?>

<h1 class="portal-h1">Messages</h1>
<p class="lede">Ask your barber for advice on a style, a product or how to look after a cut between visits.</p>

<section class="panel">
  <h2>Start a conversation</h2>
  <form method="get" action="<?= e(url('account/messages.php')) ?>" class="inline-form">
    <label for="barber">Choose a team member</label>
    <select id="barber" name="barber" required>
      <option value="">Select&hellip;</option>
      <?php foreach ($team as $member): ?>
        <option value="<?= e($member['slug']) ?>"><?= e($member['name']) ?> &middot; <?= e($member['staff_kind_label']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary">Open chat</button>
  </form>
</section>

<section class="panel">
  <h2>Your conversations</h2>
  <?php if ($threads === []): ?>
    <p class="muted">No conversations yet.</p>
  <?php else: ?>
    <ul class="thread-list">
      <?php foreach ($threads as $thread): ?>
        <li>
          <a class="thread" href="<?= e(url('account/chat.php?c=' . urlencode((string) $thread['id']))) ?>">
            <span class="thread-avatar">
              <?php if (staff_photo_url($thread['barber_photo'] ?? null) !== ''): ?>
                <img src="<?= e(staff_photo_url($thread['barber_photo'] ?? null)) ?>" alt="">
              <?php else: ?>
                <?= e(mb_substr((string) $thread['barber_name'], 0, 1)) ?>
              <?php endif; ?>
            </span>
            <span class="thread-body">
              <span class="thread-top">
                <strong><?= e($thread['barber_name']) ?></strong>
                <span class="thread-time"><?= e($thread['last_message_at'] !== null ? date('j M', strtotime((string) $thread['last_message_at'])) : 'New') ?></span>
              </span>
              <span class="thread-preview">
                <?php if (($thread['last_sender'] ?? '') === 'customer'): ?><em>You: </em><?php endif; ?>
                <?= e(mb_strimwidth((string) ($thread['last_body'] ?? 'Say hello'), 0, 90, '...')) ?>
              </span>
            </span>
            <?php if ((int) $thread['customer_unread'] > 0): ?>
              <span class="portal-badge"><?= (int) $thread['customer_unread'] ?></span>
            <?php endif; ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/portal-footer.php'; ?>
