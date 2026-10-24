<?php
/**
 * 后台 - 用户管理：列表 / 新增 / 重置密码 / 修改角色 / 启停账号
 * 安全保护：不允许对自己停用或取消管理员角色。
 */
require_once __DIR__ . '/../../app/init.php';
require_admin();

$db   = db();
$self = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'add') {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $email    = strtolower(trim((string)($_POST['email'] ?? '')));
        if (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $username)) {
            flash_set('error', '用户名需为 3-20 位字母、数字或下划线。');
        } elseif (str_length($password) < 6) {
            flash_set('error', '密码长度至少 6 位。');
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash_set('error', '邮箱格式不正确。');
        } else {
            $exists = $db->prepare('SELECT id FROM users WHERE username = ?');
            $exists->execute([$username]);
            if ($exists->fetch() !== false) {
                flash_set('error', '该用户名已存在。');
            } elseif ($email !== '') {
                $exists = $db->prepare('SELECT id FROM users WHERE email = ?');
                $exists->execute([$email]);
                if ($exists->fetch() !== false) {
                    flash_set('error', '该邮箱已被其他用户使用。');
                } else {
                    $db->prepare('INSERT INTO users (username, password_hash, email, role, status) VALUES (?, ?, ?, ?, 1)')
                        ->execute([$username, password_hash($password, PASSWORD_DEFAULT), $email, 'user']);
                    flash_set('success', '用户「' . $username . '」已创建。');
                }
            } else {
                $db->prepare('INSERT INTO users (username, password_hash, role, status) VALUES (?, ?, ?, 1)')
                    ->execute([$username, password_hash($password, PASSWORD_DEFAULT), 'user']);
                flash_set('success', '用户「' . $username . '」已创建。');
            }
        }
    } elseif ($action === 'edit_contact' && $id > 0) {
        $email    = strtolower(trim((string)($_POST['email'] ?? '')));
        $phone    = trim((string)($_POST['phone'] ?? ''));
        $realName = trim((string)($_POST['real_name'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash_set('error', '邮箱格式不正确。');
        } elseif (str_length($realName) > 50) {
            flash_set('error', '姓名不超过 50 字。');
        } elseif (str_length($phone) > 20) {
            flash_set('error', '手机号不超过 20 位。');
        } else {
            $exists = $db->prepare('SELECT id FROM users WHERE email = ? AND id <> ?');
            $exists->execute([$email, $id]);
            if ($email !== '' && $exists->fetch() !== false) {
                flash_set('error', '该邮箱已被其他用户使用。');
            } else {
                $db->prepare('UPDATE users SET email = ?, email_verified = 0, phone = ?, real_name = ? WHERE id = ?')
                    ->execute([$email !== '' ? $email : null, $phone, $realName, $id]);
                flash_set('success', '用户资料已更新。');
            }
        }
    } elseif ($action === 'reset_pass' && $id > 0) {
        $password = (string)($_POST['password'] ?? '');
        if (str_length($password) < 6) {
            flash_set('error', '新密码长度至少 6 位。');
        } else {
            $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
            flash_set('success', '密码已重置。');
        }
    } elseif ($action === 'toggle_role' && $id > 0) {
        if ($id === (int)$self['id']) {
            flash_set('error', '不能修改自己的管理员角色。');
        } else {
            $db->prepare('UPDATE users SET role = IF(role = \'admin\', \'user\', \'admin\') WHERE id = ?')->execute([$id]);
            flash_set('success', '角色已切换。');
        }
    } elseif ($action === 'toggle_status' && $id > 0) {
        if ($id === (int)$self['id']) {
            flash_set('error', '不能停用当前登录的账号。');
        } else {
            $db->prepare('UPDATE users SET status = 1 - status WHERE id = ?')->execute([$id]);
            flash_set('success', '账号状态已切换。');
        }
    } else {
        flash_set('error', '未知操作。');
    }
    redirect('user.php');
}

$users = $db->query(
    'SELECT id, username, email, email_verified, phone, real_name, role, status, created_at, last_login_at FROM users ORDER BY id ASC'
)->fetchAll();

$pageTitle = '用户管理';
$active    = 'user';
include __DIR__ . '/partials/header.php';
?>

<h1 class="page-title">用户管理</h1>

<div class="panel">
  <h2 class="panel-title">新增用户</h2>
  <form class="form-inline" method="post" action="user.php">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add">
    <input class="input input-sm" type="text" name="username" placeholder="用户名（3-20 位字母数字下划线）" required maxlength="20" pattern="[A-Za-z0-9_]{3,20}">
    <input class="input input-sm" type="password" name="password" placeholder="初始密码（至少 6 位）" required minlength="6">
    <input class="input input-sm" type="email" name="email" placeholder="邮箱（选填）" maxlength="191">
    <button class="btn btn-primary btn-sm" type="submit">创建用户</button>
  </form>
</div>

<div class="panel">
  <h2 class="panel-title">用户列表</h2>
  <?php if (!$users): ?>
    <p class="empty-tip">暂无用户。</p>
  <?php else: ?>
    <div class="table-scroll">
      <table class="table">
        <thead>
          <tr><th>ID</th><th>用户名</th><th>联系方式</th><th>角色</th><th>状态</th><th>注册时间</th><th>最后登录</th><th>操作</th></tr>
        </thead>
        <tbody>
        <?php foreach ($users as $user): ?>
          <tr>
            <td><?= (int)$user['id'] ?></td>
            <td>
              <?= e($user['username']) ?>
              <?php if ((int)$user['id'] === (int)$self['id']): ?>
                <span class="badge badge-on">当前账号</span>
              <?php endif; ?>
            </td>
            <td>
              <form class="form-inline" method="post" action="user.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="edit_contact">
                <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
                <input class="input input-sm" type="email" name="email" value="<?= e($user['email'] ?? '') ?>" placeholder="邮箱" maxlength="191" style="width:150px">
                <input class="input input-sm" type="text" name="phone" value="<?= e($user['phone'] ?? '') ?>" placeholder="手机" maxlength="20" style="width:100px">
                <input class="input input-sm" type="text" name="real_name" value="<?= e($user['real_name'] ?? '') ?>" placeholder="姓名" maxlength="50" style="width:90px">
                <button class="btn btn-outline btn-sm" type="submit">保存资料</button>
              </form>
              <?php if ($user['email'] !== '' && $user['email'] !== null): ?>
                <?php if ((int)($user['email_verified'] ?? 0) === 1): ?>
                  <span class="badge badge-on">邮箱已验证</span>
                <?php else: ?>
                  <span class="badge badge-off">邮箱未验证</span>
                <?php endif; ?>
              <?php endif; ?>
            </td>
            <td>
              <?php if ((int)$user['status'] === 1): ?>
                <span class="badge badge-on">正常</span>
              <?php else: ?>
                <span class="badge badge-off">已禁用</span>
              <?php endif; ?>
            </td>
            <td class="cell-muted"><?= e($user['created_at']) ?></td>
            <td class="cell-muted"><?= $user['last_login_at'] ? e($user['last_login_at']) : '-' ?></td>
            <td class="cell-actions">
              <form class="form-inline" method="post" action="user.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reset_pass">
                <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
                <input class="input input-sm input-pwd" type="password" name="password" placeholder="新密码" required minlength="6">
                <button class="btn btn-outline btn-sm" type="submit">重置密码</button>
              </form>
              <form class="form-inline" method="post" action="user.php" <?= ((int)$user['id'] === (int)$self['id']) ? 'data-disabled="1"' : '' ?>>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle_role">
                <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
                <button class="btn btn-outline btn-sm" type="submit" <?= ((int)$user['id'] === (int)$self['id']) ? 'disabled' : '' ?>>
                  <?= $user['role'] === 'admin' ? '取消管理员' : '设为管理员' ?>
                </button>
              </form>
              <form class="form-inline" method="post" action="user.php" data-confirm="确定<?= (int)$user['status'] === 1 ? '禁用' : '启用' ?>账号「<?= e($user['username']) ?>」？">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle_status">
                <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
                <button class="btn btn-outline btn-sm" type="submit" <?= ((int)$user['id'] === (int)$self['id']) ? 'disabled' : '' ?>>
                  <?= (int)$user['status'] === 1 ? '禁用' : '启用' ?>
                </button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
