/* Register the service worker so the site can be installed as an app. */
(function () {
  if (!('serviceWorker' in navigator)) { return; }

  window.addEventListener('load', function () {
    navigator.serviceWorker.register('sw.js').catch(function () {
      /* offline support is a bonus, not a requirement */
    });
  });
}());