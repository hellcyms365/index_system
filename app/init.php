<?php
/**
 * 上网入口 - 应用初始化
 * 所有页面（install.php 除外）先引入本文件。
 */

require_once __DIR__ . '/functions.php';

// 调试模式（放在 functions.php 之后，config() 才可用）
if (config('app.debug')) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

// 会话（HttpOnly + SameSite=Lax，降低 Cookie 劫持与 CSRF 风险）
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/settings.php';
