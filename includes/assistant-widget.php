<?php
/**
 * Floating assistant widget, included by the public footer on every page.
 */

declare(strict_types=1);
?>
<div class="assistant-widget" id="assistant-widget" hidden>
  <div class="assistant-widget-head">
    <span>
      <strong>Ask Morrow &amp; Blade</strong>
      <em>Prices, availability, directions</em>
    </span>
    <button type="button" class="assistant-close" data-assistant-close aria-label="Close assistant">&times;</button>
  </div>

  <div class="assistant assistant-compact" data-assistant data-api="<?= e(url('api/ai.php')) ?>">
    <div class="assistant-log" data-log aria-live="polite">
      <div class="msg msg-theirs">
        <p class="msg-body">Hello. Ask me about a treatment, a price or today's availability.</p>
      </div>
    </div>

    <form class="assistant-form" data-form>
      <label class="visually-hidden" for="widget-question">Your question</label>
      <input id="widget-question" name="question" type="text" maxlength="500" autocomplete="off" placeholder="Ask a question&hellip;" required>
      <button type="submit" class="btn btn-primary btn-small">Ask</button>
    </form>
  </div>
</div>

<button type="button" class="assistant-fab" id="assistant-open" aria-controls="assistant-widget">Ask us</button>
<script src="<?= e(asset('js/assistant.js')) ?>" defer></script>
<script src="<?= e(asset('js/widget.js')) ?>" defer></script>