<?php
/**
 * 后台概览：关键指标 + 7 日点击趋势 + 点击排行
 */
require_once __DIR__ . '/../../app/init.php';
require_admin();

$db = db();
ensure_click_daily_table();

// 基础计数
$linkCount   = (int)$db->query('SELECT COUNT(*) FROM links')->fetchColumn();
$catCount    = (int)$db->query('SELECT COUNT(*) FROM categories')->fetchColumn();
$userCount   = (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
$totalClicks = (int)$db->query('SELECT COALESCE(SUM(count), 0) FROM click_daily')->fetchColumn();
$todayClicks = (int)$db->query(
    'SELECT COALESCE(SUM(count), 0) FROM click_daily WHERE click_date >= CURDATE()'
)->fetchColumn();

// 近 7 日点击趋势（聚合表，避免大数据量下全表扫描；补齐没有点击的日期）
$trendRows = $db->query(
    'SELECT click_date AS d, SUM(count) AS c
     FROM click_daily
     WHERE click_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     GROUP BY click_date'
)->fetchAll();
$trend = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $trend[$date] = 0;
}
foreach ($trendRows as $row) {
    $trend[$row['d']] = (int)$row['c'];
}
$trendMax = max($trend) > 0 ? max($trend) : 1;

// 点击排行 TOP 10（聚合表）
$topLinks = $db->query(
    'SELECT l.title, l.url, COALESCE(SUM(cd.count), 0) AS c
     FROM links l
     LEFT JOIN click_daily cd ON cd.link_id = l.id
     GROUP BY l.id
     ORDER BY c DESC, l.id ASC
     LIMIT 10'
)->fetchAll();

$pageTitle = '概览';
$active    = 'index';
include __DIR__ . '/partials/header.php';
?>

<h1 class="page-title">后台概览</h1>

<div class="stat-grid">
  <div class="stat-card"><div class="stat-num"><?= $linkCount ?></div><div class="stat-label">链接总数</div></div>
  <div class="stat-card"><div class="stat-num"><?= $catCount ?></div><div class="stat-label">分类数</div></div>
  <div class="stat-card"><div class="stat-num"><?= $userCount ?></div><div class="stat-label">注册用户</div></div>
  <div class="stat-card"><div class="stat-num"><?= $todayClicks ?></div><div class="stat-label">今日点击</div></div>
  <div class="stat-card"><div class="stat-num"><?= $totalClicks ?></div><div class="stat-label">累计点击</div></div>
</div>

<section class="panel">
  <h2 class="panel-title">近 7 日点击趋势</h2>
  <div class="bar-chart">
    <?php foreach ($trend as $date => $count): ?>
      <div class="bar-item">
        <span class="bar-value"><?= $count ?></span>
        <div class="bar" style="height: <?= (int)round($count / $trendMax * 100) ?>%"></div>
        <span class="bar-label"><?= e(substr($date, 5)) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="panel">
  <h2 class="panel-title">点击排行 TOP 10</h2>
  <?php if (!$topLinks): ?>
    <p class="empty-tip">暂无点击数据，去前台点几个链接试试吧。</p>
  <?php else: ?>
    <table class="table">
      <thead><tr><th>#</th><th>链接</th><th>地址</th><th>点击次数</th></tr></thead>
      <tbody>
      <?php foreach ($topLinks as $i => $link): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><?= e($link['title']) ?></td>
          <td class="cell-muted"><?= e($link['url']) ?></td>
          <td><?= (int)$link['c'] ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<?php include __DIR__ . '/partials/footer.php'; ?>
