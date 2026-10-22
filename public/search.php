<?php
/**
 * 站内搜索
 * 按标题 / 描述 / 链接地址模糊匹配启用的链接。
 */
require_once __DIR__ . '/../app/init.php';

$q        = trim((string)($_GET['q'] ?? ''));
$results  = [];
$keywords = [];

if ($q !== '') {
    // 按空格拆分关键词，全部关键词都要命中（AND 关系）
    $keywords = array_values(array_filter(array_map('trim', preg_split('/[\s,，]+/u', $q) ?: [])));
    if ($keywords) {
        $conditions = [];
        $params     = [];
        foreach ($keywords as $word) {
            $like       = '%' . $word . '%';
            $conditions[] = '(l.title LIKE ? OR l.description LIKE ? OR l.url LIKE ?)';
            array_push($params, $like, $like, $like);
        }
        $sql = 'SELECT l.id, l.title, l.url, l.description, l.icon, c.name AS category_name
                FROM links l
                JOIN categories c ON c.id = l.category_id
                WHERE l.status = 1 AND ' . implode(' AND ', $conditions) . '
                ORDER BY l.sort_order ASC, l.id ASC';
        $stmt   = db()->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll();
    }
}

$pageTitle = $q !== '' ? '搜索：' . $q : '搜索';
include __DIR__ . '/partials/header.php';
?>

<section class="search-page">
  <h1 class="page-title">
    <?= $q !== '' ? '「' . e($q) . '」的搜索结果' : '站内搜索' ?>
  </h1>

  <?php if ($q === ''): ?>
    <div class="empty-state">
      <div class="empty-icon">🔍</div>
      <p>输入关键词，查找你想要的网站</p>
    </div>
  <?php elseif (!$keywords): ?>
    <div class="empty-state">
      <div class="empty-icon">🔍</div>
      <p>请输入有效的搜索关键词</p>
    </div>
  <?php elseif (!$results): ?>
    <div class="empty-state">
      <div class="empty-icon">🤷</div>
      <p>没有找到与「<?= e($q) ?>」相关的链接</p>
      <p class="empty-tip">换个关键词试试，或联系管理员添加。</p>
    </div>
  <?php else: ?>
    <p class="result-count">共找到 <?= count($results) ?> 个结果</p>
    <div class="result-list">
      <?php foreach ($results as $link): ?>
        <a class="result-item" href="go.php?id=<?= (int)$link['id'] ?>" target="_blank" rel="noopener">
          <span class="link-icon">
            <?= link_icon_html($link['icon'] ?? '', $link['title']) ?>
          </span>
          <span class="link-body">
            <span class="link-title"><?= e($link['title']) ?></span>
            <span class="link-desc">
              <?= $link['description'] !== '' ? e($link['description']) . ' · ' : '' ?>
              <em><?= e($link['category_name']) ?></em>
            </span>
          </span>
          <span class="link-arrow">↗</span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php include __DIR__ . '/partials/footer.php'; ?>
