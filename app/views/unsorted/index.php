<?php
/** @var array $repos */
?>
<div class="page-head">
  <h1>Unsorted</h1>
  <input type="text" class="filter-box" id="addon-filter" placeholder="Filter addons&hellip;">
</div>
<p class="page-intro">Addons the crawler has found on GitHub but nobody has categorized yet.</p>

<?php if (empty($repos)): ?>
  <p class="empty-state">Nothing unsorted right now.</p>
<?php endif; ?>

<div class="addon-grid">
  <?php foreach ($repos as $addon): ?>
    <?php ofx_addon_partial($addon); ?>
  <?php endforeach; ?>
</div>
