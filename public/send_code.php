<?php
/**
 * 邮箱验证码发送接口（AJAX / 表单均可）。
 * POST 参数：csrf_token、email、usage(register|change)
 * 返回 JSON：{"ok": true/false, "msg": "..."}
 */
require_once __DIR__ . '/../app/init.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in() && !config('app.registration_open')) {
    echo json_encode(['ok' => false, 'msg' => '本站暂未开放注册。'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => '请求方式不正确。'], JSON_UNESCAPED_UNICODE);
    exit;
}

csrf_check();

$email = strtolower(trim((string)($_POST['email'] ?? '')));
$usage = (string)($_POST['usage'] ?? 'register');
if (!in_array($usage, ['register', 'change'], true)) {
    $usage = 'register';
}
// 更换邮箱场景必须已登录且目标是自己的邮箱
if ($usage === 'change' && !is_logged_in()) {
    echo json_encode(['ok' => false, 'msg' => '请先登录。'], JSON_UNESCAPED_UNICODE);
    exit;
}

[$ok, $msg] = send_email_code($email, $usage);
echo json_encode(['ok' => $ok, 'msg' => $msg], JSON_UNESCAPED_UNICODE);
