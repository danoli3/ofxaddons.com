<?php
/** @var array $category */
/** @var array $addons */
?>
<div class="page-head">
  <h1><?= ofx_h($category['name']) ?></h1>
  <input type="text" class="filter-box" id="addon-filter" placeholder="Filter addons&hellip;">
</div>

<?php if (empty($addons)): ?>
  <p class="empty-state">No addons in this category yet.</p>
<?php endif; ?>

<div class="addon-grid">
  <?php foreach ($addons as $addon): ?>
    <?php ofx_addon_partial($addon); ?>
  <?php endforeach; ?>
</div>
