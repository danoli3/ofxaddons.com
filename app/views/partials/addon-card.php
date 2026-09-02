<?php
/** @var array $addon */
?>
<article class="addon-card" data-name="<?= ofx_h(strtolower($addon['name'] ?? '')) ?>" data-desc="<?= ofx_h(strtolower($addon['description'] ?? '')) ?>">
  <div class="addon-card__head">
    <img class="addon-card__avatar" src="<?= ofx_h(ofx_avatar_url($addon['user_avatar_url'] ?? null)) ?>" alt="" loading="lazy">
    <a class="addon-card__name" href="https://github.com/<?= ofx_h($addon['full_name'] ?? '') ?>" target="_blank" rel="noopener">
      <?= ofx_h($addon['name'] ?? '') ?>
    </a>
  </div>
  <p class="addon-card__desc"><?= ofx_h($addon['description'] ?: 'No description.') ?></p>
  <div class="addon-card__meta">
    <span class="addon-card__stars" title="Stars">&#9733; <?= (int)($addon['stargazers_count'] ?? 0) ?></span>
    <?php if (!empty($addon['user_login'])): ?>
      <a class="addon-card__owner" href="/contributors/<?= ofx_h(rawurlencode($addon['user_login'])) ?>">
        <?= ofx_h($addon['user_login']) ?>
      </a>
    <?php endif; ?>
  </div>
</article>
