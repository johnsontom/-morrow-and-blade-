/* Chat window: keeps the thread fresh by polling for new messages. */
(function () {
  var log = document.getElementById('chat-log');
  if (!log) { return; }

  var api = log.getAttribute('data-api');
  var conversation = log.getAttribute('data-conversation');
  var viewer = log.getAttribute('data-viewer') || 'customer';
  var after = parseInt(log.getAttribute('data-after') || '0', 10);
  var empty = document.getElementById('chat-empty');
  var timer = null;

  function jumpToEnd() {
    log.scrollTop = log.scrollHeight;
  }

  jumpToEnd();

  function append(message) {
    var el = document.createElement('div');
    el.className = 'msg ' + (message.mine ? 'msg-mine' : 'msg-theirs');
    el.setAttribute('data-id', message.id);

    var body = document.createElement('p');
    body.className = 'msg-body';
    body.innerHTML = message.body
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/\n/g, '<br>');

    var time = document.createElement('p');
    time.className = 'msg-time';
    time.textContent = message.time;

    el.appendChild(body);
    el.appendChild(time);
    log.appendChild(el);
  }

  function poll() {
    fetch(api + '?c=' + encodeURIComponent(conversation) + '&after=' + after, {
      headers: { Accept: 'application/json' }
    })
      .then(function (response) { return response.json(); })
      .then(function (data) {
        if (!data.ok || !data.messages || !data.messages.length) { return; }
        if (empty) { empty.remove(); empty = null; }
        data.messages.forEach(function (message) {
          append(message);
          after = Math.max(after, message.id);
        });
        log.setAttribute('data-after', String(after));
        jumpToEnd();
      })
      .catch(function () { /* stay quiet, we will try again */ });
  }

  function start() {
    if (timer) { return; }
    timer = window.setInterval(poll, 5000);
  }

  function stop() {
    window.clearInterval(timer);
    timer = null;
  }

  document.addEventListener('visibilitychange', function () {
    document.hidden ? stop() : start();
  });

  log.closest('form')?.addEventListener('submit', jumpToEnd);
  start();
}());