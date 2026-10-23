<?php
/**
 * 后台 - 访问统计：汇总指标 / 30 日趋势 / 分类分布 / 点击排行 / 最近点击
 */
require_once __DIR__ . '/../../app/init.php';
require_admin();

$db = db();
ensure_click_daily_table();

// 汇总指标（聚合表；独立访客 IP 仍需明细表去重）
$todayClicks = (int)$db->query('SELECT COALESCE(SUM(count), 0) FROM click_daily WHERE click_date >= CURDATE()')->fetchColumn();
$weekClicks  = (int)$db->query('SELECT COALESCE(SUM(count), 0) FROM click_daily WHERE click_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)')->fetchColumn();
$totalClicks = (int)$db->query('SELECT COALESCE(SUM(count), 0) FROM click_daily')->fetchColumn();
$uniqueIps   = (int)$db->query('SELECT COUNT(DISTINCT ip) FROM click_logs WHERE ip <> \'\'')->fetchColumn();

// 近 30 日趋势（聚合表）
$trendRows = $db->query(
    'SELECT click_date AS d, SUM(count) AS c
     FROM click_daily
     WHERE click_date >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
     GROUP BY click_date'
)->fetchAll();
$trend = [];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $trend[$date] = 0;
}
foreach ($trendRows as $row) {
    $trend[$row['d']] = (int)$row['c'];
}
$trendMax = max($trend) > 0 ? max($trend) : 1;

// 分类点击分布（聚合表）
$catStats = $db->query(
    'SELECT c.name, COALESCE(SUM(cd.count), 0) AS c
     FROM click_daily cd
     JOIN links l ON l.id = cd.link_id
     JOIN categories c ON c.id = l.category_id
     GROUP BY c.id
     ORDER BY c DESC'
)->fetchAll();
$catTotal = array_sum(array_column($catStats, 'c'));
$catMax   = $catTotal > 0 ? $catTotal : 1;

// 点击排行 TOP 20（聚合表）
$topLinks = $db->query(
    'SELECT l.title, l.url, COALESCE(SUM(cd.count), 0) AS c
     FROM links l
     LEFT JOIN click_daily cd ON cd.link_id = l.id
     GROUP BY l.id
     ORDER BY c DESC, l.id ASC
     LIMIT 20'
)->fetchAll();

// 最近 50 条点击（明细表，仅取最近数据）
$recentClicks = $db->query(
    'SELECT cl.id, cl.ip, cl.created_at, l.title
     FROM click_logs cl
     JOIN links l ON l.id = cl.link_id
     ORDER BY cl.id DESC
     LIMIT 50'
)->fetchAll();

$pageTitle = '访问统计';
$active    = 'stats';
include __DIR__ . '/partials/header.php';
?>

<h1 class="page-title">访问统计</h1>

<div class="stat-grid">
  <div class="stat-card"><div class="stat-num"><?= $todayClicks ?></div><div class="stat-label">今日点击</div></div>
  <div class="stat-card"><div class="stat-num"><?= $weekClicks ?></div><div class="stat-label">近 7 日点击</div></div>
  <div class="stat-card"><div class="stat-num"><?= $totalClicks ?></div><div class="stat-label">累计点击</div></div>
  <div class="stat-card"><div class="stat-num"><?= $uniqueIps ?></div><div class="stat-label">独立访客(IP)</div></div>
</div>

<section class="panel">
  <h2 class="panel-title">近 30 日点击趋势</h2>
  <div class="bar-chart bar-chart-30">
    <?php foreach ($trend as $date => $count): ?>
      <div class="bar-item" title="<?= e($date) ?>：<?= $count ?> 次">
        <span class="bar-value"><?= $count > 0 ? $count : '' ?></span>
        <div class="bar" style="height: <?= (int)round($count / $trendMax * 100) ?>%"></div>
        <?php if (substr($date, 8, 2) === '01' || $date === array_key_first($trend) || $date === array_key_last($trend)): ?>
          <span class="bar-label"><?= e(substr($date, 5)) ?></span>
        <?php else: ?>
          <span class="bar-label bar-label-blank"></span>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="panel">
  <h2 class="panel-title">分类点击分布</h2>
  <?php if ($catTotal === 0): ?>
    <p class="empty-tip">暂无数据。</p>
  <?php else: ?>
    <?php foreach ($catStats as $row): ?>
      <div class="cat-stat-row">
        <span class="cat-stat-name"><?= e($row['name']) ?></span>
        <div class="cat-stat-track">
          <div class="cat-stat-bar" style="width: <?= (int)round($row['c'] / $catMax * 100) ?>%"></div>
        </div>
        <span class="cat-stat-count"><?= (int)$row['c'] ?> 次（<?= round($row['c'] / $catTotal * 100, 1) ?>%）</span>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</section>

<section class="panel">
  <h2 class="panel-title">点击排行 TOP 20</h2>
  <?php if (!$topLinks): ?>
    <p class="empty-tip">暂无点击数据。</p>
  <?php else: ?>
    <table class="table">
      <thead><tr><th>#</th><th>标题</th><th>网址</th><th>点击次数</th></tr></thead>
      <tbody>
      <?php foreach ($topLinks as $i => $link): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><?= e($link['title']) ?></td>
          <td class="cell-muted cell-url"><?= e($link['url']) ?></td>
          <td><?= (int)$link['c'] ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<section class="panel">
  <h2 class="panel-title">最近点击（最新 50 条）</h2>
  <?php if (!$recentClicks): ?>
    <p class="empty-tip">暂无点击数据。</p>
  <?php else: ?>
    <div class="table-scroll">
      <table class="table">
        <thead><tr><th>时间</th><th>链接</th><th>来源 IP</th></tr></thead>
        <tbody>
        <?php foreach ($recentClicks as $click): ?>
          <tr>
            <td class="cell-muted"><?= e($click['created_at']) ?></td>
            <td><?= e($click['title']) ?></td>
            <td class="cell-muted"><?= e($click['ip'] !== '' ? $click['ip'] : '-') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<?php include __DIR__ . '/partials/footer.php'; ?>
