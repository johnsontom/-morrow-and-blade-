/* Branch finder: ask the browser for a location, then reorder the list. */
(function () {
  var button = document.getElementById('use-location');
  var status = document.getElementById('locator-status');
  var list = document.getElementById('branch-list');

  if (!button || !status || !list || !navigator.geolocation) {
    if (button) { button.hidden = true; }
    return;
  }

  button.addEventListener('click', function () {
    status.textContent = 'Finding you...';
    button.disabled = true;

    navigator.geolocation.getCurrentPosition(function (position) {
      var url = button.getAttribute('data-api')
        + '?lat=' + encodeURIComponent(position.coords.latitude)
        + '&lng=' + encodeURIComponent(position.coords.longitude);

      fetch(url, { headers: { Accept: 'application/json' } })
        .then(function (response) { return response.json(); })
        .then(function (data) {
          if (!data.ok) { throw new Error(data.error || 'Lookup failed'); }

          data.branches.forEach(function (branch) {
            var card = list.querySelector('.branch-card[data-slug="' + branch.slug + '"]');
            if (!card) { return; }

            var badge = card.querySelector('[data-distance]');
            badge.hidden = false;
            badge.textContent = branch.distance_miles + ' miles away';
            list.appendChild(card);
          });

          if (data.nearest) {
            status.innerHTML = 'Closest to you: <strong>' + data.nearest.name + '</strong>, '
              + data.nearest.distance_miles + ' miles away.';
          }
          button.disabled = false;
        })
        .catch(function () {
          status.textContent = 'We could not work out your distance. Pick a branch from the list below.';
          button.disabled = false;
        });
    }, function () {
      status.textContent = 'Location permission was declined. Pick a branch from the list below.';
      button.disabled = false;
    }, { maximumAge: 300000, timeout: 10000 });
  });
}());