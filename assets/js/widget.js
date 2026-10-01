/* Open and close the floating assistant. */
(function () {
  var panel = document.getElementById('assistant-widget');
  var open = document.getElementById('assistant-open');

  if (!panel || !open) { return; }

  function show() {
    panel.hidden = false;
    open.hidden = true;
    var input = panel.querySelector('input[name="question"]');
    if (input) { input.focus(); }
  }

  function hide() {
    panel.hidden = true;
    open.hidden = false;
    open.focus();
  }

  open.addEventListener('click', show);

  panel.querySelectorAll('[data-assistant-close]').forEach(function (button) {
    button.addEventListener('click', hide);
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && !panel.hidden) { hide(); }
  });
}());