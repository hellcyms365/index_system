<?php
/**
 * 上网入口 - 站点设置（key-value 存储）
 * 依赖 functions.php 与 db.php（请通过 app/init.php 引入）。
 *
 * 说明：settings 表若不存在会自动创建并写入默认值（用于老库平滑升级）；
 * 建表失败时（如数据库账号无 DDL 权限）静默回退到内置默认值，不影响使用。
 */

require_once __DIR__ . '/db.php';

/** 内置默认设置 */
const DEFAULT_SETTINGS = [
    'site_name'    => '',                 // 站点名称（留空则用 config 中的 site.name）
    'tagline'      => '',                 // 首页副标题（留空不显示）
    'accent_color' => '#2563eb',          // 主题色
    'bg_mode'      => 'default',          // 背景：default | color | gradient | image
    'bg_color'     => '#f3f5f9',          // 纯色背景色
    'gradient_a'   => '#60a5fa',          // 渐变起始色
    'gradient_b'   => '#8b5cf6',          // 渐变结束色
    'bg_image'     => '',                 // 背景图（http(s) 地址或 /assets/uploads/ 本地路径）
    'bg_overlay'   => '0.35',             // 背景图白色遮罩透明度 0-0.85
    'bg_fixed'     => '1',                // 背景图是否固定（视差）
    'card_opacity' => '1',                // 链接卡片不透明度 0.35-1（背景图模式下更通透）
    'footer_text'  => '',                 // 页脚文字（留空用默认）
];

/** 确保 settings 表存在并写入默认值（老库升级用） */
function ensure_settings_table(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        db()->exec(
            'CREATE TABLE IF NOT EXISTS `settings` (
                `skey` VARCHAR(64) NOT NULL PRIMARY KEY,
                `svalue` TEXT,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $rows = db()->query('SELECT COUNT(*) FROM `settings`')->fetchColumn();
        if ((int)$rows === 0) {
            $stmt = db()->prepare('INSERT IGNORE INTO `settings` (skey, svalue) VALUES (?, ?)');
            foreach (DEFAULT_SETTINGS as $key => $value) {
                $stmt->execute([$key, $value]);
            }
        }
    } catch (Throwable $e) {
        // 建表/读取失败（如无 DDL 权限）：静默回退，setting() 将全部使用默认值
    }
}

/** 读取一项设置（未保存过时返回内置默认值） */
function setting(string $key, string $default = ''): string
{
    ensure_settings_table();
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (db()->query('SELECT skey, svalue FROM `settings`') as $row) {
                $cache[$row['skey']] = (string)$row['svalue'];
            }
        } catch (Throwable $e) {
            $cache = [];
        }
    }
    if (array_key_exists($key, $cache) && $cache[$key] !== '') {
        return $cache[$key];
    }
    if ($default !== '') {
        return $default;
    }
    return DEFAULT_SETTINGS[$key] ?? '';
}

/** 保存一项设置（不存在则插入，存在则更新） */
function save_setting(string $key, string $value): void
{
    ensure_settings_table();
    $stmt = db()->prepare('UPDATE `settings` SET svalue = ? WHERE skey = ?');
    $stmt->execute([$value, $key]);
    if ($stmt->rowCount() === 0) {
        db()->prepare('INSERT IGNORE INTO `settings` (skey, svalue) VALUES (?, ?)')
            ->execute([$key, $value]);
    }
}

/** 站点显示名称：优先取设置，其次取 config */
function site_name(): string
{
    $name = setting('site_name');
    return $name !== '' ? $name : (string)config('site.name');
}
