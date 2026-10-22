<?php
/**
 * 后台公共头部
 * 使用前需已 require app/init.php 并调用 require_admin()；
 * 可选设置 $pageTitle、$active（index|category|link|stats|user）。
 */
if (!isset($pageTitle)) {
    $pageTitle = '后台管理';
}
if (!isset($active)) {
    $active = '';
}
$navItems = [
    'index'    => ['概览', 'index.php'],
    'category' => ['分类管理', 'category.php'],
    'link'     => ['链接管理', 'link.php'],
    'stats'    => ['访问统计', 'stats.php'],
    'settings' => ['外观设置', 'settings.php'],
    'uploads'  => ['文件管理', 'uploads.php'],
    'user'     => ['用户管理', 'user.php'],
];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> - <?= e(config('site.name')) ?> 后台</title>
<link rel="icon" href='data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text y=".9em" font-size="90">🌐</text></svg>'>
<link rel="stylesheet" href="../assets/css/style.css?v=<?= (int)filemtime(__DIR__ . '/../../assets/css/style.css') ?>">
</head>
<body class="admin-body">
<div class="admin-layout">
  <aside class="admin-sidebar">
    <div class="admin-brand">🌐 上网入口 · 后台</div>
    <nav class="admin-nav">
      <?php foreach ($navItems as $key => $item): ?>
        <a href="<?= $item[1] ?>" class="<?= $active === $key ? 'active' : '' ?>"><?= e($item[0]) ?></a>
      <?php endforeach; ?>
      <a href="../index.php">← 返回前台</a>
      <a href="../logout.php">退出登录</a>
    </nav>
    <div class="admin-user"><?= e(current_user()['username']) ?>（管理员）</div>
  </aside>
  <main class="admin-main">
    <div class="admin-container">
      <?= flash_render() ?>
