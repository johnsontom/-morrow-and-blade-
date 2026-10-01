// Closes the mobile navigation after tapping a link.
document.addEventListener('click', function (event) {
  var toggle = document.getElementById('nav-toggle');
  if (!toggle || !toggle.checked) return;
  if (event.target.closest('.site-nav a')) toggle.checked = false;
});