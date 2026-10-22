<?php
/**
 * 前台导航首页
 * 按分类展示所有启用的链接。
 */
require_once __DIR__ . '/../app/init.php';

// 拉取启用的分类
$categories = db()->query(
    'SELECT id, name FROM categories WHERE status = 1 ORDER BY sort_order ASC, id ASC'
)->fetchAll();

// 一次查询拉取所有启用的链接，按分类分组
$linksByCategory = [];
if ($categories) {
    $placeholders = implode(',', array_fill(0, count($categories), '?'));
    $ids          = array_column($categories, 'id');
    $stmt = db()->prepare(
        "SELECT id, category_id, title, url, description, icon
         FROM links
         WHERE status = 1 AND category_id IN ($placeholders)
         ORDER BY sort_order ASC, id ASC"
    );
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $link) {
        $linksByCategory[$link['category_id']][] = $link;
    }
}

include __DIR__ . '/partials/header.php';
?>

<?php $tagline = trim(setting('tagline')); ?>
<?php if ($tagline !== ''): ?>
  <div class="site-hero">
    <p class="site-tagline"><?= e($tagline) ?></p>
  </div>
<?php endif; ?>

<?php if (!$categories): ?>
  <section class="empty-state">
    <div class="empty-icon">🗂️</div>
    <p>还没有任何导航内容</p>
    <p class="empty-tip">请管理员登录后，在「后台管理 → 分类管理 / 链接管理」中添加。</p>
    <?php if (is_admin()): ?>
      <a class="btn btn-primary" href="admin/link.php">去添加链接</a>
    <?php endif; ?>
  </section>
<?php else: ?>
  <?php foreach ($categories as $cat): ?>
    <?php $links = $linksByCategory[$cat['id']] ?? []; ?>
    <?php if (!$links) { continue; } ?>
    <section class="category-block">
      <h2 class="category-title"><?= e($cat['name']) ?></h2>
      <div class="link-grid">
        <?php foreach ($links as $i => $link): ?>
          <a class="link-card" href="go.php?id=<?= (int)$link['id'] ?>" target="_blank" rel="noopener">
            <span class="link-icon hue-<?= $i % 8 ?>">
              <?= link_icon_html($link['icon'] ?? '', $link['title']) ?>
            </span>
            <span class="link-body">
              <span class="link-title"><?= e($link['title']) ?></span>
              <?php if ($link['description'] !== ''): ?>
                <span class="link-desc"><?= e($link['description']) ?></span>
              <?php endif; ?>
            </span>
            <span class="link-arrow">↗</span>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
<?php endif; ?>

<?php include __DIR__ . '/partials/footer.php'; ?>
