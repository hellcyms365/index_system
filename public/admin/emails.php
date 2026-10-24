<?php
/**
 * 后台 - 邮箱管理：给任意用户（含管理员）添加 / 修改邮箱、标记验证状态；
 * 附 SMTP 测试发信，便于排查注册验证码收不到的问题。
 */
require_once __DIR__ . '/../../app/init.php';
require_admin();

$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'save_email') {
        $id    = (int)($_POST['id'] ?? 0);
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        if ($id <= 0) {
            flash_set('error', '用户 ID 无效。');
        } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash_set('error', '邮箱格式不正确（留空请直接删除后保存为空）。');
        } else {
            $stmt = $db->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
            $stmt->execute([$email, $id]);
            if ($stmt->fetch() !== false) {
                flash_set('error', '该邮箱已被其他用户使用。');
            } else {
                // 修改邮箱后验证状态重置为未验证，管理员可随后手动标记已验证
                $db->prepare('UPDATE users SET email = ?, email_verified = 0 WHERE id = ?')->execute([$email, $id]);
                flash_set('success', '邮箱已保存（验证状态重置为未验证，可一键标记已验证）。');
            }
        }
        redirect('emails.php');
    }

    if ($action === 'clear_email') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $db->prepare('UPDATE users SET email = NULL, email_verified = 0 WHERE id = ?')->execute([$id]);
            flash_set('success', '邮箱已清除。');
        }
        redirect('emails.php');
    }

    if ($action === 'toggle_verified') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare('SELECT email FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $email = (string)$stmt->fetchColumn();
        if ($email === '') {
            flash_set('error', '该用户还没有邮箱，请先填写邮箱。');
        } else {
            $db->prepare('UPDATE users SET email_verified = 1 - email_verified WHERE id = ?')->execute([$id]);
            flash_set('success', '邮箱验证状态已切换。');
        }
        redirect('emails.php');
    }

    if ($action === 'save_mail_config') {
        $mode     = (string)($_POST['mail_mode'] ?? 'mail');
        $host     = trim((string)($_POST['mail_host'] ?? ''));
        $port     = (int)($_POST['mail_port'] ?? 465);
        $secure   = (string)($_POST['mail_secure'] ?? 'ssl');
        $user     = trim((string)($_POST['mail_user'] ?? ''));
        $pass     = (string)($_POST['mail_pass'] ?? '');
        $from     = trim((string)($_POST['mail_from'] ?? ''));
        $fromName = trim((string)($_POST['mail_from_name'] ?? ''));

        if (!in_array($mode, ['mail', 'smtp'], true)) {
            flash_set('error', '发信模式不合法。');
        } elseif ($port < 1 || $port > 65535) {
            flash_set('error', '端口需在 1-65535 之间。');
        } elseif (!in_array($secure, ['ssl', 'tls', 'none'], true)) {
            flash_set('error', '加密方式不合法。');
        } elseif (str_length($host) > 255 || str_length($user) > 255 || str_length($from) > 255 || str_length($fromName) > 100) {
            flash_set('error', '填写内容过长。');
        } elseif ($from !== '' && !filter_var($from, FILTER_VALIDATE_EMAIL)) {
            flash_set('error', '发件人邮箱格式不正确。');
        } else {
            // 密码留空 = 保留原密码（不回显明文）
            if ($pass === '' && $host !== '') {
                $pass = setting('mail_pass');
            }
            save_setting('mail_mode', $mode);
            save_setting('mail_host', $host);
            save_setting('mail_port', (string)$port);
            save_setting('mail_secure', $secure);
            save_setting('mail_user', $user);
            save_setting('mail_pass', $pass);
            save_setting('mail_from', $from);
            save_setting('mail_from_name', $fromName);
            flash_set('success', '发信设置已保存（无需改文件）。建议点下方「发送测试邮件」验证。');
        }
        redirect('emails.php');
    }

    if ($action === 'test_mail') {
        $to = strtolower(trim((string)($_POST['test_to'] ?? '')));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            flash_set('error', '测试收件邮箱格式不正确。');
        } else {
            $site  = site_name() ?: '上网入口';
            $body  = '<div style="font-family:sans-serif;max-width:480px;margin:0 auto;padding:24px;border:1px solid #e5e7eb;border-radius:12px">'
                   . '<h2 style="margin:0 0 12px">' . htmlspecialchars($site, ENT_QUOTES, 'UTF-8') . ' · SMTP 测试邮件</h2>'
                   . '<p style="margin:0;color:#333">如果收到这封邮件，说明发信配置正常，注册验证码可以正常送达。</p></div>';
            [$ok, $err] = send_mail($to, '【' . $site . '】SMTP 测试', $body);
            if ($ok) {
                flash_set('success', '测试邮件已发送到 ' . $to . '，请查收（注意垃圾箱）。');
            } else {
                flash_set('error', '发送失败：' . $err);
            }
        }
        redirect('emails.php');
    }

    flash_set('error', '未知操作。');
    redirect('emails.php');
}

$users = $db->query(
    'SELECT id, username, email, email_verified, role FROM users ORDER BY id ASC'
)->fetchAll();

$mailCfg = mail_config();
$pageTitle = '邮箱管理';
$active    = 'emails';
include __DIR__ . '/partials/header.php';
?>

<h1 class="page-title">邮箱管理</h1>

<div class="panel">
  <h2 class="panel-title">SMTP 发信设置</h2>
  <p class="form-hint">配置保存到数据库，无需修改任何文件。QQ 邮箱示例：模式 SMTP、服务器 smtp.qq.com、端口 465、加密 ssl、账号你的QQ邮箱、密码<strong>QQ邮箱授权码</strong>（QQ邮箱「设置 → 账户 → 开启SMTP并生成授权码」，不是登录密码）。</p>
  <form method="post" action="emails.php">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_mail_config">
    <div class="form-row">
      <div class="form-col">
        <label for="mail_mode">发信模式</label>
        <select class="input" id="mail_mode" name="mail_mode">
          <option value="smtp" <?= $mailCfg['mode'] === 'smtp' ? 'selected' : '' ?>>SMTP（推荐，稳定）</option>
          <option value="mail" <?= $mailCfg['mode'] === 'mail' ? 'selected' : '' ?>>mail()（依赖服务器自带发信）</option>
        </select>
      </div>
      <div class="form-col">
        <label for="mail_host">SMTP 服务器</label>
        <input class="input" type="text" id="mail_host" name="mail_host" maxlength="255"
               value="<?= e($mailCfg['host']) ?>" placeholder="smtp.qq.com">
      </div>
      <div class="form-col">
        <label for="mail_port">端口</label>
        <input class="input" type="number" id="mail_port" name="mail_port" min="1" max="65535"
               value="<?= (int)$mailCfg['port'] ?>">
      </div>
      <div class="form-col">
        <label for="mail_secure">加密方式</label>
        <select class="input" id="mail_secure" name="mail_secure">
          <option value="ssl"  <?= $mailCfg['secure'] === 'ssl' ? 'selected' : '' ?>>SSL（465 端口）</option>
          <option value="tls"  <?= $mailCfg['secure'] === 'tls' ? 'selected' : '' ?>>STARTTLS（587 端口）</option>
          <option value="none" <?= $mailCfg['secure'] === 'none' ? 'selected' : '' ?>>无加密</option>
        </select>
      </div>
    </div>
    <div class="form-row">
      <div class="form-col">
        <label for="mail_user">发信账号</label>
        <input class="input" type="text" id="mail_user" name="mail_user" maxlength="255"
               value="<?= e($mailCfg['user']) ?>" placeholder="你的QQ邮箱，如 12345@qq.com">
      </div>
      <div class="form-col">
        <label for="mail_pass">密码 / 授权码</label>
        <input class="input" type="password" id="mail_pass" name="mail_pass" maxlength="255"
               placeholder="留空表示保留原密码">
      </div>
      <div class="form-col">
        <label for="mail_from">发件人地址</label>
        <input class="input" type="email" id="mail_from" name="mail_from" maxlength="255"
               value="<?= e($mailCfg['from']) ?>" placeholder="一般与账号相同，留空自动用收件人">
      </div>
      <div class="form-col">
        <label for="mail_from_name">发件人显示名</label>
        <input class="input" type="text" id="mail_from_name" name="mail_from_name" maxlength="100"
               value="<?= e($mailCfg['from_name']) ?>" placeholder="如：上网入口">
      </div>
    </div>
    <button class="btn btn-primary" type="submit">保存发信设置</button>
  </form>
</div>

<div class="panel">
  <h2 class="panel-title">发送测试邮件（验证发信配置）</h2>
  <form class="form-inline" method="post" action="emails.php">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="test_mail">
    <input class="input input-sm" type="email" name="test_to" placeholder="收件邮箱（给自己发一封测试）" required maxlength="191" style="width:260px">
    <button class="btn btn-primary btn-sm" type="submit">发送测试邮件</button>
  </form>
  <p class="form-hint">
    当前生效配置：<strong><?= $mailCfg['mode'] === 'smtp' ? 'SMTP' : 'mail()' ?></strong>
    <?php if ($mailCfg['mode'] === 'smtp'): ?>
      — <?= e($mailCfg['host']) ?>:<?= (int)$mailCfg['port'] ?>（<?= e($mailCfg['secure']) ?>）
      <?php if ($mailCfg['user'] !== ''): ?>，账号 <?= e($mailCfg['user']) ?><?php endif; ?>
      <?php if ($mailCfg['from'] !== ''): ?>，发件人 <?= e($mailCfg['from']) ?><?php endif; ?>
    <?php endif; ?>
    — 若发送失败，按上方配置检查后重试。
  </p>
</div>

<div class="panel">
  <h2 class="panel-title">用户邮箱列表</h2>
  <p class="form-hint">直接修改邮箱后验证状态会重置为「未验证」，可一键标记「已验证」。验证状态仅用于展示与后台判断，不影响登录。</p>
  <?php if (!$users): ?>
    <p class="empty-tip">暂无用户。</p>
  <?php else: ?>
    <div class="table-scroll">
      <table class="table">
        <thead>
          <tr><th>ID</th><th>用户名</th><th>角色</th><th>邮箱</th><th>验证状态</th><th>操作</th></tr>
        </thead>
        <tbody>
        <?php foreach ($users as $user): ?>
          <tr>
            <td><?= (int)$user['id'] ?></td>
            <td>
              <?= e($user['username']) ?>
              <?php if ($user['role'] === 'admin'): ?><span class="badge badge-admin">管理员</span><?php endif; ?>
            </td>
            <td class="cell-muted"><?= $user['role'] === 'admin' ? '管理员' : '普通用户' ?></td>
            <td>
              <form class="form-inline" method="post" action="emails.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_email">
                <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
                <input class="input input-sm" type="email" name="email" value="<?= e($user['email'] ?? '') ?>" placeholder="输入邮箱" maxlength="191" style="width:230px">
                <button class="btn btn-outline btn-sm" type="submit">保存</button>
              </form>
            </td>
            <td>
              <?php if ($user['email'] !== '' && $user['email'] !== null): ?>
                <?php if ((int)$user['email_verified'] === 1): ?>
                  <span class="badge badge-on">已验证</span>
                <?php else: ?>
                  <span class="badge badge-off">未验证</span>
                <?php endif; ?>
              <?php else: ?>
                <span class="badge">无邮箱</span>
              <?php endif; ?>
            </td>
            <td class="cell-actions">
              <?php if ($user['email'] !== '' && $user['email'] !== null): ?>
                <form class="form-inline" method="post" action="emails.php">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="toggle_verified">
                  <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
                  <button class="btn btn-outline btn-sm" type="submit">
                    <?= (int)$user['email_verified'] === 1 ? '取消已验证' : '标记已验证' ?>
                  </button>
                </form>
                <form class="form-inline" method="post" action="emails.php" data-confirm="确定清除该用户的邮箱？">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="clear_email">
                  <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
                  <button class="btn btn-outline btn-sm" type="submit">清除</button>
                </form>
              <?php else: ?>
                <span class="cell-muted">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
