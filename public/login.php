<?php
/**
 * 用户登录
 */
require_once __DIR__ . '/../app/init.php';

if (is_logged_in()) {
    redirect('index.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = '请输入用户名和密码。';
    } else {
        [$ok, $result] = attempt_login($username, $password);
        if ($ok) {
            flash_set('success', '欢迎回来，' . $result['username'] . '！');
            redirect(is_admin() ? 'admin/index.php' : 'index.php');
        }
        $error = $result;
    }
}

$pageTitle = '登录';
include __DIR__ . '/partials/header.php';
?>

<section class="auth-page">
  <div class="auth-card">
    <h1 class="auth-title">用户登录</h1>
    <?php if ($error): ?>
      <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" action="login.php" autocomplete="on">
      <?= csrf_field() ?>
      <div class="form-group">
        <label for="username">用户名</label>
        <input class="input" type="text" id="username" name="username"
               value="<?= e($_POST['username'] ?? '') ?>" required maxlength="20" autofocus>
      </div>
      <div class="form-group">
        <label for="password">密码</label>
        <input class="input" type="password" id="password" name="password" required>
      </div>
      <button class="btn btn-primary btn-block" type="submit">登 录</button>
    </form>
    <?php if (config('app.registration_open')): ?>
      <p class="auth-alt">还没有账号？<a href="register.php">立即注册</a></p>
    <?php endif; ?>
  </div>
</section>

<?php include __DIR__ . '/partials/footer.php'; ?>
