<?php
/**
 * 后台 - 链接管理：列表（筛选/分页）/ 新增 / 编辑 / 启停 / 删除
 */
require_once __DIR__ . '/../../app/init.php';
require_admin();

$db = db();
ensure_links_pinned();

// ---- CSV 导出（GET，无需表单提交）----
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $rows = $db->query(
        'SELECT c.name AS category, l.title, l.url, l.description, l.icon, l.sort_order, l.status, l.is_pinned
         FROM links l
         JOIN categories c ON c.id = l.category_id
         ORDER BY c.sort_order ASC, l.is_pinned DESC, l.sort_order ASC, l.id ASC'
    )->fetchAll();

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="links_' . date('Ymd_His') . '.csv"');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM，方便 Excel 正确识别中文
    $out = fopen('php://output', 'w');
    fputcsv($out, ['category', 'title', 'url', 'description', 'icon', 'sort_order', 'status', 'pinned']);
    foreach ($rows as $row) {
        fputcsv($out, [
            $row['category'],
            $row['title'],
            $row['url'],
            $row['description'],
            $row['icon'],
            (int)$row['sort_order'],
            (int)$row['status'],
            (int)$row['is_pinned'],
        ]);
    }
    fclose($out);
    exit;
}

// ---- POST 处理 ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');
    $id     = (int)($_POST['id'] ?? 0);

    if (in_array($action, ['add', 'edit'], true)) {
        $errors = [];
        $title       = trim((string)($_POST['title'] ?? ''));
        $url         = normalize_url((string)($_POST['url'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $icon        = trim((string)($_POST['icon'] ?? ''));
        $categoryId  = (int)($_POST['category_id'] ?? 0);
        $order       = (int)($_POST['sort_order'] ?? 0);
        $pinned      = isset($_POST['is_pinned']) ? 1 : 0;
        $status      = isset($_POST['status']) ? 1 : 0;

        // 图标图片上传（优先于文本框内容）
        $iconFile = $_FILES['icon_file'] ?? null;
        if ($iconFile && is_array($iconFile) && ($iconFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && (int)$iconFile['size'] > 0) {
            $iconExt = strtolower(pathinfo((string)$iconFile['name'], PATHINFO_EXTENSION));
            if (!in_array($iconExt, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
                $errors[] = '图标仅支持 jpg / png / webp / gif 图片。';
            } elseif ((int)$iconFile['size'] > 2 * 1024 * 1024) {
                $errors[] = '图标图片不能超过 2MB。';
            } else {
                $uploadDir = __DIR__ . '/../assets/uploads/';
                if (!is_dir($uploadDir)) {
                    @mkdir($uploadDir, 0755, true);
                }
                if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
                    $errors[] = '上传目录不可写（public/assets/uploads/），请检查目录权限。';
                } else {
                    $iconName = 'icon_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $iconExt;
                    if (!move_uploaded_file($iconFile['tmp_name'], $uploadDir . $iconName)) {
                        $errors[] = '图标保存失败，请重试。';
                    } elseif (function_exists('getimagesize') && getimagesize($uploadDir . $iconName) === false) {
                        $errors[] = '上传的图标不是有效图片。';
                        @unlink($uploadDir . $iconName);
                    } else {
                        $icon = '/assets/uploads/' . $iconName;
                    }
                }
            }
        }

        if ($title === '' || str_length($title) > 150) {
            $errors[] = '链接标题不能为空且不超过 150 字。';
        }
        if ($url === '') {
            $errors[] = '链接地址不合法（仅支持 http/https）。';
        }
        if (str_length($description) > 500) {
            $errors[] = '描述不超过 500 字。';
        }
        if (str_length($icon) > 255) {
            $errors[] = '图标内容不超过 255 字。';
        }
        $catCheck = $db->prepare('SELECT id FROM categories WHERE id = ?');
        $catCheck->execute([$categoryId]);
        if ($catCheck->fetch() === false) {
            $errors[] = '请选择有效的分类。';
        }

        if ($errors) {
            flash_set('error', implode(' ', $errors));
            $redirectQuery = ($action === 'edit' && $id > 0) ? '?edit=' . $id : '';
            redirect('link.php' . $redirectQuery);
        }

        if ($action === 'add') {
            $db->prepare(
                'INSERT INTO links (category_id, title, url, description, icon, sort_order, is_pinned, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([$categoryId, $title, $url, $description, $icon, $order, $pinned, $status]);
            flash_set('success', '链接「' . $title . '」已添加。');
        } else {
            // 更新前记录旧图标，用于清理被替换/移除的本站上传文件
            $oldIcon = '';
            if ($id > 0) {
                $oldStmt = $db->prepare('SELECT icon FROM links WHERE id = ?');
                $oldStmt->execute([$id]);
                $oldIcon = (string)$oldStmt->fetchColumn();
            }
            $db->prepare(
                'UPDATE links SET category_id = ?, title = ?, url = ?, description = ?, icon = ?, sort_order = ?, is_pinned = ?, status = ?
                 WHERE id = ?'
            )->execute([$categoryId, $title, $url, $description, $icon, $order, $pinned, $status, $id]);
            if (substr($oldIcon, 0, 21) === '/assets/uploads/icon_' && $oldIcon !== $icon) {
                $oldFile = __DIR__ . '/..' . $oldIcon;
                if (is_file($oldFile)) {
                    @unlink($oldFile);
                }
            }
            flash_set('success', '链接已更新。');
        }
    } elseif ($action === 'toggle' && $id > 0) {
        $db->prepare('UPDATE links SET status = 1 - status WHERE id = ?')->execute([$id]);
        flash_set('success', '链接状态已切换。');
    } elseif ($action === 'pin' && $id > 0) {
        $db->prepare('UPDATE links SET is_pinned = 1 - is_pinned WHERE id = ?')->execute([$id]);
        flash_set('success', '链接置顶状态已切换。');
    } elseif ($action === 'delete' && $id > 0) {
        $db->prepare('DELETE FROM links WHERE id = ?')->execute([$id]);
        flash_set('success', '链接已删除（相关点击记录一并清除）。');
    } elseif ($action === 'fetch_icon' && $id > 0) {
        // ---- favicon 自动抓取：解析首页 <link rel="icon"> 或兜底 /favicon.ico ----
        $stmt = $db->prepare('SELECT url FROM links WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row === false) {
            flash_set('error', '链接不存在。');
        } else {
            $linkUrl = normalize_url((string)$row['url']);
            $scheme  = strtolower((string)parse_url($linkUrl, PHP_URL_SCHEME));
            $host    = (string)parse_url($linkUrl, PHP_URL_HOST);
            if (!in_array($scheme, ['http', 'https'], true) || $host === ''
                || strtolower($host) === 'localhost'
                || preg_match('/^\d+(\.\d+){3}$/', $host)) {
                flash_set('error', '无法解析该链接域名（不支持本机/IP 直连地址）。');
            } else {
                $fetched = fetch_favicon($scheme, $host);
                if ($fetched[0] === null) {
                    flash_set('error', '图标抓取失败：' . $fetched[1]);
                } else {
                    $uploadDir = __DIR__ . '/../assets/uploads/';
                    if (!is_dir($uploadDir)) {
                        @mkdir($uploadDir, 0755, true);
                    }
                    if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
                        flash_set('error', '上传目录不可写（public/assets/uploads/），请检查目录权限。');
                    } else {
                        $iconName = 'icon_' . preg_replace('/[^a-z0-9]/i', '', $host) . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $fetched[1];
                        if (file_put_contents($uploadDir . $iconName, $fetched[0]) === false) {
                            flash_set('error', '图标保存失败，请检查上传目录权限。');
                        } else {
                            $oldStmt = $db->prepare('SELECT icon FROM links WHERE id = ?');
                            $oldStmt->execute([$id]);
                            $oldIcon = (string)$oldStmt->fetchColumn();
                            $newIcon = '/assets/uploads/' . $iconName;
                            $db->prepare('UPDATE links SET icon = ? WHERE id = ?')->execute([$newIcon, $id]);
                            if (substr($oldIcon, 0, 21) === '/assets/uploads/icon_' && $oldIcon !== $newIcon) {
                                $oldFile = __DIR__ . '/..' . $oldIcon;
                                if (is_file($oldFile)) {
                                    @unlink($oldFile);
                                }
                            }
                            flash_set('success', '已自动抓取并设置图标（' . $host . '）。');
                        }
                    }
                }
            }
        }
        redirect('link.php');
    } elseif ($action === 'import') {
        // ---- CSV 批量导入 ----
        $file = $_FILES['csv_file'] ?? null;
        if (!$file || !is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || (int)$file['size'] <= 0) {
            flash_set('error', '请选择要导入的 CSV 文件。');
        } elseif ((int)$file['size'] > 5 * 1024 * 1024) {
            flash_set('error', 'CSV 文件不能超过 5MB。');
        } else {
            $raw = (string)file_get_contents($file['tmp_name']);
            if (substr($raw, 0, 3) === "\xEF\xBB\xBF") {
                $raw = substr($raw, 3); // 去掉 UTF-8 BOM
            }
            if (function_exists('mb_check_encoding') && !mb_check_encoding($raw, 'UTF-8')) {
                // Excel 常见 GBK 编码，转换为 UTF-8（无 mbstring 时跳过转换）
                $raw = function_exists('mb_convert_encoding') ? mb_convert_encoding($raw, 'UTF-8', 'GB18030') : $raw;
            }
            $tmp = tempnam(sys_get_temp_dir(), 'csv');
            file_put_contents($tmp, $raw);
            $fh = fopen($tmp, 'r');

            $catMap = [];
            foreach ($db->query('SELECT id, name FROM categories') as $c) {
                $catMap[strtolower(trim($c['name']))] = (int)$c['id'];
            }
            $insLink = $db->prepare(
                'INSERT INTO links (category_id, title, url, description, icon, sort_order, is_pinned, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $okCount = 0;
            $createdCats = 0;
            $failList = [];
            $lineNo = 0;
            while (($cols = fgetcsv($fh)) !== false) {
                $lineNo++;
                if ($lineNo === 1 && isset($cols[0]) && strtolower(trim($cols[0])) === 'category') {
                    continue; // 跳过表头
                }
                $cols = array_map(function ($v) { return trim((string)$v); }, $cols);
                if (implode('', $cols) === '') {
                    continue; // 空行
                }
                $catName = $cols[0] ?? '';
                $title   = $cols[1] ?? '';
                $url     = $cols[2] ?? '';
                $desc    = $cols[3] ?? '';
                $icon    = $cols[4] ?? '';
                $order   = (isset($cols[5]) && $cols[5] !== '') ? (int)$cols[5] : 0;
                $status  = (isset($cols[6]) && in_array(strtolower($cols[6]), ['0', '停用', 'false', 'no'], true)) ? 0 : 1;
                $pinned  = (isset($cols[7]) && in_array(strtolower($cols[7]), ['1', '置顶', 'true', 'yes'], true)) ? 1 : 0;

                $errs = [];
                if ($title === '' || str_length($title) > 150) {
                    $errs[] = '标题为空或超长';
                }
                $url = normalize_url($url);
                if ($url === '') {
                    $errs[] = '网址无效';
                }
                if ($catName === '') {
                    $errs[] = '分类为空';
                    $catId = 0;
                } else {
                    $catId = $catMap[strtolower($catName)] ?? null;
                    if ($catId === null) {
                        $db->prepare('INSERT INTO categories (name, sort_order) VALUES (?, 0)')
                            ->execute([str_cut($catName, 100)]);
                        $catId = (int)$db->lastInsertId();
                        $catMap[strtolower($catName)] = $catId;
                        $createdCats++;
                    }
                }
                if (str_length($desc) > 500) {
                    $errs[] = '描述超长';
                }
                if (str_length($icon) > 255) {
                    $errs[] = '图标超长';
                }
                if ($errs) {
                    $failList[] = '第 ' . $lineNo . ' 行（' . $title . '）：' . implode('、', $errs);
                    continue;
                }
                $insLink->execute([$catId, $title, $url, $desc, $icon, $order, $pinned, $status]);
                $okCount++;
            }
            fclose($fh);
            @unlink($tmp);

            $msg = '成功导入 ' . $okCount . ' 条链接';
            if ($createdCats > 0) {
                $msg .= '，自动创建分类 ' . $createdCats . ' 个';
            }
            if ($failList) {
                $msg .= '；失败 ' . count($failList) . ' 条（最多显示 10 条）：' . implode('；', array_slice($failList, 0, 10));
            }
            flash_set($okCount > 0 ? 'success' : 'error', $msg);
        }
        redirect('link.php');
    } else {
        flash_set('error', '未知操作。');
    }
    redirect('link.php');
}

// ---- 编辑模式：读取要编辑的链接 ----
$editLink = null;
$editId   = (int)($_GET['edit'] ?? 0);
if ($editId > 0) {
    $stmt = $db->prepare('SELECT * FROM links WHERE id = ?');
    $stmt->execute([$editId]);
    $editLink = $stmt->fetch();
    if ($editLink === false) {
        $editId = 0;
    }
}

// ---- 列表数据（筛选 + 分页）----
$catFilter = (int)($_GET['cat'] ?? 0);
$keyword   = trim((string)($_GET['q'] ?? ''));
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 30;

$where  = ['1=1'];
$params = [];
if ($catFilter > 0) {
    $where[]  = 'l.category_id = ?';
    $params[] = $catFilter;
}
if ($keyword !== '') {
    $where[]  = '(l.title LIKE ? OR l.url LIKE ? OR l.description LIKE ?)';
    $like     = '%' . $keyword . '%';
    array_push($params, $like, $like, $like);
}
$whereSql = implode(' AND ', $where);

$totalStmt = $db->prepare("SELECT COUNT(*) FROM links l WHERE $whereSql");
$totalStmt->execute($params);
$total   = (int)$totalStmt->fetchColumn();
$maxPage = max(1, (int)ceil($total / $perPage));
$page    = min($page, $maxPage);
$offset  = ($page - 1) * $perPage;

$listStmt = $db->prepare(
    "SELECT l.*, c.name AS category_name,
            (SELECT COUNT(*) FROM click_logs cl WHERE cl.link_id = l.id) AS click_count
     FROM links l
     JOIN categories c ON c.id = l.category_id
     WHERE $whereSql
     ORDER BY l.is_pinned DESC, l.sort_order ASC, l.id ASC
     LIMIT $perPage OFFSET $offset"
);
$listStmt->execute($params);
$links = $listStmt->fetchAll();

$categories = $db->query(
    'SELECT id, name, status FROM categories ORDER BY sort_order ASC, id ASC'
)->fetchAll();

// 生成分页链接
function admin_page_url(int $page, int $cat, string $q): string
{
    $query = http_build_query(array_filter([
        'page' => $page > 1 ? $page : null,
        'cat'  => $cat > 0 ? $cat : null,
        'q'    => $q !== '' ? $q : null,
    ]));
    return 'link.php' . ($query !== '' ? '?' . $query : '');
}

$pageTitle = '链接管理';
$active    = 'link';
include __DIR__ . '/partials/header.php';
?>

<h1 class="page-title">链接管理</h1>

<div class="panel">
  <h2 class="panel-title"><?= $editLink ? '编辑链接 #' . (int)$editLink['id'] : '新增链接' ?></h2>
  <form method="post" action="link.php" class="link-form" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $editLink ? 'edit' : 'add' ?>">
    <?php if ($editLink): ?><input type="hidden" name="id" value="<?= (int)$editLink['id'] ?>"><?php endif; ?>

    <div class="form-row">
      <div class="form-col">
        <label for="title">标题 *</label>
        <input class="input" type="text" id="title" name="title" required maxlength="150"
               value="<?= e($editLink['title'] ?? '') ?>" placeholder="如：百度搜索">
      </div>
      <div class="form-col">
        <label for="category_id">分类 *</label>
        <select class="input" id="category_id" name="category_id" required>
          <option value="">请选择分类</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= (int)$cat['id'] ?>" <?= ((int)($editLink['category_id'] ?? 0) === (int)$cat['id']) ? 'selected' : '' ?>>
              <?= e($cat['name']) ?><?= (int)$cat['status'] !== 1 ? '（已停用）' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-row">
      <div class="form-col form-col-wide">
        <label for="url">网址 *</label>
        <input class="input" type="text" id="url" name="url" required maxlength="500"
               value="<?= e($editLink['url'] ?? '') ?>" placeholder="https://www.example.com">
        <p class="form-hint">支持 http/https，可省略协议前缀自动补全</p>
      </div>
      <div class="form-col">
        <label for="icon">图标（可选）</label>
        <input class="input" type="text" id="icon" name="icon" maxlength="255"
               value="<?= e($editLink['icon'] ?? '') ?>" placeholder="emoji 或图片地址">
        <input class="input" type="file" id="icon_file" name="icon_file" accept="image/jpeg,image/png,image/webp,image/gif">
        <p class="form-hint">上传图片（≤2MB）自动保存到 /assets/uploads/，优先于上方文本；留空文本并保存可移除图标</p>
      </div>
    </div>

    <div class="form-row">
      <div class="form-col form-col-wide">
        <label for="description">描述（可选）</label>
        <input class="input" type="text" id="description" name="description" maxlength="500"
               value="<?= e($editLink['description'] ?? '') ?>" placeholder="一句话说明这个网站">
      </div>
      <div class="form-col">
        <label for="sort_order">排序</label>
        <input class="input" type="number" id="sort_order" name="sort_order" min="0"
               value="<?= (int)($editLink['sort_order'] ?? 0) ?>">
      </div>
    </div>

    <div class="form-row form-row-bottom">
      <div class="checkbox-group">
        <label class="checkbox-label">
          <input type="checkbox" name="status" value="1"
                 <?= ($editLink === null || (int)$editLink['status'] === 1) ? 'checked' : '' ?>>
          启用（立即在前台展示）
        </label>
        <label class="checkbox-label">
          <input type="checkbox" name="is_pinned" value="1"
                 <?= ($editLink !== null && (int)$editLink['is_pinned'] === 1) ? 'checked' : '' ?>>
          置顶（排在分类最前）
        </label>
      </div>
      <div class="form-actions">
        <?php if ($editLink): ?>
          <a class="btn btn-outline" href="link.php">取消编辑</a>
        <?php endif; ?>
        <button class="btn btn-primary" type="submit"><?= $editLink ? '保存修改' : '添加链接' ?></button>
      </div>
    </div>
  </form>
</div>

<div class="panel">
  <h2 class="panel-title">批量导入 / 导出</h2>
  <div class="form-row">
    <form class="form-inline" method="post" action="link.php" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="import">
      <input class="input input-sm" type="file" name="csv_file" accept=".csv,text/csv" required>
      <button class="btn btn-primary btn-sm" type="submit">导入 CSV</button>
    </form>
    <a class="btn btn-outline btn-sm" href="link.php?export=csv">导出 CSV（全部链接）</a>
  </div>
  <p class="form-hint">
    CSV 列顺序：<code>分类,标题,网址,描述,图标,排序,状态,置顶</code>（首行为表头可省略）。
    分类不存在会自动创建；支持 Excel 导出的 UTF-8 / GBK 编码；状态 1/启用=显示，0/停用=隐藏；置顶 1=置顶。
  </p>
</div>

<div class="panel">
  <h2 class="panel-title">链接列表（共 <?= $total ?> 条）</h2>

  <form class="form-inline filter-bar" method="get" action="link.php">
    <select class="input input-sm" name="cat">
      <option value="0">全部分类</option>
      <?php foreach ($categories as $cat): ?>
        <option value="<?= (int)$cat['id'] ?>" <?= $catFilter === (int)$cat['id'] ? 'selected' : '' ?>>
          <?= e($cat['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <input class="input input-sm" type="text" name="q" value="<?= e($keyword) ?>" placeholder="搜索标题 / 网址 / 描述">
    <button class="btn btn-outline btn-sm" type="submit">筛选</button>
    <?php if ($catFilter > 0 || $keyword !== ''): ?>
      <a class="btn btn-ghost btn-sm" href="link.php">清除筛选</a>
    <?php endif; ?>
  </form>

  <?php if (!$links): ?>
    <p class="empty-tip">没有符合条件的链接。</p>
  <?php else: ?>
    <div class="table-scroll">
      <table class="table">
        <thead>
          <tr><th>ID</th><th>标题</th><th>分类</th><th>网址</th><th>排序</th><th>点击</th><th>状态</th><th>操作</th></tr>
        </thead>
        <tbody>
        <?php foreach ($links as $link): ?>
          <tr>
            <td><?= (int)$link['id'] ?></td>
            <td class="cell-title">
              <?= link_icon_html($link['icon'] ?? '', '') ?> <?= e($link['title']) ?>
              <?php if ((int)$link['is_pinned'] === 1): ?>
                <span class="badge badge-pin">置顶</span>
              <?php endif; ?>
              <?php if ($link['description'] !== ''): ?>
                <div class="cell-sub"><?= e($link['description']) ?></div>
              <?php endif; ?>
            </td>
            <td><?= e($link['category_name']) ?></td>
            <td class="cell-muted cell-url"><?= e($link['url']) ?></td>
            <td><?= (int)$link['sort_order'] ?></td>
            <td><?= (int)$link['click_count'] ?></td>
            <td>
              <?php if ((int)$link['status'] === 1): ?>
                <span class="badge badge-on">启用</span>
              <?php else: ?>
                <span class="badge badge-off">停用</span>
              <?php endif; ?>
            </td>
            <td class="cell-actions">
              <a class="btn btn-outline btn-sm" href="link.php?edit=<?= (int)$link['id'] ?>">编辑</a>
              <form class="form-inline" method="post" action="link.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="fetch_icon">
                <input type="hidden" name="id" value="<?= (int)$link['id'] ?>">
                <button class="btn btn-outline btn-sm" type="submit" title="自动抓取该网站 favicon 作为图标">抓图标</button>
              </form>
              <form class="form-inline" method="post" action="link.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="pin">
                <input type="hidden" name="id" value="<?= (int)$link['id'] ?>">
                <button class="btn btn-outline btn-sm" type="submit"><?= (int)$link['is_pinned'] === 1 ? '取消置顶' : '置顶' ?></button>
              </form>
              <form class="form-inline" method="post" action="link.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?= (int)$link['id'] ?>">
                <button class="btn btn-outline btn-sm" type="submit"><?= (int)$link['status'] === 1 ? '停用' : '启用' ?></button>
              </form>
              <form class="form-inline" method="post" action="link.php" data-confirm="确定删除「<?= e($link['title']) ?>」？相关点击记录将一并删除。">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$link['id'] ?>">
                <button class="btn btn-danger btn-sm" type="submit">删除</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($maxPage > 1): ?>
      <div class="pagination">
        <?php if ($page > 1): ?>
          <a class="page-btn" href="<?= e(admin_page_url(1, $catFilter, $keyword)) ?>">« 首页</a>
          <a class="page-btn" href="<?= e(admin_page_url($page - 1, $catFilter, $keyword)) ?>">‹ 上一页</a>
        <?php endif; ?>
        <span class="page-info">第 <?= $page ?> / <?= $maxPage ?> 页</span>
        <?php if ($page < $maxPage): ?>
          <a class="page-btn" href="<?= e(admin_page_url($page + 1, $catFilter, $keyword)) ?>">下一页 ›</a>
          <a class="page-btn" href="<?= e(admin_page_url($maxPage, $catFilter, $keyword)) ?>">末页 »</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
