/*
 * Branch awareness.
 *
 * The site shows one salon's team. This script works out which one: if the
 * visitor has already granted location access we resolve it silently,
 * otherwise they can press the button. Either way the answer travels back
 * as ?branch=<slug>, and the server remembers it in a cookie.
 *
 * Once we have located someone we also know how far away their salon is, so
 * that label is painted next to the salon name.
 */
(function () {
  var DISTANCE_KEY = 'mb_branch_distance';

  var button = document.getElementById('branch-locate');
  var status = document.getElementById('branch-locate-status');
  var picker = document.getElementById('branch-picker');
  var distance = document.getElementById('branch-distance');
  var form = picker ? picker.form : null;

  function currentSlug() {
    return picker ? picker.value : '';
  }

  function withBranch(slug) {
    var url = new URL(window.location.href);
    url.searchParams.set('branch', slug);
    return url.toString();
  }

  function say(message) {
    if (status) { status.textContent = message; }
  }

  function markTried() {
    try { window.sessionStorage.setItem('mb_branch_tried', '1'); } catch (error) { /* private mode */ }
  }

  function triedAlready() {
    try { return window.sessionStorage.getItem('mb_branch_tried') === '1'; } catch (error) { return false; }
  }

  function rememberDistance(value) {
    try { window.sessionStorage.setItem(DISTANCE_KEY, JSON.stringify(value)); } catch (error) { /* private mode */ }
  }

  function storedDistance() {
    try {
      var raw = window.sessionStorage.getItem(DISTANCE_KEY);
      return raw ? JSON.parse(raw) : null;
    } catch (error) { return null; }
  }

  function forgetDistance() {
    try { window.sessionStorage.removeItem(DISTANCE_KEY); } catch (error) { /* private mode */ }
  }

  function miles(miles) {
    var value = miles >= 10 ? Math.round(miles) : Math.round(miles * 10) / 10;
    return value + (value === 1 ? ' mile' : ' miles') + ' away';
  }

  // Only label the salon we actually measured. If the visitor has since
  // switched branch by hand the stale number is simply dropped.
  function paintDistance() {
    if (!distance) { return; }

    var stored = storedDistance();

    if (!stored || stored.slug !== currentSlug() || typeof stored.miles !== 'number') {
      distance.hidden = true;
      distance.textContent = '';
      return;
    }

    distance.textContent = '\u00b7 ' + miles(stored.miles);
    distance.hidden = false;
  }

  function locate(onFound) {
    navigator.geolocation.getCurrentPosition(function (position) {
      var url = button.getAttribute('data-api')
        + '?lat=' + encodeURIComponent(position.coords.latitude)
        + '&lng=' + encodeURIComponent(position.coords.longitude);

      fetch(url, { headers: { Accept: 'application/json' } })
        .then(function (response) { return response.json(); })
        .then(function (data) {
          if (!data.ok || !data.nearest) { throw new Error('no match'); }

          markTried();
          onFound(data.nearest);
        })
        .catch(function () {
          say('We could not match your location. Pick a salon from the list instead.');
          if (button) { button.disabled = false; }
        });
    }, function () {
      say('Location was declined. Pick your salon from the list instead.');
      if (button) { button.disabled = false; }
    }, { maximumAge: 600000, timeout: 10000 });
  }

  paintDistance();

  if (button && navigator.geolocation) {
    button.addEventListener('click', function () {
      button.disabled = true;
      say('Finding your nearest salon...');

      locate(function (branch) {
        var away = typeof branch.distance_miles === 'number' ? miles(branch.distance_miles) : 'close by';
        say('Closest is ' + branch.name + ', ' + away + '. Taking you there...');
        rememberDistance({ slug: branch.slug, miles: branch.distance_miles });
        window.location.href = withBranch(branch.slug);
      });
    });

    // Nobody should be shown a salon in another city without being asked, so
    // only reach for a location the browser has already agreed to share.
    if (button.getAttribute('data-chosen') !== '1' && !triedAlready()
      && navigator.permissions && navigator.permissions.query) {
      navigator.permissions.query({ name: 'geolocation' }).then(function (permission) {
        if (permission.state === 'granted') { button.click(); }
      }).catch(function () { /* not supported, leave the button to the visitor */ });
    }
  } else if (button) {
    button.hidden = true;
  }

  // With scripting on, the picker is enough - the Switch button is the
  // no-JavaScript fallback.
  if (picker) {
    picker.addEventListener('change', function () {
      // A hand-picked salon has no measured distance.
      forgetDistance();
      window.location.href = withBranch(picker.value);
    });

    if (form) {
      var submit = form.querySelector('button[type="submit"]');
      if (submit) { submit.hidden = true; }
    }
  }
}());