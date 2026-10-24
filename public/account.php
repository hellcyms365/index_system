<?php
/**
 * 个人中心：个人资料 / 我的外观（主题、背景等）/ 我的链接（自建常用网站）
 * 仅登录用户可访问。
 */
require_once __DIR__ . '/../app/init.php';
require_login();

$db      = db();
$me      = current_user();
$uid     = (int)$me['id'];
$tab     = (string)($_GET['tab'] ?? 'profile');
if (!in_array($tab, ['profile', 'appearance', 'links'], true)) {
    $tab = 'profile';
}

$ACCENT_PRESETS = [
    '默认蓝' => '#2563eb',
    '清新绿' => '#059669',
    '活力橙' => '#ea580c',
    '浪漫紫' => '#7c3aed',
    '中国红' => '#dc2626',
    '青蓝'   => '#0ea5e9',
    '暗灰'   => '#475569',
];
$UPLOAD_REL = '/assets/uploads/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');

    // ---------- 个人资料 ----------
    if ($action === 'save_profile') {
        $realName = trim((string)($_POST['real_name'] ?? ''));
        $phone    = trim((string)($_POST['phone'] ?? ''));
        if (str_length($realName) > 50) {
            flash_set('error', '姓名不超过 50 字。');
        } elseif (str_length($phone) > 20) {
            flash_set('error', '手机号不超过 20 位。');
        } else {
            $db->prepare('UPDATE users SET real_name = ?, phone = ? WHERE id = ?')
                ->execute([$realName, $phone, $uid]);
            flash_set('success', '个人资料已保存。');
        }
        redirect('account.php?tab=profile');
    }

    // ---------- 更换邮箱（需验证码） ----------
    if ($action === 'change_email') {
        $newEmail = strtolower(trim((string)($_POST['email'] ?? '')));
        $code     = trim((string)($_POST['code'] ?? ''));
        $curEmail = strtolower(trim((string)($me['email'] ?? '')));
        if ($newEmail === '' || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            flash_set('error', '新邮箱格式不正确。');
        } elseif ($newEmail === $curEmail) {
            flash_set('error', '新邮箱与当前邮箱相同。');
        } else {
            $stmt = $db->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
            $stmt->execute([$newEmail, $uid]);
            if ($stmt->fetch() !== false) {
                flash_set('error', '该邮箱已被其他账号使用。');
            } elseif (!verify_email_code($newEmail, $code)) {
                flash_set('error', '验证码不正确或已过期，请重新获取。');
            } else {
                $db->prepare('UPDATE users SET email = ?, email_verified = 1 WHERE id = ?')
                    ->execute([$newEmail, $uid]);
                flash_set('success', '邮箱已更换并完成验证。');
            }
        }
        redirect('account.php?tab=profile');
    }

    // ---------- 我的外观 ----------
    if ($action === 'save_appearance') {
        $errors = [];
        $accentColor = strtolower(trim((string)($_POST['accent_color'] ?? '')));
        if (!preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $accentColor)) {
            $accentColor = strtolower(trim((string)($_POST['accent_color_manual'] ?? '#2563eb')));
        }
        $bgMode    = (string)($_POST['bg_mode'] ?? 'default');
        $bgColor   = strtolower(trim((string)($_POST['bg_color'] ?? '#f3f5f9')));
        $gradA     = strtolower(trim((string)($_POST['gradient_a'] ?? '#60a5fa')));
        $gradB     = strtolower(trim((string)($_POST['gradient_b'] ?? '#8b5cf6')));
        $bgImage   = trim((string)($_POST['bg_image'] ?? ''));
        $bgOverlay = (string)(max(0.0, min(0.85, (float)($_POST['bg_overlay'] ?? 0.35))));
        $bgFixed   = isset($_POST['bg_fixed']) ? '1' : '0';
        $cardOpacity = (string)(max(0.35, min(1.0, (float)($_POST['card_opacity'] ?? 1.0))));

        if (!in_array($bgMode, ['default', 'color', 'gradient', 'image'], true)) {
            $errors[] = '背景模式不合法。';
        }
        if (!preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $accentColor)) { $errors[] = '主题色格式不正确。'; }
        if (!preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $bgColor))     { $errors[] = '背景色格式不正确。'; }
        if (!preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $gradA))       { $errors[] = '渐变起始色格式不正确。'; }
        if (!preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $gradB))       { $errors[] = '渐变结束色格式不正确。'; }

        // 背景图上传（个人背景图同样存入公共上传目录）
        $newBgImage = $bgImage;
        $file = $_FILES['bg_image_file'] ?? null;
        if ($file && is_array($file) && isset($file['error']) && $file['error'] === UPLOAD_ERR_OK && (int)$file['size'] > 0) {
            $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
                $errors[] = '背景图仅支持 jpg / png / webp / gif 格式。';
            } elseif ((int)$file['size'] > 5 * 1024 * 1024) {
                $errors[] = '背景图不能超过 5MB。';
            } else {
                $uploadDir = __DIR__ . '/assets/uploads/';
                if (!is_dir($uploadDir)) {
                    @mkdir($uploadDir, 0755, true);
                }
                if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
                    $errors[] = '上传目录不可写（public/assets/uploads/），请检查目录权限。';
                } else {
                    $name = 'ubg_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $name)) {
                        $errors[] = '背景图保存失败，请重试。';
                    } elseif (function_exists('getimagesize') && getimagesize($uploadDir . $name) === false) {
                        $errors[] = '上传的文件不是有效图片。';
                        @unlink($uploadDir . $name);
                    } else {
                        $newBgImage = $UPLOAD_REL . $name;
                    }
                }
            }
        } else {
            if ($bgImage !== '' && !preg_match('#^https?://#i', $bgImage) && substr($bgImage, 0, 16) !== $UPLOAD_REL) {
                $errors[] = '背景图地址仅支持 http/https 链接或 /assets/uploads/ 本地路径。';
            }
            $newBgImage = $bgImage;
        }

        if ($errors) {
            flash_set('error', implode(' ', $errors));
        } else {
            save_user_settings($uid, [
                'accent_color' => $accentColor,
                'bg_mode'      => $bgMode,
                'bg_color'     => $bgColor,
                'gradient_a'   => $gradA,
                'gradient_b'   => $gradB,
                'bg_image'     => $newBgImage,
                'bg_overlay'   => $bgOverlay,
                'bg_fixed'     => $bgFixed,
                'card_opacity' => $cardOpacity,
            ]);
            flash_set('success', '个人外观已保存，刷新首页即可看到效果。');
        }
        redirect('account.php?tab=appearance');
    }

    // ---------- 恢复全局默认外观 ----------
    if ($action === 'clear_appearance') {
        clear_user_settings($uid);
        flash_set('success', '已恢复站点默认外观。');
        redirect('account.php?tab=appearance');
    }

    // ---------- 我的链接 ----------
    if ($action === 'link_add' || $action === 'link_edit') {
        $id      = $action === 'link_edit' ? (int)($_POST['id'] ?? 0) : 0;
        $title   = trim((string)($_POST['title'] ?? ''));
        $url     = trim((string)($_POST['url'] ?? ''));
        $desc    = trim((string)($_POST['description'] ?? ''));
        $icon    = trim((string)($_POST['icon'] ?? ''));
        $catId   = (int)($_POST['category_id'] ?? 0);
        $newCat  = trim((string)($_POST['new_category'] ?? ''));
        $sort    = (int)($_POST['sort_order'] ?? 0);
        $pinned  = isset($_POST['is_pinned']) ? 1 : 0;

        if ($title === '' || str_length($title) > 150) {
            flash_set('error', '标题必填且不超过 150 字。');
        } elseif (str_length($desc) > 500) {
            flash_set('error', '描述不超过 500 字。');
        } elseif (str_length($icon) > 255) {
            flash_set('error', '图标内容过长。');
        } elseif ($newCat !== '' && str_length($newCat) > 100) {
            flash_set('error', '新建分类名称不超过 100 字。');
        } else {
            $url = normalize_url($url);
            if ($url === '') {
                flash_set('error', '网址无效，仅支持 http/https 链接。');
            } else {
                // 填写了「新建我的分类」：同名复用，否则创建个人分类
                if ($newCat !== '') {
                    $stmt = $db->prepare('SELECT id FROM categories WHERE name = ? AND user_id = ? LIMIT 1');
                    $stmt->execute([$newCat, $uid]);
                    $dup = $stmt->fetch();
                    if ($dup !== false) {
                        $catId = (int)$dup['id'];
                    } else {
                        $db->prepare('INSERT INTO categories (user_id, name, sort_order, status) VALUES (?, ?, 0, 1)')
                            ->execute([$uid, $newCat]);
                        $catId = (int)$db->lastInsertId();
                    }
                }
                // 分类归属校验：仅允许本人个人分类或全局分类
                $catOk = $db->prepare('SELECT id FROM categories WHERE id = ? AND status = 1 AND (user_id IS NULL OR user_id = ?) LIMIT 1');
                $catOk->execute([$catId, $uid]);
                if ($catOk->fetch() === false) {
                    flash_set('error', '请选择有效的分类（可选你的个人分类或站点分类）。');
                } elseif ($action === 'link_add') {
                    $db->prepare('INSERT INTO links (category_id, user_id, title, url, description, icon, sort_order, is_pinned, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)')
                        ->execute([$catId, $uid, $title, $url, $desc, $icon, $sort, $pinned]);
                    flash_set('success', '链接已添加到首页（仅自己可见）。');
                } else {
                    $upd = $db->prepare('UPDATE links SET category_id = ?, title = ?, url = ?, description = ?, icon = ?, sort_order = ?, is_pinned = ? WHERE id = ? AND user_id = ?');
                    $upd->execute([$catId, $title, $url, $desc, $icon, $sort, $pinned, $id, $uid]);
                    flash_set('success', '链接已更新。');
                }
            }
        }
        redirect('account.php?tab=links');
    }

    // ---------- 我的分类 ----------
    if ($action === 'cat_add') {
        $name  = trim((string)($_POST['name'] ?? ''));
        $order = (int)($_POST['sort_order'] ?? 0);
        if ($name === '' || str_length($name) > 100) {
            flash_set('error', '分类名称不能为空且不超过 100 字。');
        } else {
            $stmt = $db->prepare('SELECT id FROM categories WHERE name = ? AND user_id = ? LIMIT 1');
            $stmt->execute([$name, $uid]);
            if ($stmt->fetch() !== false) {
                flash_set('error', '你已经有同名分类了。');
            } else {
                $db->prepare('INSERT INTO categories (user_id, name, sort_order, status) VALUES (?, ?, ?, 1)')
                    ->execute([$uid, $name, $order]);
                flash_set('success', '分类「' . $name . '」已添加（仅自己可见）。');
            }
        }
        redirect('account.php?tab=links');
    }

    if ($action === 'cat_edit') {
        $id    = (int)($_POST['id'] ?? 0);
        $name  = trim((string)($_POST['name'] ?? ''));
        $order = (int)($_POST['sort_order'] ?? 0);
        if ($id <= 0 || $name === '' || str_length($name) > 100) {
            flash_set('error', '分类名称不能为空且不超过 100 字。');
        } else {
            $stmt = $db->prepare('SELECT id FROM categories WHERE name = ? AND user_id = ? AND id <> ? LIMIT 1');
            $stmt->execute([$name, $uid, $id]);
            if ($stmt->fetch() !== false) {
                flash_set('error', '你已经有同名分类了。');
            } else {
                $db->prepare('UPDATE categories SET name = ?, sort_order = ? WHERE id = ? AND user_id = ?')
                    ->execute([$name, $order, $id, $uid]);
                flash_set('success', '分类已更新。');
            }
        }
        redirect('account.php?tab=links');
    }

    if ($action === 'cat_delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare('SELECT COUNT(*) FROM links WHERE category_id = ? AND user_id = ?');
        $stmt->execute([$id, $uid]);
        if ((int)$stmt->fetchColumn() > 0) {
            flash_set('error', '该分类下还有你的链接，请先移动或删除这些链接。');
        } else {
            $db->prepare('DELETE FROM categories WHERE id = ? AND user_id = ?')->execute([$id, $uid]);
            flash_set('success', '分类已删除。');
        }
        redirect('account.php?tab=links');
    }

    if ($action === 'link_delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare('SELECT icon FROM links WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$id, $uid]);
        $row = $stmt->fetch();
        if ($row !== false) {
            $db->prepare('DELETE FROM links WHERE id = ? AND user_id = ?')->execute([$id, $uid]);
            if (substr((string)$row['icon'], 0, 16) === '/assets/uploads/') {
                $f = __DIR__ . $row['icon'];
                if (is_file($f)) {
                    @unlink($f);
                }
            }
            flash_set('success', '链接已删除。');
        } else {
            flash_set('error', '链接不存在或无权操作。');
        }
        redirect('account.php?tab=links');
    }

    if ($action === 'link_fetch_icon') {
        $id   = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare('SELECT * FROM links WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$id, $uid]);
        $link = $stmt->fetch();
        if ($link === false) {
            flash_set('error', '链接不存在或无权操作。');
        } else {
            $parsed = parse_url($link['url']);
            $scheme = strtolower((string)($parsed['scheme'] ?? 'http'));
            $host   = strtolower((string)($parsed['host'] ?? ''));
            if (!in_array($scheme, ['http', 'https'], true) || $host === '' || preg_match('/^\d+(\.\d+){3}$/', $host)) {
                flash_set('error', '该链接不支持自动抓取图标。');
            } else {
                [$data, $ext] = fetch_favicon($scheme, $host);
                if ($data === null) {
                    flash_set('error', '图标抓取失败：' . $ext);
                } else {
                    $name = 'icon_' . preg_replace('/[^a-z0-9]+/', '_', $host) . '_' . time() . '_' . bin2hex(random_bytes(2)) . '.' . $ext;
                    $dir  = __DIR__ . '/assets/uploads/';
                    if (!is_dir($dir)) {
                        @mkdir($dir, 0755, true);
                    }
                    if (!is_dir($dir) || !is_writable($dir) || @file_put_contents($dir . $name, $data) === false) {
                        flash_set('error', '图标保存失败，请检查上传目录权限。');
                    } else {
                        $old = (string)$link['icon'];
                        if (substr($old, 0, 16) === $UPLOAD_REL && $old !== $UPLOAD_REL . $name) {
                            $f = __DIR__ . $old;
                            if (is_file($f)) {
                                @unlink($f);
                            }
                        }
                        $db->prepare('UPDATE links SET icon = ? WHERE id = ? AND user_id = ?')
                            ->execute([$UPLOAD_REL . $name, $id, $uid]);
                        flash_set('success', '已自动抓取该网站图标。');
                    }
                }
            }
        }
        redirect('account.php?tab=links');
    }

    flash_set('error', '未知操作。');
    redirect('account.php?tab=' . $tab);
}

// ---------- 页面数据 ----------
$editLink = null;
if ($tab === 'links' && isset($_GET['edit'])) {
    $stmt = $db->prepare('SELECT * FROM links WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([(int)$_GET['edit'], $uid]);
    $editLink = $stmt->fetch();
}
$myLinks = [];
if ($tab === 'links') {
    $myLinks = $db->prepare(
        'SELECT l.*, c.name AS category_name
         FROM links l JOIN categories c ON c.id = l.category_id
         WHERE l.user_id = ?
         ORDER BY l.is_pinned DESC, l.sort_order ASC, l.id ASC'
    );
    $myLinks->execute([$uid]);
    $myLinks = $myLinks->fetchAll();
}
$activeCats = $db->query('SELECT id, name FROM categories WHERE status = 1 ORDER BY sort_order ASC, id ASC')->fetchAll();
$myCats     = [];
if ($tab === 'links') {
    // 我的个人分类（含各分类下的个人链接数）
    $myCats = $db->prepare(
        'SELECT c.*, (SELECT COUNT(*) FROM links l WHERE l.category_id = c.id AND l.user_id = ?) AS link_count
         FROM categories c
         WHERE c.user_id = ?
         ORDER BY c.sort_order ASC, c.id ASC'
    );
    $myCats->execute([$uid, $uid]);
    $myCats = $myCats->fetchAll();
}

$pageTitle = '个人中心';
include __DIR__ . '/partials/header.php';
?>

<section class="account-page">
  <div class="tab-nav">
    <a href="account.php?tab=profile"    class="<?= $tab === 'profile' ? 'active' : '' ?>">个人资料</a>
    <a href="account.php?tab=appearance" class="<?= $tab === 'appearance' ? 'active' : '' ?>">我的外观</a>
    <a href="account.php?tab=links"      class="<?= $tab === 'links' ? 'active' : '' ?>">我的链接</a>
  </div>

  <?php if ($tab === 'profile'): ?>
    <div class="panel">
      <h2 class="panel-title">个人资料</h2>
      <form method="post" action="account.php?tab=profile">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_profile">
        <div class="form-row">
          <div class="form-col">
            <label>用户名（不可修改）</label>
            <input class="input" type="text" value="<?= e($me['username']) ?>" disabled>
          </div>
          <div class="form-col">
            <label for="real_name">姓名</label>
            <input class="input" type="text" id="real_name" name="real_name" maxlength="50"
                   value="<?= e($me['real_name'] ?? '') ?>" required placeholder="真实姓名或昵称">
          </div>
        </div>
        <div class="form-row">
          <div class="form-col">
            <label for="phone">手机号</label>
            <input class="input" type="text" id="phone" name="phone" maxlength="20"
                   value="<?= e($me['phone'] ?? '') ?>" placeholder="选填">
          </div>
          <div class="form-col">
            <label>邮箱</label>
            <div class="email-static">
              <?= e($me['email'] ?? '-') ?>
              <?php if ((int)($me['email_verified'] ?? 0) === 1): ?>
                <span class="badge badge-on">已验证</span>
              <?php else: ?>
                <span class="badge badge-off">未验证</span>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <button class="btn btn-primary" type="submit">保存资料</button>
      </form>
    </div>

    <div class="panel">
      <h2 class="panel-title">更换邮箱</h2>
      <form method="post" action="account.php?tab=profile">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="change_email">
        <div class="form-group">
          <label for="new_email">新邮箱</label>
          <div class="input-group">
            <input class="input" type="email" id="new_email" name="email" required maxlength="191"
                   placeholder="新邮箱（需验证）">
            <button class="btn btn-outline btn-sm" type="button" id="change_send_btn">获取验证码</button>
          </div>
          <p class="form-hint" id="change_send_hint"></p>
        </div>
        <div class="form-group">
          <label for="change_code">验证码</label>
          <input class="input" type="text" id="change_code" name="code" required maxlength="6"
                 inputmode="numeric" pattern="[0-9]{6}" placeholder="6 位数字验证码">
        </div>
        <button class="btn btn-primary" type="submit">更换并验证</button>
      </form>
    </div>

  <?php elseif ($tab === 'appearance'): ?>
    <div class="panel">
      <h2 class="panel-title">实时预览</h2>
      <div class="bg-preview" id="bg-preview">
        <div class="bg-preview-card">
          <span class="bg-preview-logo">🌐 <?= e(site_name()) ?></span>
          <span class="bg-preview-chip" id="accent-chip">主题色</span>
        </div>
      </div>
      <p class="form-hint">个人外观只对你自己生效；留空 / 未保存的项沿用站点全局设置。</p>
    </div>

    <form method="post" action="account.php?tab=appearance" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_appearance">

      <?php $cur = user_setting_row($uid); ?>
      <div class="panel">
        <h2 class="panel-title">主题色</h2>
        <div class="swatch-group">
          <?php $currentAccent = strtolower(user_setting($uid, 'accent_color', setting('accent_color', '#2563eb'))); ?>
          <?php foreach ($ACCENT_PRESETS as $label => $color): ?>
            <label class="swatch <?= $currentAccent === $color ? 'active' : '' ?>">
              <input type="radio" name="accent_color" value="<?= $color ?>"
                     <?= $currentAccent === $color ? 'checked' : '' ?>>
              <span class="swatch-dot" style="background: <?= $color ?>"></span>
              <span class="swatch-label"><?= e($label) ?></span>
            </label>
          <?php endforeach; ?>
          <label class="swatch <?= in_array($currentAccent, array_values($ACCENT_PRESETS), true) ? '' : 'active' ?>">
            <input type="radio" name="accent_color" value="custom" id="accent_custom_radio"
                   <?= !in_array($currentAccent, array_values($ACCENT_PRESETS), true) ? 'checked' : '' ?>>
            <span class="swatch-dot custom"><input type="color" id="accent_custom" value="<?= e($currentAccent) ?>"></span>
            <span class="swatch-label">自定义</span>
          </label>
        </div>
        <p class="form-hint">主题色应用到整站按钮、链接、标题等元素。</p>
        <input type="hidden" name="accent_color_manual" id="accent_color_manual" value="<?= e($currentAccent) ?>">
      </div>

      <div class="panel">
        <h2 class="panel-title">背景</h2>
        <?php $bgMode = user_setting($uid, 'bg_mode', setting('bg_mode', 'default')); ?>
        <div class="bg-mode-row">
          <label class="radio-label"><input type="radio" name="bg_mode" value="default"  <?= $bgMode === 'default' ? 'checked' : '' ?>> 默认浅色</label>
          <label class="radio-label"><input type="radio" name="bg_mode" value="color"    <?= $bgMode === 'color' ? 'checked' : '' ?>> 纯色</label>
          <label class="radio-label"><input type="radio" name="bg_mode" value="gradient" <?= $bgMode === 'gradient' ? 'checked' : '' ?>> 渐变</label>
          <label class="radio-label"><input type="radio" name="bg_mode" value="image"    <?= $bgMode === 'image' ? 'checked' : '' ?>> 背景图</label>
        </div>

        <div class="bg-fields bg-field-color" <?= $bgMode === 'color' ? '' : 'style="display:none"' ?>>
          <label for="bg_color">背景颜色</label>
          <input type="color" id="bg_color" name="bg_color" value="<?= e(sanitize_css_color(user_setting($uid, 'bg_color', setting('bg_color', '#f3f5f9')), '#f3f5f9')) ?>">
        </div>

        <div class="bg-fields bg-field-gradient" <?= $bgMode === 'gradient' ? '' : 'style="display:none"' ?>>
          <label for="gradient_a">渐变起始色</label>
          <input type="color" id="gradient_a" name="gradient_a" value="<?= e(sanitize_css_color(user_setting($uid, 'gradient_a', setting('gradient_a', '#60a5fa')), '#60a5fa')) ?>">
          <label for="gradient_b">渐变结束色</label>
          <input type="color" id="gradient_b" name="gradient_b" value="<?= e(sanitize_css_color(user_setting($uid, 'gradient_b', setting('gradient_b', '#8b5cf6')), '#8b5cf6')) ?>">
        </div>

        <div class="bg-fields bg-field-image" <?= $bgMode === 'image' ? '' : 'style="display:none"' ?>>
          <div class="form-row">
            <div class="form-col form-col-wide">
              <label for="bg_image">背景图地址</label>
              <input class="input" type="text" id="bg_image" name="bg_image" maxlength="500"
                     value="<?= e(user_setting($uid, 'bg_image', '')) ?>" placeholder="https://… 或留空仅用下方上传">
            </div>
            <div class="form-col">
              <label for="bg_image_file">或上传图片（≤5MB）</label>
              <input class="input" type="file" id="bg_image_file" name="bg_image_file" accept="image/jpeg,image/png,image/webp,image/gif">
            </div>
          </div>
          <div class="form-row">
            <label class="checkbox-label"><input type="checkbox" name="bg_fixed" value="1" <?= user_setting($uid, 'bg_fixed', setting('bg_fixed', '1')) === '1' ? 'checked' : '' ?>> 背景固定（滚动视差效果）</label>
            <div class="overlay-field">
              <label for="bg_overlay">白色遮罩透明度：<span id="overlay_value"><?= e(user_setting($uid, 'bg_overlay', '0.35')) ?></span></label>
              <input type="range" id="bg_overlay" name="bg_overlay" min="0" max="0.85" step="0.05"
                     value="<?= e(user_setting($uid, 'bg_overlay', '0.35')) ?>">
            </div>
          </div>
        </div>
      </div>

      <div class="panel">
        <h2 class="panel-title">卡片外观</h2>
        <div class="overlay-field">
          <label for="card_opacity">链接卡片透明度：<span id="card_opacity_value"><?= e(user_setting($uid, 'card_opacity', '1')) ?></span></label>
          <input type="range" id="card_opacity" name="card_opacity" min="0.35" max="1" step="0.05"
                 value="<?= e(user_setting($uid, 'card_opacity', '1')) ?>">
        </div>
      </div>

      <div class="panel">
        <button class="btn btn-primary" type="submit">保存我的外观</button>
        <button class="btn btn-outline" type="submit" name="action" value="clear_appearance" formnovalidate data-confirm="确定恢复站点默认外观？">恢复默认</button>
        <a class="btn btn-outline" href="index.php" target="_blank">查看前台效果</a>
      </div>
    </form>

  <?php elseif ($tab === 'links'): ?>
    <div class="panel">
      <h2 class="panel-title"><?= $editLink ? '编辑链接' : '添加链接' ?></h2>
      <form method="post" action="account.php?tab=links">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= $editLink ? 'link_edit' : 'link_add' ?>">
        <?php if ($editLink): ?>
          <input type="hidden" name="id" value="<?= (int)$editLink['id'] ?>">
        <?php endif; ?>
        <div class="form-row">
          <div class="form-col">
            <label for="lk_title">标题</label>
            <input class="input" type="text" id="lk_title" name="title" required maxlength="150"
                   value="<?= e($editLink['title'] ?? '') ?>" placeholder="如：B站">
          </div>
          <div class="form-col">
            <label for="lk_url">网址</label>
            <input class="input" type="text" id="lk_url" name="url" required maxlength="500"
                   value="<?= e($editLink['url'] ?? '') ?>" placeholder="https://bilibili.com">
          </div>
        </div>
        <div class="form-row">
          <div class="form-col">
            <label for="lk_cat">分类</label>
            <select class="input" id="lk_cat" name="category_id" required>
              <?php if ($myCats): ?>
                <optgroup label="我的分类（仅自己可见）">
                  <?php foreach ($myCats as $cat): ?>
                    <option value="<?= (int)$cat['id'] ?>" <?= (int)($editLink['category_id'] ?? 0) === (int)$cat['id'] ? 'selected' : '' ?>>
                      <?= e($cat['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </optgroup>
              <?php endif; ?>
              <optgroup label="站点分类">
                <?php foreach ($activeCats as $cat): ?>
                  <option value="<?= (int)$cat['id'] ?>" <?= (int)($editLink['category_id'] ?? 0) === (int)$cat['id'] ? 'selected' : '' ?>>
                    <?= e($cat['name']) ?>
                  </option>
                <?php endforeach; ?>
              </optgroup>
            </select>
          </div>
          <div class="form-col">
            <label for="lk_new_cat">或新建我的分类</label>
            <input class="input" type="text" id="lk_new_cat" name="new_category" maxlength="100"
                   placeholder="填写分类名，保存时自动创建">
          </div>
        </div>
        <div class="form-row">
          <div class="form-col">
            <label for="lk_icon">图标</label>
            <input class="input" type="text" id="lk_icon" name="icon" maxlength="255"
                   value="<?= e($editLink['icon'] ?? '') ?>" placeholder="emoji（如 🐧）或图片地址">
          </div>
        </div>
        <div class="form-row">
          <div class="form-col form-col-wide">
            <label for="lk_desc">描述（选填）</label>
            <input class="input" type="text" id="lk_desc" name="description" maxlength="500"
                   value="<?= e($editLink['description'] ?? '') ?>" placeholder="一句话说明">
          </div>
          <div class="form-col">
            <label for="lk_sort">排序</label>
            <input class="input" type="number" id="lk_sort" name="sort_order" value="<?= (int)($editLink['sort_order'] ?? 0) ?>">
          </div>
        </div>
        <div class="form-row form-row-bottom">
          <label class="checkbox-label">
            <input type="checkbox" name="is_pinned" value="1" <?= $editLink && (int)$editLink['is_pinned'] === 1 ? 'checked' : '' ?>>
            置顶（排在最前）
          </label>
          <button class="btn btn-primary btn-sm" type="submit"><?= $editLink ? '保存修改' : '添加链接' ?></button>
          <?php if ($editLink): ?><a class="btn btn-outline btn-sm" href="account.php?tab=links">取消编辑</a><?php endif; ?>
        </div>
      </form>
      <p class="form-hint">你的链接只显示在你自己登录后的首页与站内搜索里，其他用户看不到。</p>
    </div>

    <div class="panel">
      <h2 class="panel-title">我的分类（共 <?= count($myCats) ?> 个）</h2>
      <form class="form-inline" method="post" action="account.php?tab=links">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="cat_add">
        <input class="input" type="text" name="name" placeholder="新分类名称（仅自己可见）" required maxlength="100">
        <input class="input input-sm" type="number" name="sort_order" value="0" min="0" placeholder="排序">
        <button class="btn btn-primary btn-sm" type="submit">添加分类</button>
      </form>
      <?php if (!$myCats): ?>
        <p class="empty-tip">还没有个人分类。可以在上面添加，或在上方添加链接时直接填写「新建我的分类」。</p>
      <?php else: ?>
        <div class="table-scroll">
          <table class="table">
            <thead><tr><th>名称</th><th>链接数</th><th>排序</th><th>操作</th></tr></thead>
            <tbody>
            <?php foreach ($myCats as $cat): ?>
              <tr>
                <td>
                  <form class="form-inline" method="post" action="account.php?tab=links">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="cat_edit">
                    <input type="hidden" name="id" value="<?= (int)$cat['id'] ?>">
                    <input class="input input-sm" type="text" name="name" value="<?= e($cat['name']) ?>" required maxlength="100">
                    <input class="input input-sm input-num" type="number" name="sort_order" value="<?= (int)$cat['sort_order'] ?>" min="0">
                    <button class="btn btn-outline btn-sm" type="submit">保存</button>
                  </form>
                </td>
                <td class="cell-muted"><?= (int)$cat['link_count'] ?></td>
                <td class="cell-muted"><?= (int)$cat['sort_order'] ?></td>
                <td class="cell-actions">
                  <form class="form-inline" method="post" action="account.php?tab=links" data-confirm="确定删除分类「<?= e($cat['name']) ?>」？">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="cat_delete">
                    <input type="hidden" name="id" value="<?= (int)$cat['id'] ?>">
                    <button class="btn btn-danger btn-sm" type="submit" <?= (int)$cat['link_count'] > 0 ? 'disabled title="该分类下还有链接，请先移动或删除链接"' : '' ?>>删除</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <div class="panel">
      <h2 class="panel-title">我的链接（共 <?= count($myLinks) ?> 个）</h2>
      <?php if (!$myLinks): ?>
        <p class="empty-tip">还没有个人链接，用上面的表单添加一个吧。</p>
      <?php else: ?>
        <div class="table-scroll">
          <table class="table">
            <thead><tr><th>图标</th><th>标题</th><th>分类</th><th>排序</th><th>操作</th></tr></thead>
            <tbody>
            <?php foreach ($myLinks as $link): ?>
              <tr>
                <td><?= link_icon_html($link['icon'] ?? '', $link['title']) ?></td>
                <td class="cell-title">
                  <a href="go.php?id=<?= (int)$link['id'] ?>" target="_blank" rel="noopener"><?= e($link['title']) ?></a>
                  <?php if ((int)$link['is_pinned'] === 1): ?><span class="badge badge-pin">置顶</span><?php endif; ?>
                  <?php if ($link['description'] !== ''): ?><div class="cell-sub"><?= e($link['description']) ?></div><?php endif; ?>
                </td>
                <td class="cell-muted"><?= e($link['category_name']) ?></td>
                <td class="cell-muted"><?= (int)$link['sort_order'] ?></td>
                <td class="cell-actions">
                  <a class="btn btn-outline btn-sm" href="account.php?tab=links&edit=<?= (int)$link['id'] ?>">编辑</a>
                  <form class="form-inline" method="post" action="account.php?tab=links">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="link_fetch_icon">
                    <input type="hidden" name="id" value="<?= (int)$link['id'] ?>">
                    <button class="btn btn-outline btn-sm" type="submit" title="自动抓取该网站 favicon 作为图标">抓图标</button>
                  </form>
                  <form class="form-inline" method="post" action="account.php?tab=links" data-confirm="确定删除「<?= e($link['title']) ?>」？">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="link_delete">
                    <input type="hidden" name="id" value="<?= (int)$link['id'] ?>">
                    <button class="btn btn-outline btn-sm" type="submit">删除</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
  // ---- 我的外观：主题色 / 背景联动（与后台外观页同逻辑）----
  var accentCustom = document.getElementById('accent_custom');
  var accentManual = document.getElementById('accent_color_manual');
  var cardSlider = document.getElementById('card_opacity');
  var cardValue = document.getElementById('card_opacity_value');
  if (accentCustom) {
    function syncSwatches() {
      document.querySelectorAll('.swatch').forEach(function (sw) {
        var radio = sw.querySelector('input[type="radio"]');
        sw.classList.toggle('active', !!(radio && radio.checked));
      });
    }
    document.querySelectorAll('input[name="accent_color"]').forEach(function (radio) {
      radio.addEventListener('change', function () {
        accentManual.value = radio.value === 'custom' ? accentCustom.value : radio.value;
        syncSwatches();
        updatePreview();
      });
    });
    document.querySelectorAll('.swatch').forEach(function (sw) {
      sw.addEventListener('click', function () {
        var radio = sw.querySelector('input[type="radio"]');
        if (radio && !radio.checked) {
          radio.checked = true;
          radio.dispatchEvent(new Event('change', { bubbles: true }));
        }
      });
    });
    accentCustom.addEventListener('input', function () {
      document.getElementById('accent_custom_radio').checked = true;
      accentManual.value = accentCustom.value;
      updatePreview();
    });
  }
  document.querySelectorAll('input[name="bg_mode"]').forEach(function (radio) {
    radio.addEventListener('change', function () {
      ['color', 'gradient', 'image'].forEach(function (mode) {
        var el = document.querySelector('.bg-field-' + mode);
        if (el) el.style.display = (radio.value === mode) ? '' : 'none';
      });
      updatePreview();
    });
  });
  ['bg_color', 'gradient_a', 'gradient_b', 'bg_image', 'bg_overlay'].forEach(function (id) {
    var el = document.getElementById(id);
    if (el) el.addEventListener('input', updatePreview);
  });
  var overlay = document.getElementById('bg_overlay');
  if (overlay) overlay.addEventListener('input', function () {
    var v = document.getElementById('overlay_value');
    if (v) v.textContent = overlay.value;
  });
  if (cardSlider) cardSlider.addEventListener('input', function () {
    if (cardValue) cardValue.textContent = cardSlider.value;
    applyCardOpacity();
  });
  var fileInput = document.getElementById('bg_image_file');
  if (fileInput) fileInput.addEventListener('change', updatePreview);

  function updatePreview() {
    var box = document.getElementById('bg-preview');
    if (!box) return;
    var modeEl = document.querySelector('input[name="bg_mode"]:checked');
    var mode = modeEl ? modeEl.value : 'default';
    if (mode === 'color') {
      box.style.background = document.getElementById('bg_color').value;
    } else if (mode === 'gradient') {
      box.style.background = 'linear-gradient(135deg, ' +
        document.getElementById('gradient_a').value + ', ' +
        document.getElementById('gradient_b').value + ')';
    } else if (mode === 'image') {
      var file = fileInput && fileInput.files && fileInput.files[0];
      var url = document.getElementById('bg_image').value;
      if (file) {
        box.style.background = 'url(' + URL.createObjectURL(file) + ') center/cover no-repeat';
      } else if (url) {
        box.style.background = 'url(' + url + ') center/cover no-repeat';
      } else {
        box.style.background = '#f3f5f9';
      }
    } else {
      box.style.background = '#f3f5f9';
    }
    var chip = document.getElementById('accent-chip');
    if (chip && accentManual && accentManual.value) chip.style.background = accentManual.value;
    applyCardOpacity();
  }
  function applyCardOpacity() {
    var card = document.querySelector('.bg-preview-card');
    if (card && cardSlider) card.style.opacity = cardSlider.value;
  }
  if (accentCustom) { syncSwatches(); updatePreview(); }

  // ---- 更换邮箱：发送验证码 ----
  var sendBtn = document.getElementById('change_send_btn');
  var emailInput = document.getElementById('new_email');
  var hint = document.getElementById('change_send_hint');
  var csrf = document.querySelector('input[name="csrf_token"]');
  var countdown = 0;
  var timer = null;
  if (sendBtn) {
    sendBtn.addEventListener('click', function () {
      if (countdown > 0) return;
      var email = emailInput.value.trim();
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        hint.textContent = '请填写正确的邮箱格式。';
        return;
      }
      var fd = new FormData();
      fd.append('csrf_token', csrf.value);
      fd.append('email', email);
      fd.append('usage', 'change');
      fetch('send_code.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          hint.textContent = res.msg || '';
          hint.className = res.ok ? 'form-hint form-hint-ok' : 'form-hint';
          if (res.ok) {
            countdown = 60;
            sendBtn.disabled = true;
            sendBtn.textContent = countdown + 's 后重发';
            timer = setInterval(function () {
              countdown--;
              if (countdown <= 0) {
                clearInterval(timer);
                sendBtn.disabled = false;
                sendBtn.textContent = '重新获取验证码';
              } else {
                sendBtn.textContent = countdown + 's 后重发';
              }
            }, 1000);
          }
        })
        .catch(function () { hint.textContent = '网络错误，请重试。'; });
    });
  }
});
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
