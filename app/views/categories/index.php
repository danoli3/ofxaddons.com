<?php
/** @var array $categories */
/** @var array $addonsByCategory */
?>
<div class="page-head">
  <h1>Categories</h1>
  <input type="text" class="filter-box" id="addon-filter" placeholder="Filter addons&hellip;">
</div>

<?php if (empty($categories)): ?>
  <p class="empty-state">No categorized addons yet.</p>
<?php endif; ?>

<?php foreach ($categories as $category): ?>
  <section class="category-section" id="category-<?= (int)$category['id'] ?>">
    <h2 class="category-section__title">
      <a href="/categories/<?= (int)$category['id'] ?>"><?= ofx_h($category['name']) ?></a>
      <span class="count"><?= count($addonsByCategory[$category['id']] ?? []) ?></span>
    </h2>
    <div class="addon-grid">
      <?php foreach ($addonsByCategory[$category['id']] ?? [] as $addon): ?>
        <?php ofx_addon_partial($addon); ?>
      <?php endforeach; ?>
    </div>
  </section>
<?php endforeach; ?>
