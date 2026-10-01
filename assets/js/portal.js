/* Small portal helpers: enable/disable the time fields for a whole day off. */
(function () {
  var checkbox = document.querySelector('input[name="full_day"]');

  if (!checkbox) { return; }

  var start = document.getElementById('exception_start');
  var end = document.getElementById('exception_end');

  function sync() {
    var disabled = checkbox.checked;
    if (start) { start.disabled = disabled; }
    if (end) { end.disabled = disabled; }
  }

  checkbox.addEventListener('change', sync);
  sync();
}());