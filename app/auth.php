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
        $stmt = db()->prepare('SELECT id, username, role, status, created_at, last_login_at FROM users WHERE id = ? LIMIT 1');
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
 * @return array [是否成功, 失败时为错误信息]
 */
function attempt_register(string $username, string $password): array
{
    $username = trim($username);
    if (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $username)) {
        return [false, '用户名需为 3-20 位字母、数字或下划线。'];
    }
    if (str_length($password) < 6) {
        return [false, '密码长度至少 6 位。'];
    }
    $exists = db()->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    $exists->execute([$username]);
    if ($exists->fetch() !== false) {
        return [false, '该用户名已被注册。'];
    }
    db()->prepare('INSERT INTO users (username, password_hash, role, status) VALUES (?, ?, ?, 1)')
        ->execute([$username, password_hash($password, PASSWORD_DEFAULT), 'user']);
    return [true, null];
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
