<?php
/**
 * 后台 - 外观设置：站点名称 / 副标题 / 主题色 / 背景（纯色、渐变、图片上传）/ 页脚文字
 */
require_once __DIR__ . '/../../app/init.php';
require_admin();

// 主题色预设
$ACCENT_PRESETS = [
    '默认蓝' => '#2563eb',
    '清新绿' => '#059669',
    '活力橙' => '#ea580c',
    '浪漫紫' => '#7c3aed',
    '中国红' => '#dc2626',
    '青蓝'   => '#0ea5e9',
    '暗灰'   => '#475569',
];

// 上传目录（相对 public/）
$UPLOAD_REL = '/assets/uploads/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $errors  = [];
    // 主题色：预设单选直接提交色值；「自定义」提交 custom，回退读取取色器隐藏域
    $accentColor = strtolower(trim((string)($_POST['accent_color'] ?? '')));
    if (!preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $accentColor)) {
        $accentColor = strtolower(trim((string)($_POST['accent_color_manual'] ?? '#2563eb')));
    }
    $siteName    = trim((string)($_POST['site_name'] ?? ''));
    $tagline     = trim((string)($_POST['tagline'] ?? ''));
    $footerText  = trim((string)($_POST['footer_text'] ?? ''));
    $bgMode      = (string)($_POST['bg_mode'] ?? 'default');
    $bgColor     = strtolower(trim((string)($_POST['bg_color'] ?? '#f3f5f9')));
    $gradA       = strtolower(trim((string)($_POST['gradient_a'] ?? '#60a5fa')));
    $gradB       = strtolower(trim((string)($_POST['gradient_b'] ?? '#8b5cf6')));
    $bgImage     = trim((string)($_POST['bg_image'] ?? ''));
    $bgOverlay   = (string)(max(0.0, min(0.85, (float)($_POST['bg_overlay'] ?? 0.35))));
    $bgFixed     = isset($_POST['bg_fixed']) ? '1' : '0';
    $cardOpacity = (string)(max(0.35, min(1.0, (float)($_POST['card_opacity'] ?? 1.0))));

    // ---- 基础校验 ----
    if (str_length($siteName) > 100)    { $errors[] = '站点名称不超过 100 字。'; }
    if (str_length($tagline) > 200)     { $errors[] = '副标题不超过 200 字。'; }
    if (str_length($footerText) > 200)  { $errors[] = '页脚文字不超过 200 字。'; }
    if (!in_array($bgMode, ['default', 'color', 'gradient', 'image'], true)) {
        $errors[] = '背景模式不合法。';
    }
    if (!preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $accentColor)) { $errors[] = '主题色格式不正确。'; }
    if (!preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $bgColor))     { $errors[] = '背景色格式不正确。'; }
    if (!preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $gradA))       { $errors[] = '渐变起始色格式不正确。'; }
    if (!preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $gradB))       { $errors[] = '渐变结束色格式不正确。'; }

    // ---- 背景图：优先处理上传 ----
    $newBgImage = $bgImage;
    $file = $_FILES['bg_image_file'] ?? null;
    if ($file && is_array($file) && isset($file['error']) && $file['error'] === UPLOAD_ERR_OK && (int)$file['size'] > 0) {
        $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            $errors[] = '背景图仅支持 jpg / png / webp / gif 格式。';
        } elseif ((int)$file['size'] > 5 * 1024 * 1024) {
            $errors[] = '背景图不能超过 5MB。';
        } else {
            $uploadDir = __DIR__ . '/../assets/uploads/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }
            if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
                $errors[] = '上传目录不可写（public/assets/uploads/），请检查目录权限。';
            } else {
                $name = 'bg_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (!move_uploaded_file($file['tmp_name'], $uploadDir . $name)) {
                    $errors[] = '背景图保存失败，请重试。';
                } elseif (function_exists('getimagesize') && getimagesize($uploadDir . $name) === false) {
                    $errors[] = '上传的文件不是有效图片。';
                    @unlink($uploadDir . $name);
                } else {
                    $newBgImage = $UPLOAD_REL . $name;
                    // 删除被替换的旧本地上传图（仅当确实是本地上传且不是同一张）
                    $old = setting('bg_image');
                    if ($old !== $newBgImage && substr($old, 0, 16) === $UPLOAD_REL) {
                        $oldFile = __DIR__ . '/..' . $old;
                        if (is_file($oldFile)) {
                            @unlink($oldFile);
                        }
                    }
                }
            }
        }
    } else {
        // 未上传：校验手填地址（允许 http(s) 或本地上传目录）
        if ($bgImage !== '') {
            if (!preg_match('#^https?://#i', $bgImage) && substr($bgImage, 0, 16) !== $UPLOAD_REL) {
                $errors[] = '背景图地址仅支持 http/https 链接或 /assets/uploads/ 本地路径。';
            }
        }
        $newBgImage = $bgImage;
    }

    if ($errors) {
        flash_set('error', implode(' ', $errors));
        redirect('settings.php');
    }

    // ---- 保存 ----
    save_setting('site_name',    $siteName);
    save_setting('tagline',      $tagline);
    save_setting('footer_text',  $footerText);
    save_setting('accent_color', $accentColor);
    save_setting('bg_mode',      $bgMode);
    save_setting('bg_color',     $bgColor);
    save_setting('gradient_a',   $gradA);
    save_setting('gradient_b',   $gradB);
    save_setting('bg_image',     $newBgImage);
    save_setting('bg_overlay',   $bgOverlay);
    save_setting('bg_fixed',     $bgFixed);
    save_setting('card_opacity', $cardOpacity);

    flash_set('success', '外观设置已保存，刷新前台首页即可看到效果。');
    redirect('settings.php');
}

$pageTitle = '外观设置';
$active    = 'settings';
include __DIR__ . '/partials/header.php';
?>

<h1 class="page-title">外观设置</h1>

<div class="panel">
  <h2 class="panel-title">实时预览</h2>
  <div class="bg-preview" id="bg-preview">
    <div class="bg-preview-card">
      <span class="bg-preview-logo">🌐 <?= e(setting('site_name') ?: '上网入口') ?></span>
      <span class="bg-preview-chip" id="accent-chip">主题色</span>
    </div>
  </div>
  <p class="form-hint">以下预览会根据右侧表单实时变化（背景图选择后即可看到效果）。</p>
</div>

<form method="post" action="settings.php" enctype="multipart/form-data">
  <?= csrf_field() ?>

  <div class="panel">
    <h2 class="panel-title">基本设置</h2>
    <div class="form-row">
      <div class="form-col">
        <label for="site_name">站点名称</label>
        <input class="input" type="text" id="site_name" name="site_name" maxlength="100"
               value="<?= e(setting('site_name')) ?>" placeholder="留空使用默认「上网入口」">
      </div>
      <div class="form-col">
        <label for="tagline">首页副标题</label>
        <input class="input" type="text" id="tagline" name="tagline" maxlength="200"
               value="<?= e(setting('tagline')) ?>" placeholder="如：把常用网站，装进一个页面">
      </div>
    </div>
    <div class="form-row">
      <div class="form-col form-col-wide">
        <label for="footer_text">页脚文字</label>
        <input class="input" type="text" id="footer_text" name="footer_text" maxlength="200"
               value="<?= e(setting('footer_text')) ?>" placeholder="留空使用默认页脚">
      </div>
    </div>
  </div>

  <div class="panel">
    <h2 class="panel-title">主题色</h2>
    <div class="swatch-group">
      <?php $currentAccent = strtolower(setting('accent_color', '#2563eb')); ?>
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
    <p class="form-hint">主题色会应用到按钮、链接、标题、图标等整站元素。</p>
    <input type="hidden" name="accent_color_manual" id="accent_color_manual" value="<?= e($currentAccent) ?>">
  </div>

  <div class="panel">
    <h2 class="panel-title">背景</h2>
    <?php $bgMode = setting('bg_mode', 'default'); ?>

    <div class="bg-mode-row">
      <label class="radio-label"><input type="radio" name="bg_mode" value="default"  <?= $bgMode === 'default' ? 'checked' : '' ?>> 默认浅色</label>
      <label class="radio-label"><input type="radio" name="bg_mode" value="color"    <?= $bgMode === 'color' ? 'checked' : '' ?>> 纯色</label>
      <label class="radio-label"><input type="radio" name="bg_mode" value="gradient" <?= $bgMode === 'gradient' ? 'checked' : '' ?>> 渐变</label>
      <label class="radio-label"><input type="radio" name="bg_mode" value="image"    <?= $bgMode === 'image' ? 'checked' : '' ?>> 背景图</label>
    </div>

    <div class="bg-fields bg-field-color" <?= $bgMode === 'color' ? '' : 'style="display:none"' ?>>
      <label for="bg_color">背景颜色</label>
      <input type="color" id="bg_color" name="bg_color" value="<?= e(sanitize_css_color(setting('bg_color'), '#f3f5f9')) ?>">
    </div>

    <div class="bg-fields bg-field-gradient" <?= $bgMode === 'gradient' ? '' : 'style="display:none"' ?>>
      <label for="gradient_a">渐变起始色</label>
      <input type="color" id="gradient_a" name="gradient_a" value="<?= e(sanitize_css_color(setting('gradient_a'), '#60a5fa')) ?>">
      <label for="gradient_b">渐变结束色</label>
      <input type="color" id="gradient_b" name="gradient_b" value="<?= e(sanitize_css_color(setting('gradient_b'), '#8b5cf6')) ?>">
    </div>

    <div class="bg-fields bg-field-image" <?= $bgMode === 'image' ? '' : 'style="display:none"' ?>>
      <div class="form-row">
        <div class="form-col form-col-wide">
          <label for="bg_image">背景图地址</label>
          <input class="input" type="text" id="bg_image" name="bg_image" maxlength="500"
                 value="<?= e(setting('bg_image')) ?>" placeholder="https://… 或留空仅用下方上传">
        </div>
        <div class="form-col">
          <label for="bg_image_file">或上传图片（≤5MB）</label>
          <input class="input" type="file" id="bg_image_file" name="bg_image_file" accept="image/jpeg,image/png,image/webp,image/gif">
        </div>
      </div>
      <div class="form-row">
        <label class="checkbox-label"><input type="checkbox" name="bg_fixed" value="1" <?= setting('bg_fixed', '1') === '1' ? 'checked' : '' ?>> 背景固定（滚动视差效果）</label>
        <div class="overlay-field">
          <label for="bg_overlay">白色遮罩透明度：<span id="overlay_value"><?= e(setting('bg_overlay', '0.35')) ?></span></label>
          <input type="range" id="bg_overlay" name="bg_overlay" min="0" max="0.85" step="0.05"
                 value="<?= e(setting('bg_overlay', '0.35')) ?>">
        </div>
      </div>
      <p class="form-hint">上传的图片保存在 /assets/uploads/ 目录；重新上传会替换并清理旧文件。</p>
    </div>
  </div>

  <div class="panel">
    <h2 class="panel-title">卡片外观</h2>
    <div class="overlay-field">
      <label for="card_opacity">链接卡片透明度：<span id="card_opacity_value"><?= e(setting('card_opacity', '1')) ?></span></label>
      <input type="range" id="card_opacity" name="card_opacity" min="0.35" max="1" step="0.05"
             value="<?= e(setting('card_opacity', '1')) ?>">
    </div>
    <p class="form-hint">1 为不透明（默认）。调低后卡片半透明，在背景图 / 渐变模式下更通透；搜索结果的卡片同步生效。</p>
  </div>

  <div class="panel">
    <button class="btn btn-primary" type="submit">保存设置</button>
    <a class="btn btn-outline" href="../index.php" target="_blank">查看前台效果</a>
  </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var accentCustom = document.getElementById('accent_custom');
  var accentManual = document.getElementById('accent_color_manual');
  var cardSlider = document.getElementById('card_opacity');
  var cardValue = document.getElementById('card_opacity_value');

  // 主题色：预设单选或自定义取色器
  function syncSwatches() {
    document.querySelectorAll('.swatch').forEach(function (sw) {
      var radio = sw.querySelector('input[type="radio"]');
      sw.classList.toggle('active', !!(radio && radio.checked));
    });
  }
  document.querySelectorAll('input[name="accent_color"]').forEach(function (radio) {
    radio.addEventListener('change', function () {
      if (radio.value === 'custom') {
        accentManual.value = accentCustom.value;
      } else {
        accentManual.value = radio.value;
      }
      syncSwatches();
      updatePreview();
    });
  });
  // 兜底：某些环境下点击 label 不触发原生选中时，强制选中并派发 change
  document.querySelectorAll('.swatch').forEach(function (sw) {
    sw.addEventListener('click', function () {
      var radio = sw.querySelector('input[type="radio"]');
      if (radio && !radio.checked) {
        radio.checked = true;
        radio.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });
  });
  if (accentCustom) {
    accentCustom.addEventListener('input', function () {
      document.getElementById('accent_custom_radio').checked = true;
      accentManual.value = accentCustom.value;
      updatePreview();
    });
  }

  // 背景模式切换显隐
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
    document.getElementById('overlay_value').textContent = overlay.value;
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
    var accent = accentManual.value;
    var chip = document.getElementById('accent-chip');
    if (chip && accent) chip.style.background = accent;
    applyCardOpacity();
  }
  function applyCardOpacity() {
    var card = document.querySelector('.bg-preview-card');
    if (card && cardSlider) card.style.opacity = cardSlider.value;
  }
  syncSwatches();
  updatePreview();
});
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
