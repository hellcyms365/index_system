<?php
/**
 * 上网入口 - 一键安装向导
 *
 * 用法：将源码上传到服务器后，访问 站点地址/install.php 按提示完成安装。
 * ⚠️ 安装完成后请务必删除本文件，防止他人重复安装覆盖数据。
 *
 * 本文件独立自举：直接管理自己的连接与建表；仅复用 functions.php 与 settings.php 中的常量/工具。
 */
require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/settings.php'; // 引入 DEFAULT_SETTINGS（含 db.php 依赖，仅取常量，不触发连接）

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

// 建表语句（与 database.sql 保持一致）
$SCHEMA = [
    'users' => <<<'SQL'
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(191) NULL DEFAULT NULL,
    email_verified TINYINT NOT NULL DEFAULT 0,
    phone VARCHAR(50) NOT NULL DEFAULT '',
    real_name VARCHAR(50) NOT NULL DEFAULT '',
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','user') NOT NULL DEFAULT 'user',
    status TINYINT NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uk_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    'categories' => <<<'SQL'
CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL DEFAULT NULL,
    name VARCHAR(100) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    status TINYINT NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    'links' => <<<'SQL'
CREATE TABLE IF NOT EXISTS links (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL DEFAULT NULL,
    category_id INT UNSIGNED NOT NULL,
    title VARCHAR(150) NOT NULL,
    url VARCHAR(500) NOT NULL,
    description VARCHAR(500) NOT NULL DEFAULT '',
    icon VARCHAR(255) NOT NULL DEFAULT '',
    sort_order INT NOT NULL DEFAULT 0,
    is_pinned TINYINT NOT NULL DEFAULT 0,
    status TINYINT NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_category (category_id),
    KEY idx_user (user_id),
    CONSTRAINT fk_links_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    'click_logs' => <<<'SQL'
CREATE TABLE IF NOT EXISTS click_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    link_id INT UNSIGNED NOT NULL,
    ip VARCHAR(45) NOT NULL DEFAULT '',
    user_agent VARCHAR(255) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_link (link_id),
    KEY idx_date (created_at),
    CONSTRAINT fk_clicks_link FOREIGN KEY (link_id) REFERENCES links(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    'click_daily' => <<<'SQL'
CREATE TABLE IF NOT EXISTS click_daily (
    link_id INT UNSIGNED NOT NULL,
    click_date DATE NOT NULL,
    count INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (link_id, click_date),
    KEY idx_date (click_date),
    CONSTRAINT fk_daily_link FOREIGN KEY (link_id) REFERENCES links(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    'settings' => <<<'SQL'
CREATE TABLE IF NOT EXISTS settings (
    skey VARCHAR(64) NOT NULL PRIMARY KEY,
    svalue TEXT,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    'user_settings' => <<<'SQL'
CREATE TABLE IF NOT EXISTS user_settings (
    user_id INT UNSIGNED NOT NULL PRIMARY KEY,
    accent_color VARCHAR(20) NOT NULL DEFAULT '',
    bg_mode VARCHAR(20) NOT NULL DEFAULT '',
    bg_color VARCHAR(20) NOT NULL DEFAULT '',
    gradient_a VARCHAR(20) NOT NULL DEFAULT '',
    gradient_b VARCHAR(20) NOT NULL DEFAULT '',
    bg_image VARCHAR(500) NOT NULL DEFAULT '',
    bg_overlay VARCHAR(10) NOT NULL DEFAULT '',
    bg_fixed VARCHAR(2) NOT NULL DEFAULT '',
    card_opacity VARCHAR(10) NOT NULL DEFAULT '',
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
];

$errors    = [];
$installed = false;
$justInstalled = false;

// 判断是否已安装（能否连接并读到 users 表）
function is_installed(array $cfg): bool
{
    try {
        $pdo = new PDO(
            "mysql:host={$cfg['host']};port={$cfg['port']};charset=utf8mb4",
            $cfg['user'],
            $cfg['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $pdo->query("SELECT 1 FROM `{$cfg['name']}`.`users` LIMIT 1");
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

$defaults = [
    'host' => config('db.host'),
    'port' => config('db.port'),
    'name' => config('db.name'),
    'user' => config('db.user'),
    'pass' => config('db.pass'),
];

// 表单已提交时执行安装
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $cfg = [
        'host' => trim((string)($_POST['db_host'] ?? $defaults['host'])),
        'port' => trim((string)($_POST['db_port'] ?? $defaults['port'])),
        'name' => trim((string)($_POST['db_name'] ?? $defaults['name'])),
        'user' => trim((string)($_POST['db_user'] ?? $defaults['user'])),
        'pass' => (string)($_POST['db_pass'] ?? $defaults['pass']),
    ];
    $adminUser = trim((string)($_POST['admin_user'] ?? 'admin'));
    $adminPass = (string)($_POST['admin_pass'] ?? '');

    if (!preg_match('/^\d{1,6}$/', $cfg['port'])) {
        $errors[] = '数据库端口格式不正确。';
    }
    if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $cfg['name'])) {
        $errors[] = '数据库名只能包含字母、数字和下划线。';
    }
    if (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $adminUser)) {
        $errors[] = '管理员用户名需为 3-20 位字母、数字或下划线。';
    }
    if (str_length($adminPass) < 6) {
        $errors[] = '管理员密码长度至少 6 位。';
    }

    if (!$errors) {
        try {
            $pdo = new PDO(
                "mysql:host={$cfg['host']};port={$cfg['port']};charset=utf8mb4",
                $cfg['user'],
                $cfg['pass'],
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );

            // 建库（不存在时创建）
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$cfg['name']}`
                        DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$cfg['name']}`");

            // 建表
            foreach ($SCHEMA as $sql) {
                $pdo->exec($sql);
            }

            // 防重复安装：目标库已包含数据（已安装过 / 已导入 database.sql）时中止，
            // 避免覆盖已有数据或触发唯一索引重复插入报错
            $hasUsers      = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
            $hasCategories = (int)$pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();

            if ($hasUsers > 0 || $hasCategories > 0) {
                $errors[] = '检测到目标数据库已包含数据（可能已安装过，或已导入 database.sql）。'
                    . '为避免覆盖已有数据，安装已中止，未做任何写入。'
                    . '如需重新安装：请先清空库中的 users / categories / links / click_logs 四个数据表，'
                    . '或新建一个空数据库并修改 app/config.php 后重试；若已知现有管理员账号，可直接登录使用。';
            } else {
                // 写入管理员账号
                $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, role, status) VALUES (?, ?, ?, 1)');
                $stmt->execute([$adminUser, password_hash($adminPass, PASSWORD_DEFAULT), 'admin']);

                // 写入示例分类与链接（首次使用有内容可看，可在后台自行修改/删除）
                $categoryNames = ['常用工具', '技术开发', '资讯阅读', '影音娱乐'];
                $categoryIds   = [];
                $insertCat = $pdo->prepare('INSERT INTO categories (name, sort_order, status) VALUES (?, ?, 1)');
                foreach ($categoryNames as $i => $name) {
                    $insertCat->execute([$name, $i + 1]);
                    $categoryIds[$name] = (int)$pdo->lastInsertId();
                }

                $sampleLinks = [
                    '常用工具' => [
                        ['百度搜索', 'https://www.baidu.com', '综合搜索', '🔍'],
                        ['Google', 'https://www.google.com', '全球搜索', '🌐'],
                        ['Bing', 'https://www.bing.com', '微软搜索', '🪟'],
                        ['百度网盘', 'https://pan.baidu.com', '云盘存储', '📦'],
                        ['有道翻译', 'https://fanyi.youdao.com', '在线翻译', '📖'],
                    ],
                    '技术开发' => [
                        ['GitHub', 'https://github.com', '代码托管与开源社区', '🐙'],
                        ['Gitee 码云', 'https://gitee.com', '国内代码托管', '🐴'],
                        ['掘金', 'https://juejin.cn', '技术文章社区', '⛏️'],
                        ['菜鸟教程', 'https://www.runoob.com', '编程入门教程', '🐦'],
                        ['MDN Web Docs', 'https://developer.mozilla.org', 'Web 开发文档', '📚'],
                    ],
                    '资讯阅读' => [
                        ['知乎', 'https://www.zhihu.com', '问答与社区', '💡'],
                        ['微博', 'https://weibo.com', '社交媒体', '📣'],
                        ['36氪', 'https://36kr.com', '科技与创投资讯', '⚡'],
                        ['少数派', 'https://sspai.com', '效率与生活方式', '🎯'],
                        ['澎湃新闻', 'https://www.thepaper.cn', '时事新闻', '📰'],
                    ],
                    '影音娱乐' => [
                        ['哔哩哔哩', 'https://www.bilibili.com', '视频弹幕网站', '📺'],
                        ['腾讯视频', 'https://v.qq.com', '在线影视', '🎬'],
                        ['网易云音乐', 'https://music.163.com', '在线音乐', '🎵'],
                        ['抖音', 'https://www.douyin.com', '短视频', '📱'],
                    ],
                ];
                $insertLink = $pdo->prepare(
                    'INSERT INTO links (category_id, title, url, description, icon, sort_order, is_pinned, status) VALUES (?, ?, ?, ?, ?, ?, 0, 1)'
                );
                foreach ($sampleLinks as $catName => $links) {
                    if (!isset($categoryIds[$catName])) {
                        continue;
                    }
                    foreach ($links as $i => $link) {
                        $insertLink->execute([$categoryIds[$catName], $link[0], $link[1], $link[2], $link[3], $i + 1]);
                    }
                }

                // 写入默认站点设置
                $insertSetting = $pdo->prepare('INSERT IGNORE INTO settings (skey, svalue) VALUES (?, ?)');
                foreach (DEFAULT_SETTINGS as $skey => $svalue) {
                    $insertSetting->execute([$skey, $svalue]);
                }

                $justInstalled = true;
            }
        } catch (PDOException $e) {
            $errors[] = '数据库操作失败：' . $e->getMessage();
        }

        // 安装成功：把表单里的数据库配置回写 app/config.php，避免站点无法连接
        if ($justInstalled) {
            $configPath = dirname(__DIR__) . '/app/config.php';
            $configWritten = is_writable($configPath)
                && @file_put_contents($configPath, render_config_with_db($cfg)) !== false;
        }
    }
} else {
    // GET 请求：若已安装则提示，避免误覆盖
    try {
        $installed = is_installed($defaults);
    } catch (Exception $e) {
        $installed = false;
    }
}

?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>安装向导 - <?= e(config('site.name')) ?></title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: "Microsoft YaHei", "PingFang SC", system-ui, sans-serif; background: #f3f5f9; color: #1f2937; }
  .wrap { max-width: 560px; margin: 48px auto; padding: 0 16px; }
  .card { background: #fff; border: 1px solid #e5e9f0; border-radius: 14px; padding: 28px; box-shadow: 0 4px 20px rgba(17,24,39,.06); }
  h1 { font-size: 20px; margin-bottom: 8px; }
  .sub { color: #6b7280; font-size: 13px; margin-bottom: 20px; }
  .field { margin-bottom: 14px; }
  label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; }
  input { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; }
  input:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.15); }
  .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
  .btn { display: inline-block; width: 100%; padding: 12px; border: 0; border-radius: 8px; background: #2563eb; color: #fff; font-size: 15px; font-weight: 600; cursor: pointer; margin-top: 8px; }
  .btn:hover { background: #1d4ed8; }
  .error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; white-space: pre-wrap; word-break: break-all; }
  .success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 12px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; }
  .warn { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; padding: 12px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; }
  .links { margin-top: 18px; display: flex; gap: 12px; }
  .links a { flex: 1; text-align: center; padding: 10px; border-radius: 8px; text-decoration: none; font-size: 14px; }
  .links .primary { background: #2563eb; color: #fff; }
  .links .ghost { border: 1px solid #d1d5db; color: #374151; }
  .hint { font-size: 12px; color: #6b7280; margin-top: 4px; }
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h1>🌐 <?= e(config('site.name')) ?> 安装向导</h1>

    <?php if ($justInstalled): ?>
      <div class="success">✅ 安装成功！<br>管理员账号：<strong><?= e($adminUser) ?></strong><br>请立即登录后台修改默认密码，并 <strong>删除服务器上的 install.php 文件</strong>。</div>
      <?php if (isset($configWritten)): ?>
        <?php if ($configWritten): ?>
          <div class="success">✅ 数据库配置已自动写入 app/config.php，首页现在即可访问。</div>
        <?php else: ?>
          <div class="warn">⚠️ 无法自动写入 app/config.php（文件目录可能没有写权限）。<br>
            请手动编辑 app/config.php，把数据库连接改为：<br>
            host = <strong><?= e($cfg['host']) ?></strong>，port = <strong><?= e($cfg['port']) ?></strong>，<br>
            库名 = <strong><?= e($cfg['name']) ?></strong>，用户名 = <strong><?= e($cfg['user']) ?></strong>，密码 = <strong><?= e($cfg['pass']) ?></strong></div>
        <?php endif; ?>
      <?php endif; ?>
      <div class="links">
        <a class="primary" href="index.php">进入首页</a>
        <a class="ghost" href="admin/index.php">登录后台</a>
      </div>
    <?php elseif ($installed): ?>
      <p class="sub">系统检测到数据库已安装（users 表已存在）。</p>
      <div class="warn">为避免覆盖已有数据，安装向导已停用。请直接访问 <a href="index.php">首页</a> 或 <a href="login.php">登录</a>。<br>如需重新安装，请先删除旧数据表，并删除本文件后再次访问。</div>
    <?php else: ?>
      <p class="sub">填写数据库连接信息，一键完成建库、建表并创建管理员账号（首次访问用）。</p>
      <?php if ($errors): ?>
        <div class="error"><?= e(implode("\n", $errors)) ?></div>
      <?php endif; ?>
      <form method="post" action="install.php">
        <?= csrf_field() ?>
        <div class="grid2">
          <div class="field">
            <label for="db_host">数据库地址</label>
            <input id="db_host" name="db_host" value="<?= e($_POST['db_host'] ?? $defaults['host']) ?>" required>
          </div>
          <div class="field">
            <label for="db_port">端口</label>
            <input id="db_port" name="db_port" value="<?= e($_POST['db_port'] ?? $defaults['port']) ?>" required>
          </div>
        </div>
        <div class="field">
          <label for="db_name">数据库名（不存在则自动创建）</label>
          <input id="db_name" name="db_name" value="<?= e($_POST['db_name'] ?? $defaults['name']) ?>" required>
          <p class="hint">仅限字母、数字和下划线</p>
        </div>
        <div class="grid2">
          <div class="field">
            <label for="db_user">数据库用户名</label>
            <input id="db_user" name="db_user" value="<?= e($_POST['db_user'] ?? $defaults['user']) ?>" required>
          </div>
          <div class="field">
            <label for="db_pass">数据库密码</label>
            <input id="db_pass" name="db_pass" type="password" value="<?= e($_POST['db_pass'] ?? $defaults['pass']) ?>">
          </div>
        </div>
        <hr style="border:0;border-top:1px solid #e5e9f0;margin:18px 0">
        <div class="grid2">
          <div class="field">
            <label for="admin_user">管理员用户名</label>
            <input id="admin_user" name="admin_user" value="<?= e($_POST['admin_user'] ?? 'admin') ?>" required>
          </div>
          <div class="field">
            <label for="admin_pass">管理员密码</label>
            <input id="admin_pass" name="admin_pass" type="password" required minlength="6">
            <p class="hint">至少 6 位，安装后请尽快修改</p>
          </div>
        </div>
        <button class="btn" type="submit">开始安装</button>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
