<?php
/**
 * 点击跳转 + 访问统计
 * 记录一次点击后，302 跳转到目标链接。
 */
require_once __DIR__ . '/../app/init.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('参数错误。');
}

$stmt = db()->prepare('SELECT id, url FROM links WHERE id = ? AND status = 1 LIMIT 1');
$stmt->execute([$id]);
$link = $stmt->fetch();

if ($link === false) {
    http_response_code(404);
    exit('链接不存在或已停用。');
}

$url = normalize_url($link['url']);
if ($url === '') {
    http_response_code(400);
    exit('链接地址不合法。');
}

// 记录点击（IP 与 UA 仅用于统计，不对外展示原始日志）
$ua = str_cut((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 255);
db()->prepare('INSERT INTO click_logs (link_id, ip, user_agent) VALUES (?, ?, ?)')
    ->execute([$link['id'], get_ip(), $ua]);

header('Location: ' . $url);
exit;
