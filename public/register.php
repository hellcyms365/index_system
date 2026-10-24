<?php
/**
 * 用户注册：录入个人信息 + 邮箱验证码校验
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
    $realName        = trim((string)($_POST['real_name'] ?? ''));
    $email           = strtolower(trim((string)($_POST['email'] ?? '')));
    $phone           = trim((string)($_POST['phone'] ?? ''));
    $code            = trim((string)($_POST['code'] ?? ''));

    if ($realName === '') {
        $error = '请填写真实姓名。';
    } elseif ($password !== $passwordConfirm) {
        $error = '两次输入的密码不一致。';
    } else {
        if (!verify_email_code($email, $code)) {
            $error = '邮箱验证码不正确或已过期，请重新获取。';
        } else {
            [$ok, $message] = attempt_register($username, $password, [
                'email'     => $email,
                'real_name' => $realName,
                'phone'     => $phone,
            ], true);
            if ($ok) {
                flash_set('success', '注册成功，请登录。');
                redirect('login.php');
            }
            $error = $message;
        }
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
        <label for="real_name">姓名</label>
        <input class="input" type="text" id="real_name" name="real_name"
               value="<?= e($_POST['real_name'] ?? '') ?>" required maxlength="50"
               placeholder="真实姓名或昵称">
      </div>
      <div class="form-group">
        <label for="email">邮箱</label>
        <div class="input-group">
          <input class="input" type="email" id="email" name="email"
                 value="<?= e($_POST['email'] ?? '') ?>" required maxlength="191"
                 placeholder="用于接收注册验证码">
          <button class="btn btn-outline btn-sm" type="button" id="send_code_btn">获取验证码</button>
        </div>
        <p class="form-hint" id="send_code_hint"></p>
      </div>
      <div class="form-group">
        <label for="code">邮箱验证码</label>
        <input class="input" type="text" id="code" name="code" required maxlength="6"
               inputmode="numeric" pattern="[0-9]{6}" title="6 位数字验证码"
               placeholder="输入邮箱收到的 6 位验证码">
      </div>
      <div class="form-group">
        <label for="phone">手机号（选填）</label>
        <input class="input" type="text" id="phone" name="phone"
               value="<?= e($_POST['phone'] ?? '') ?>" maxlength="20" placeholder="可不填">
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

<script>
document.addEventListener('DOMContentLoaded', function () {
  var btn = document.getElementById('send_code_btn');
  var emailInput = document.getElementById('email');
  var hint = document.getElementById('send_code_hint');
  var csrf = document.querySelector('input[name="csrf_token"]');
  var countdown = 0;
  var timer = null;

  function resetBtn(text) {
    btn.textContent = text;
    btn.disabled = false;
  }

  btn.addEventListener('click', function () {
    if (countdown > 0) return;
    var email = emailInput.value.trim();
    if (!email) { hint.textContent = '请先填写邮箱。'; hint.className = 'form-hint'; return; }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { hint.textContent = '邮箱格式不正确。'; hint.className = 'form-hint'; return; }

    var fd = new FormData();
    fd.append('csrf_token', csrf.value);
    fd.append('email', email);
    fd.append('usage', 'register');

    fetch('send_code.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        hint.textContent = res.msg || '';
        hint.className = res.ok ? 'form-hint form-hint-ok' : 'form-hint';
        if (res.ok) {
          countdown = 60;
          btn.disabled = true;
          btn.textContent = countdown + 's 后重发';
          timer = setInterval(function () {
            countdown--;
            if (countdown <= 0) {
              clearInterval(timer);
              resetBtn('重新获取验证码');
            } else {
              btn.textContent = countdown + 's 后重发';
            }
          }, 1000);
        }
      })
      .catch(function () {
        hint.textContent = '网络错误，请重试。';
        hint.className = 'form-hint';
      });
  });
});
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
