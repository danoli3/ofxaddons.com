<?php
/** @var array $repos */
/** @var array $repoCategoryIds */
/** @var array $categories */
?>
<div class="page-head">
  <h1>Admin &mdash; Categorize</h1>
</div>
<p class="page-intro"><?= count($repos) ?> repo(s) waiting to be categorized (Unsorted / Incomplete).</p>

<div class="admin-toolbar">
  <div class="admin-toolbar__group">
    <span class="admin-toolbar__label">Export</span>
    <a href="/admin/export.json">JSON</a>
    <a href="/admin/export.xml">XML</a>
  </div>
  <form class="admin-toolbar__group" action="/admin/import" method="post" enctype="multipart/form-data">
    <span class="admin-toolbar__label">Import</span>
    <input type="file" name="file" accept=".json,.xml" required>
    <button type="submit">Upload</button>
  </form>
  <a class="admin-toolbar__link" href="/admin/banned">Banned addons &rarr;</a>
</div>

<table class="admin-table" id="admin-table">
  <thead>
    <tr>
      <th>Repo</th>
      <th>Description</th>
      <th>Type</th>
      <th>Categories</th>
      <th></th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($repos as $repo): ?>
      <tr class="admin-row" data-repo-id="<?= (int)$repo['id'] ?>">
        <td>
          <a href="https://github.com/<?= ofx_h($repo['full_name']) ?>" target="_blank" rel="noopener">
            <?= ofx_h($repo['name']) ?>
          </a>
          <div class="admin-row__owner"><?= ofx_h($repo['user_login'] ?? '') ?></div>
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
              <?php $selected = in_array((int)$category['id'], $repoCategoryIds[$repo['id']] ?? [], true); ?>
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
    <?php endforeach; ?>
  </tbody>
</table>
