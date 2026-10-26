  </main>
</div>
<nav class="bottom-nav" aria-label="Quick navigation">
  <?php foreach ($bottom as $file): [$label, $ico] = $nav[$file]; ?>
    <a href="<?= $file ?>" class="<?= $active === $file ? 'active' : '' ?>"><span class="bn-ico"><?= icon($ico) ?></span><?= $label ?></a>
  <?php endforeach; ?>
  <button type="button" class="js-menu"><span class="bn-ico"><?= icon('menu') ?></span>More</button>
</nav>
<script src="assets/js/app.js"></script>
</body>
</html>
