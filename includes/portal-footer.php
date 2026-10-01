  </main>
</div>

<footer class="portal-foot">
  <div class="page-shell">
    <p>&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?>. Signed in as <?= e($portalName) ?>.</p>
  </div>
</footer>

<script src="<?= e(asset('js/portal.js')) ?>" defer></script>
<script src="<?= e(asset('js/chat.js')) ?>" defer></script>
</body>
</html>