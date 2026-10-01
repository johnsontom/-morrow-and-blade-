<?php
/**
 * The assistant. Answers questions about prices, the team, availability,
 * styles and where to find us - grounded in the live database.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

$pageTitle = 'Ask us anything';
$pageDescription = 'Prices, availability, styles, opening hours and directions - answered instantly from our live diary.';
require __DIR__ . '/includes/header.php';
?>

<section class="section">
  <div class="page-shell assistant-page">
    <p class="eyebrow">Front desk assistant</p>
    <h1>Ask us anything</h1>
    <p class="lede">
      Prices, who is free today, how long a treatment takes, where we are - the assistant reads our
      live diary and menu, so the answers are the same ones the salon would give you.
    </p>

    <div class="assistant assistant-large" data-assistant data-api="<?= e(url('api/ai.php')) ?>">
      <div class="assistant-log" data-log aria-live="polite">
        <div class="msg msg-theirs">
          <p class="msg-body">Hello. Ask me about a treatment, a price, today's availability or finding the salon.</p>
        </div>
      </div>

      <form class="assistant-form" data-form>
        <label class="visually-hidden" for="assistant-question">Your question</label>
        <input id="assistant-question" name="question" type="text" maxlength="500" autocomplete="off"
               placeholder="How much is a skin fade?" required>
        <button type="submit" class="btn btn-primary">Ask</button>
      </form>

      <div class="assistant-hints">
        <?php foreach (Grounding::suggestions() as $suggestion): ?>
          <button type="button" class="chip chip-button" data-suggestion="<?= e($suggestion) ?>"><?= e($suggestion) ?></button>
        <?php endforeach; ?>
      </div>

      <p class="assistant-foot" data-engine>
        <?php if (Assistant::configured()): ?>
          Answers are written by a language model using our live data. For medical questions, please ring the salon.
        <?php else: ?>
          Running the built-in lookup engine. Add an API key in <code>config/local.php</code> to enable model-written answers.
        <?php endif; ?>
      </p>
    </div>
  </div>
</section>

<script src="<?= e(asset('js/assistant.js')) ?>" defer></script>
<?php require __DIR__ . '/includes/footer.php'; ?>