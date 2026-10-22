<?php
/**
 * 上网入口 - 数据库连接（PDO 单例）
 * 所有数据库操作统一走 PDO 预处理语句，防止 SQL 注入。
 */

require_once __DIR__ . '/functions.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = config('db');
        $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset={$c['charset']}";
        try {
            $pdo = new PDO($dsn, $c['user'], $c['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            exit('数据库连接失败，请检查 app/config.php 中的数据库配置。错误详情：'
                . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
        }
    }
    return $pdo;
}
