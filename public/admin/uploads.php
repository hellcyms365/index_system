<?php
/**
 * 后台 - 文件管理：上传 / 浏览 / 复制链接 / 设为背景图 / 删除
 * 管理目录：public/assets/uploads/
 * 安全说明：文件名由系统随机生成；格式白名单 + 图片真实内容校验；.htaccess 与正在使用的背景图不可删除。
 */
require_once __DIR__ . '/../../app/init.php';
require_admin();

$UPLOAD_REL = '/assets/uploads/';
$UPLOAD_DIR = __DIR__ . '/../assets/uploads/';
$ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'ico', 'pdf', 'zip', 'txt', 'md', 'csv'];
$IMAGE_EXT   = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
$MAX_SIZE    = 10 * 1024 * 1024; // 10MB

/** 人类可读的文件大小 */
function human_size(int $bytes): string
{
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 1) . ' MB';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }
    return $bytes . ' B';
}

if (!is_dir($UPLOAD_DIR)) {
    @mkdir($UPLOAD_DIR, 0755, true);
}

// ---- POST 处理 ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'upload') {
        if (!is_dir($UPLOAD_DIR) || !is_writable($UPLOAD_DIR)) {
            flash_set('error', '上传目录不可写（public/assets/uploads/），请检查目录权限。');
        } else {
            $up = $_FILES['files'] ?? null;
            if (!$up || !is_array($up['name'] ?? null) || count($up['name']) === 0 || $up['name'][0] === '') {
                flash_set('error', '请选择要上传的文件。');
            } else {
                $ok     = 0;
                $errors = [];
                for ($i = 0; $i < count($up['name']); $i++) {
                    $original = (string)$up['name'][$i];
                    if ($up['error'][$i] !== UPLOAD_ERR_OK) {
                        $errors[] = $original . '：上传失败（错误码 ' . $up['error'][$i] . '）';
                        continue;
                    }
                    if ((int)$up['size'][$i] > $MAX_SIZE) {
                        $errors[] = $original . '：超过 10MB 限制';
                        continue;
                    }
                    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
                    if (!in_array($ext, $ALLOWED_EXT, true)) {
                        $errors[] = $original . '：不支持的格式（' . ($ext !== '' ? $ext : '无扩展名') . '）';
                        continue;
                    }
                    if (in_array($ext, $IMAGE_EXT, true)) {
                        // 图片做真实内容校验，防止伪装文件
                        if (getimagesize($up['tmp_name'][$i]) === false) {
                            $errors[] = $original . '：不是有效图片';
                            continue;
                        }
                    }
                    $name = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    if (!move_uploaded_file($up['tmp_name'][$i], $UPLOAD_DIR . $name)) {
                        $errors[] = $original . '：保存失败';
                        continue;
                    }
                    $ok++;
                }
                if ($ok > 0) {
                    $message = '成功上传 ' . $ok . ' 个文件。';
                    if ($errors) {
                        $message .= ' 有 ' . count($errors) . ' 个失败：' . implode('；', $errors);
                    }
                    flash_set('success', $message);
                } elseif ($errors) {
                    flash_set('error', implode('；', $errors));
                }
            }
        }
        redirect('uploads.php');
    } elseif ($action === 'delete') {
        $name = basename((string)($_POST['name'] ?? ''));
        if ($name === '' || $name === '.htaccess' || !preg_match('/^[A-Za-z0-9._-]+$/', $name)) {
            flash_set('error', '文件名不合法。');
        } elseif (!is_file($UPLOAD_DIR . $name)) {
            flash_set('error', '文件不存在。');
        } elseif (setting('bg_image') === $UPLOAD_REL . $name) {
            flash_set('error', '该文件正被用作首页背景图，请先在「外观设置」中更换背景图再删除。');
        } else {
            if (@unlink($UPLOAD_DIR . $name)) {
                flash_set('success', '文件「' . $name . '」已删除。');
            } else {
                flash_set('error', '删除失败，请检查文件权限。');
            }
        }
        redirect('uploads.php');
    } elseif ($action === 'setbg') {
        $name = basename((string)($_POST['name'] ?? ''));
        if ($name === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $name) || !is_file($UPLOAD_DIR . $name)) {
            flash_set('error', '文件不存在。');
        } else {
            save_setting('bg_image', $UPLOAD_REL . $name);
            save_setting('bg_mode', 'image');
            flash_set('success', '已设为首页背景图并切换到「背景图」模式，刷新首页即可看到。');
        }
        redirect('uploads.php');
    } else {
        flash_set('error', '未知操作。');
        redirect('uploads.php');
    }
}

// ---- 列出文件（最新在前）----
$fileList = [];
foreach (@scandir($UPLOAD_DIR) ?: [] as $entry) {
    if ($entry === '.' || $entry === '..' || $entry[0] === '.' || $entry === '.htaccess') {
        continue;
    }
    $path = $UPLOAD_DIR . $entry;
    if (!is_file($path)) {
        continue;
    }
    $fileList[] = [
        'name'  => $entry,
        'size'  => (int)filesize($path),
        'mtime' => (int)filemtime($path),
        'ext'   => strtolower(pathinfo($entry, PATHINFO_EXTENSION)),
    ];
}
usort($fileList, function ($a, $b) {
    return $b['mtime'] <=> $a['mtime'];
});

$activeBg = setting('bg_image');
$totalSize = array_sum(array_column($fileList, 'size'));

$pageTitle = '文件管理';
$active    = 'uploads';
include __DIR__ . '/partials/header.php';
?>

<h1 class="page-title">文件管理</h1>

<div class="panel">
  <h2 class="panel-title">上传文件</h2>
  <form class="form-inline" method="post" action="uploads.php" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="upload">
    <input class="input input-sm" type="file" name="files[]" multiple required>
    <button class="btn btn-primary btn-sm" type="submit">上传</button>
  </form>
  <p class="form-hint">
    支持格式：<?= e(implode(' / ', $ALLOWED_EXT)) ?>，单文件不超过 10MB；图片会自动做内容校验。<br>
    上传后可在「外观设置 → 背景图地址」或链接图标的地址中，粘贴文件的链接使用。
  </p>
</div>

<div class="panel">
  <h2 class="panel-title">文件列表（<?= count($fileList) ?> 个，共 <?= e(human_size($totalSize)) ?>）</h2>

  <?php if (!$fileList): ?>
    <p class="empty-tip">上传目录为空。背景图、图标等文件都会保存在这里。</p>
  <?php else: ?>
    <div class="table-scroll">
      <table class="table">
        <thead>
          <tr><th>预览</th><th>文件名</th><th>大小</th><th>上传时间</th><th>操作</th></tr>
        </thead>
        <tbody>
        <?php foreach ($fileList as $file): ?>
          <?php
            $isImage = in_array($file['ext'], $IMAGE_EXT, true);
            $fileUrl = $UPLOAD_REL . $file['name'];
            $isActiveBg = ($activeBg === $fileUrl);
          ?>
          <tr>
            <td>
              <?php if ($isImage): ?>
                <img class="file-thumb" src="<?= e($fileUrl) ?>" alt="<?= e($file['name']) ?>" loading="lazy">
              <?php else: ?>
                <span class="file-icon">📄</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="file-name"><?= e($file['name']) ?></div>
              <?php if ($isActiveBg): ?>
                <span class="badge badge-on">当前背景图</span>
              <?php endif; ?>
            </td>
            <td class="cell-muted"><?= e(human_size($file['size'])) ?></td>
            <td class="cell-muted"><?= e(date('Y-m-d H:i', $file['mtime'])) ?></td>
            <td class="cell-actions">
              <button class="btn btn-outline btn-sm" type="button" data-url="<?= e(url($fileUrl)) ?>" onclick="copyLink(this)">复制链接</button>
              <form class="form-inline" method="post" action="uploads.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="setbg">
                <input type="hidden" name="name" value="<?= e($file['name']) ?>">
                <button class="btn btn-outline btn-sm" type="submit">设为背景图</button>
              </form>
              <form class="form-inline" method="post" action="uploads.php" data-confirm="确定删除文件「<?= e($file['name']) ?>」？删除后不可恢复。">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="name" value="<?= e($file['name']) ?>">
                <button class="btn btn-danger btn-sm" type="submit">删除</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<script>
function copyLink(btn) {
  var url = btn.getAttribute('data-url');
  function done() {
    var old = btn.textContent;
    btn.textContent = '已复制 ✓';
    setTimeout(function () { btn.textContent = old; }, 1200);
  }
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(url).then(done).catch(function () { fallbackCopy(url, done); });
  } else {
    fallbackCopy(url, done);
  }
}
function fallbackCopy(url, done) {
  var ta = document.createElement('textarea');
  ta.value = url;
  ta.style.position = 'fixed';
  ta.style.opacity = '0';
  document.body.appendChild(ta);
  ta.select();
  try {
    document.execCommand('copy');
    done();
  } catch (e) {
    window.prompt('复制失败，请手动复制链接：', url);
  }
  document.body.removeChild(ta);
}
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
