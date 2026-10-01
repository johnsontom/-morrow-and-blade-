/* Assistant UI: works for the full page panel and the floating widget. */
(function () {
  var apiDefault = 'api/ai.php';

  function setup(root) {
    var log = root.querySelector('[data-log]');
    var form = root.querySelector('[data-form]');
    var input = form ? form.querySelector('input[name="question"]') : null;
    var api = root.getAttribute('data-api') || apiDefault;
    var history = [];
    var busy = false;

    if (!log || !form || !input) { return; }

    function bubble(html, mine) {
      var el = document.createElement('div');
      el.className = 'msg ' + (mine ? 'msg-mine' : 'msg-theirs');
      var body = document.createElement('p');
      body.className = 'msg-body';
      body.innerHTML = html;
      el.appendChild(body);
      log.appendChild(el);
      log.scrollTop = log.scrollHeight;
      return el;
    }

    function escape(text) {
      var div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }

    function ask(question) {
      if (busy || !question.trim()) { return; }
      busy = true;
      bubble(escape(question), true);
      var thinking = bubble('Thinking&hellip;', false);
      thinking.classList.add('msg-thinking');

      fetch(api, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ question: question, history: history })
      })
        .then(function (response) { return response.json(); })
        .then(function (data) {
          thinking.remove();
          if (!data.ok) { throw new Error(data.error || 'No answer'); }
          bubble(escape(data.answer).replace(/\n/g, '<br>'), false);
          history.push({ role: 'user', content: question });
          history.push({ role: 'assistant', content: data.answer });
          history = history.slice(-8);
          var engine = root.querySelector('[data-engine]');
          if (engine && data.powered_by_model) {
            engine.textContent = 'Answered by the language model using our live menu and diary.';
          }
        })
        .catch(function () {
          thinking.remove();
          bubble('Sorry, I could not reach the salon just then. Please try again, or ring us.', false);
        })
        .then(function () { busy = false; });
    }

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      var question = input.value;
      input.value = '';
      ask(question);
    });

    root.querySelectorAll('[data-suggestion]').forEach(function (button) {
      button.addEventListener('click', function () {
        ask(button.getAttribute('data-suggestion'));
      });
    });
  }

  function boot() {
    document.querySelectorAll('[data-assistant]').forEach(setup);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
}());