<?php
/**
 * 上网入口 - 用户认证
 * 依赖 db() 与 functions.php，请通过 app/init.php 引入。
 */

/** 获取当前登录用户（未登录返回 null） */
function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $user = false;
    if ($user === false) {
        $stmt = db()->prepare('SELECT id, username, email, email_verified, phone, real_name, role, status, created_at, last_login_at FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([(int)$_SESSION['user_id']]);
        $row = $stmt->fetch();
        if ($row === false || (int)$row['status'] !== 1) {
            // 用户不存在或被禁用：清除无效会话
            unset($_SESSION['user_id']);
            $user = null;
        } else {
            $user = $row;
        }
    }
    return $user;
}

/** 是否已登录 */
function is_logged_in(): bool
{
    return current_user() !== null;
}

/** 是否为管理员 */
function is_admin(): bool
{
    $user = current_user();
    return $user !== null && $user['role'] === 'admin';
}

/** 未登录则跳转到登录页 */
function require_login(): void
{
    if (!is_logged_in()) {
        flash_set('warning', '请先登录。');
        redirect('login.php');
    }
}

/** 非管理员直接拒绝访问 */
function require_admin(): void
{
    if (!is_admin()) {
        http_response_code(403);
        exit('无权访问该页面。');
    }
}

/**
 * 登录校验。
 * @return array [是否成功, 成功时为用户数组 / 失败时为错误信息]
 */
function attempt_login(string $username, string $password): array
{
    // 简单防爆破：同一会话累计 5 次失败后锁定 15 分钟
    $now       = time();
    $failCount = (int)($_SESSION['login_fails'] ?? 0);
    $lockUntil = (int)($_SESSION['login_lock_until'] ?? 0);

    if ($lockUntil > $now) {
        return [false, '尝试次数过多，请 ' . (int)ceil(($lockUntil - $now) / 60) . ' 分钟后再试。'];
    }
    if ($failCount >= 5) {
        $_SESSION['login_lock_until'] = $now + 900;
        $_SESSION['login_fails']      = 0;
        return [false, '尝试次数过多，请 15 分钟后再试。'];
    }

    $stmt = db()->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([trim($username)]);
    $user = $stmt->fetch();

    if ($user !== false && password_verify($password, $user['password_hash'])) {
        if ((int)$user['status'] !== 1) {
            return [false, '该账号已被禁用，请联系管理员。'];
        }
        // 登录成功：重置失败计数、更新会话与最后登录时间
        unset($_SESSION['login_fails'], $_SESSION['login_lock_until']);
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);
        return [true, $user];
    }

    $_SESSION['login_fails'] = $failCount + 1;
    return [false, '用户名或密码错误。'];
}

/**
 * 注册新用户（默认普通用户角色）。
 * 注册前需先完成邮箱验证码校验（由调用方调用 verify_email_code）。
 *
 * @param string $username      用户名
 * @param string $password      密码
 * @param array  $info          ['email' => 邮箱(必填), 'real_name' => 姓名(可空), 'phone' => 手机号(可空)]
 * @param bool   $emailVerified 邮箱是否已验证
 * @return array [是否成功, 失败时为错误信息]
 */
function attempt_register(string $username, string $password, array $info = [], bool $emailVerified = false): array
{
    $username = trim($username);
    $email    = strtolower(trim((string)($info['email'] ?? '')));
    $realName = trim((string)($info['real_name'] ?? ''));
    $phone    = trim((string)($info['phone'] ?? ''));

    if (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $username)) {
        return [false, '用户名需为 3-20 位字母、数字或下划线。'];
    }
    if (str_length($password) < 6) {
        return [false, '密码长度至少 6 位。'];
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [false, '请填写正确的邮箱地址。'];
    }
    if (str_length($realName) > 50) {
        return [false, '姓名不超过 50 字。'];
    }
    if (str_length($phone) > 20) {
        return [false, '手机号不超过 20 位。'];
    }
    if (!$emailVerified) {
        return [false, '邮箱尚未验证，请先获取并输入验证码。'];
    }

    $exists = db()->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    $exists->execute([$username]);
    if ($exists->fetch() !== false) {
        return [false, '该用户名已被注册。'];
    }
    $exists = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $exists->execute([$email]);
    if ($exists->fetch() !== false) {
        return [false, '该邮箱已被注册，请直接登录或更换邮箱。'];
    }
    db()->prepare('INSERT INTO users (username, password_hash, email, email_verified, phone, real_name, role, status) VALUES (?, ?, ?, 1, ?, ?, ?, 1)')
        ->execute([$username, password_hash($password, PASSWORD_DEFAULT), $email, $phone, $realName, 'user']);
    return [true, null];
}

/* ==================== 邮箱验证码（注册 / 更换邮箱） ==================== */

/**
 * 生成并发送邮箱验证码（存会话，10 分钟有效，一次性使用）。
 * 限频：同一邮箱 60 秒内不可重复发送；每日每邮箱最多 10 次。
 * @return array [是否成功, 失败时为错误信息]
 */
function send_email_code(string $email, string $usage = 'register'): array
{
    $email = strtolower(trim($email));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [false, '邮箱格式不正确。'];
    }
    if ($usage === 'register' || $usage === 'change') {
        // 该邮箱是否已被占用
        $stmt = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $taken = $stmt->fetch() !== false;
        if ($usage === 'register' && $taken) {
            return [false, '该邮箱已被注册，请直接登录或更换邮箱。'];
        }
        if ($usage === 'change' && $taken) {
            return [false, '该邮箱已被其他账号使用。'];
        }
    }

    $sessKey = 'mail_code_' . md5($email);
    $now     = time();
    $data    = $_SESSION[$sessKey] ?? null;
    if ($data && ($now - (int)$data['sent_at']) < 60) {
        return [false, '发送过于频繁，请 ' . (60 - ($now - (int)$data['sent_at'])) . ' 秒后再试。'];
    }
    // 每日每邮箱次数限制（按会话统计，够用且无 IP 误伤）
    $dayKey = 'mail_sent_count_' . md5($email);
    if (!isset($_SESSION[$dayKey]) || date('Ymd', (int)$_SESSION[$dayKey]['day']) !== date('Ymd', $now)) {
        $_SESSION[$dayKey] = ['day' => $now, 'count' => 0];
    }
    if ((int)$_SESSION[$dayKey]['count'] >= 10) {
        return [false, '该邮箱今日发送次数已达上限，请明日再试。'];
    }

    $code = (string)random_int(100000, 999999);
    $site = site_name() ?: '上网入口';
    $body = '<div style="font-family:sans-serif;max-width:480px;margin:0 auto;padding:24px;border:1px solid #e5e7eb;border-radius:12px">'
          . '<h2 style="margin:0 0 12px">' . htmlspecialchars($site, ENT_QUOTES, 'UTF-8') . ' - 邮箱验证</h2>'
          . '<p style="margin:0 0 8px;color:#333">您的验证码是：</p>'
          . '<div style="font-size:28px;font-weight:bold;letter-spacing:6px;color:#2563eb;padding:10px 0 14px">' . $code . '</div>'
          . '<p style="margin:0;color:#888;font-size:13px">验证码 10 分钟内有效，请勿泄露给他人。若您未进行此操作，请忽略本邮件。</p>'
          . '</div>';
    [$ok, $err] = send_mail($email, '【' . $site . '】验证码：' . $code, $body);
    if (!$ok) {
        return [false, '邮件发送失败：' . $err];
    }

    $_SESSION[$sessKey] = ['code' => $code, 'sent_at' => $now, 'expires_at' => $now + 600];
    $_SESSION[$dayKey]['count'] = (int)$_SESSION[$dayKey]['count'] + 1;
    return [true, null];
}

/** 校验邮箱验证码（正确则立即作废，一次性使用） */
function verify_email_code(string $email, string $input): bool
{
    $email = strtolower(trim($email));
    $data  = $_SESSION['mail_code_' . md5($email)] ?? null;
    if (!is_array($data)) {
        return false;
    }
    if (time() > (int)$data['expires_at']) {
        unset($_SESSION['mail_code_' . md5($email)]);
        return false;
    }
    if (!hash_equals((string)$data['code'], trim($input))) {
        return false;
    }
    unset($_SESSION['mail_code_' . md5($email)]);
    return true;
}

/** 退出登录并销毁会话 */
function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
