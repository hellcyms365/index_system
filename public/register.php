<?php
/**
 * 用户注册
 */
require_once __DIR__ . '/../app/init.php';

if (is_logged_in()) {
    redirect('index.php');
}

if (!config('app.registration_open')) {
    http_response_code(403);
    exit('本站暂未开放注册，请联系管理员。');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username        = trim((string)($_POST['username'] ?? ''));
    $password        = (string)($_POST['password'] ?? '');
    $passwordConfirm = (string)($_POST['password_confirm'] ?? '');

    if ($password !== $passwordConfirm) {
        $error = '两次输入的密码不一致。';
    } else {
        [$ok, $message] = attempt_register($username, $password);
        if ($ok) {
            flash_set('success', '注册成功，请登录。');
            redirect('login.php');
        }
        $error = $message;
    }
}

$pageTitle = '注册';
include __DIR__ . '/partials/header.php';
?>

<section class="auth-page">
  <div class="auth-card">
    <h1 class="auth-title">用户注册</h1>
    <?php if ($error): ?>
      <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" action="register.php" autocomplete="on">
      <?= csrf_field() ?>
      <div class="form-group">
        <label for="username">用户名</label>
        <input class="input" type="text" id="username" name="username"
               value="<?= e($_POST['username'] ?? '') ?>" required maxlength="20"
               pattern="[A-Za-z0-9_]{3,20}" title="3-20 位字母、数字或下划线" autofocus>
        <p class="form-hint">3-20 位字母、数字或下划线</p>
      </div>
      <div class="form-group">
        <label for="password">密码</label>
        <input class="input" type="password" id="password" name="password" required minlength="6">
        <p class="form-hint">至少 6 位</p>
      </div>
      <div class="form-group">
        <label for="password_confirm">确认密码</label>
        <input class="input" type="password" id="password_confirm" name="password_confirm" required minlength="6">
      </div>
      <button class="btn btn-primary btn-block" type="submit">注 册</button>
    </form>
    <p class="auth-alt">已有账号？<a href="login.php">直接登录</a></p>
  </div>
</section>

<?php include __DIR__ . '/partials/footer.php'; ?>
