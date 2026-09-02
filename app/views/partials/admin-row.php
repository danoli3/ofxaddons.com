<?php
/** @var array $repo */
/** @var array $categories */
/** @var array $selectedCategoryIds */
?>
<tr class="admin-row" data-repo-id="<?= (int)$repo['id'] ?>">
  <td>
    <a href="https://github.com/<?= ofx_h($repo['full_name']) ?>" target="_blank" rel="noopener">
      <?= ofx_h($repo['name']) ?>
    </a>
    <div class="admin-row__owner"><?= ofx_h($repo['user_login'] ?? '') ?></div>
    <a class="admin-row__url" href="https://github.com/<?= ofx_h($repo['full_name']) ?>" target="_blank" rel="noopener">
      github.com/<?= ofx_h($repo['full_name']) ?>
    </a>
  </td>
  <td class="admin-row__desc"><?= ofx_h($repo['description'] ?: '') ?></td>
  <td>
    <select class="admin-row__type">
      <?php foreach (OFX_REPO_TYPES as $type): ?>
        <option value="<?= ofx_h($type) ?>" <?= $repo['type'] === $type ? 'selected' : '' ?>>
          <?= ofx_h($type) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </td>
  <td>
    <select class="admin-row__categories" multiple size="4">
      <?php foreach ($categories as $category): ?>
        <?php $selected = in_array((int)$category['id'], $selectedCategoryIds, true); ?>
        <option value="<?= (int)$category['id'] ?>" <?= $selected ? 'selected' : '' ?>>
          <?= ofx_h($category['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </td>
  <td class="admin-row__actions">
    <button type="button" class="admin-row__save">Save</button>
    <button type="button" class="admin-row__ban" title="Not really an openFrameworks addon">Ban</button>
    <span class="admin-row__status"></span>
  </td>
</tr>
