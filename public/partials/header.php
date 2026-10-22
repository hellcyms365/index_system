<?php
/**
 * 前台公共头部
 * 使用前需已 require app/init.php；可选设置 $pageTitle。
 * 根据后台「外观设置」注入主题色与背景样式。
 */
if (!isset($pageTitle)) {
    $pageTitle = site_name();
}
$currentUser = current_user();

// ---- 外观设置 ----
$accent    = sanitize_css_color(setting('accent_color'));
$bgMode    = setting('bg_mode', 'default');
$bgImage   = trim(setting('bg_image'));
$bgColor   = sanitize_css_color(setting('bg_color'), '#f3f5f9');
$gradA     = sanitize_css_color(setting('gradient_a'), '#60a5fa');
$gradB     = sanitize_css_color(setting('gradient_b'), '#8b5cf6');
$bgOverlay = max(0.0, min(0.85, (float)setting('bg_overlay', '0.35')));
$bgFixed   = setting('bg_fixed', '1') === '1';
$cardAlpha = max(0.35, min(1.0, (float)setting('card_opacity', '1')));

// 背景图仅允许 http(s) 或本地上传目录，避免非法协议 / CSS 注入
if ($bgImage !== '' && !preg_match('#^https?://#i', $bgImage) && substr($bgImage, 0, 16) !== '/assets/uploads/') {
    $bgImage = '';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> - <?= e(site_name()) ?></title>
<link rel="icon" href='data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text y=".9em" font-size="90">🌐</text></svg>'>
<link rel="stylesheet" href="assets/css/style.css?v=<?= (int)filemtime(__DIR__ . '/../assets/css/style.css') ?>">
<style>
:root {
  --primary: <?= e($accent) ?>;
  --card-alpha: <?= (string)$cardAlpha ?>;
}
<?php if ($bgMode === 'color'): ?>
body.bg-color { --bg-custom: <?= e($bgColor) ?>; background: var(--bg-custom); }
<?php elseif ($bgMode === 'gradient'): ?>
body.bg-gradient {
  background: linear-gradient(135deg, <?= e($gradA) ?>, <?= e($gradB) ?>);
  background-attachment: fixed;
}
<?php elseif ($bgMode === 'image' && $bgImage !== ''): ?>
body.bg-image { --bg-img-url: url(<?= css_quote($bgImage) ?>); --bg-mask: rgba(255,255,255,<?= (string)$bgOverlay ?>); }
body.bg-image { background-attachment: <?= $bgFixed ? 'fixed' : 'scroll' ?>; }
<?php endif; ?>
</style>
</head>
<body class="bg-<?= e($bgMode) ?>">
<header class="site-header">
  <div class="container header-inner">
    <a class="logo" href="index.php"><span class="logo-icon">🌐</span><?= e(site_name()) ?></a>
    <form class="search-form" action="search.php" method="get" role="search">
      <input type="search" name="q" placeholder="搜索网站、描述…" value="<?= e($_GET['q'] ?? '') ?>" autocomplete="off">
      <button type="submit">搜索</button>
    </form>
    <nav class="user-nav">
      <?php if ($currentUser): ?>
        <span class="user-name"><?= e($currentUser['username']) ?><?= is_admin() ? ' · 管理员' : '' ?></span>
        <?php if (is_admin()): ?><a href="admin/index.php">后台管理</a><?php endif; ?>
        <a href="logout.php">退出</a>
      <?php else: ?>
        <a href="login.php">登录</a>
        <?php if (config('app.registration_open')): ?><a href="register.php">注册</a><?php endif; ?>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main class="container">
<?= flash_render() ?>
