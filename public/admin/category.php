<?php
/**
 * 后台 - 分类管理：列表 / 新增 / 编辑 / 启停 / 删除
 */
require_once __DIR__ . '/../../app/init.php';
require_admin();

$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');
    $id     = (int)($_POST['id'] ?? 0);
    $name   = trim((string)($_POST['name'] ?? ''));
    $order  = (int)($_POST['sort_order'] ?? 0);

    if ($name === '' || str_length($name) > 100) {
        flash_set('error', '分类名称不能为空且不超过 100 字。');
    } else {
        if ($action === 'add') {
            $db->prepare('INSERT INTO categories (name, sort_order, status) VALUES (?, ?, 1)')
                ->execute([$name, $order]);
            flash_set('success', '分类「' . $name . '」已添加。');
        } elseif ($action === 'edit' && $id > 0) {
            $db->prepare('UPDATE categories SET name = ?, sort_order = ? WHERE id = ?')
                ->execute([$name, $order, $id]);
            flash_set('success', '分类已更新。');
        } elseif ($action === 'toggle' && $id > 0) {
            $db->prepare('UPDATE categories SET status = 1 - status WHERE id = ?')->execute([$id]);
            flash_set('success', '分类状态已切换。');
        } elseif ($action === 'delete' && $id > 0) {
            $count = $db->prepare('SELECT COUNT(*) FROM links WHERE category_id = ?');
            $count->execute([$id]);
            if ((int)$count->fetchColumn() > 0) {
                flash_set('error', '该分类下还有链接，请先移动或删除这些链接。');
            } else {
                $db->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
                flash_set('success', '分类已删除。');
            }
        } else {
            flash_set('error', '未知操作。');
        }
    }
    redirect('category.php');
}

$categories = $db->query(
    'SELECT c.*, (SELECT COUNT(*) FROM links l WHERE l.category_id = c.id) AS link_count
     FROM categories c
     ORDER BY c.sort_order ASC, c.id ASC'
)->fetchAll();

$pageTitle = '分类管理';
$active    = 'category';
include __DIR__ . '/partials/header.php';
?>

<h1 class="page-title">分类管理</h1>

<div class="panel">
  <h2 class="panel-title">新增分类</h2>
  <form class="form-inline" method="post" action="category.php">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add">
    <input class="input" type="text" name="name" placeholder="分类名称（如：常用工具）" required maxlength="100">
    <input class="input input-sm" type="number" name="sort_order" value="0" min="0" placeholder="排序">
    <button class="btn btn-primary" type="submit">添加</button>
  </form>
</div>

<div class="panel">
  <h2 class="panel-title">分类列表</h2>
  <?php if (!$categories): ?>
    <p class="empty-tip">还没有分类，先在上方添加一个吧。</p>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr><th>ID</th><th>名称</th><th>链接数</th><th>排序</th><th>状态</th><th>操作</th></tr>
      </thead>
      <tbody>
      <?php foreach ($categories as $cat): ?>
        <tr>
          <td><?= (int)$cat['id'] ?></td>
          <td>
            <form class="form-inline" method="post" action="category.php">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="edit">
              <input type="hidden" name="id" value="<?= (int)$cat['id'] ?>">
              <input class="input input-sm" type="text" name="name" value="<?= e($cat['name']) ?>" required maxlength="100">
              <input class="input input-sm input-num" type="number" name="sort_order" value="<?= (int)$cat['sort_order'] ?>" min="0">
              <button class="btn btn-outline btn-sm" type="submit">保存</button>
            </form>
          </td>
          <td><?= (int)$cat['link_count'] ?></td>
          <td class="cell-muted"><?= (int)$cat['sort_order'] ?></td>
          <td>
            <?php if ((int)$cat['status'] === 1): ?>
              <span class="badge badge-on">启用</span>
            <?php else: ?>
              <span class="badge badge-off">停用</span>
            <?php endif; ?>
          </td>
          <td class="cell-actions">
            <form class="form-inline" method="post" action="category.php">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= (int)$cat['id'] ?>">
              <button class="btn btn-outline btn-sm" type="submit"><?= (int)$cat['status'] === 1 ? '停用' : '启用' ?></button>
            </form>
            <form class="form-inline" method="post" action="category.php" data-confirm="确定删除分类「<?= e($cat['name']) ?>」？">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$cat['id'] ?>">
              <button class="btn btn-danger btn-sm" type="submit">删除</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
