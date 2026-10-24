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
    // 邮件发信（留空 = 使用 app/config.php / 环境变量；后台「邮箱管理」页可覆盖）
    'mail_mode'    => '',
    'mail_host'    => '',
    'mail_port'    => '',
    'mail_secure'  => '',
    'mail_user'    => '',
    'mail_pass'    => '',
    'mail_from'    => '',
    'mail_from_name' => '',
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

/* ==================== 用户个人外观设置（user_settings 表） ====================
 * 空值 = 未设置，自动回退到站点全局设置。仅存外观相关 9 个键。
 */

/** 确保 user_settings 表存在（老库自动升级） */
function ensure_user_settings_table(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        db()->exec(
            'CREATE TABLE IF NOT EXISTS `user_settings` (
                `user_id`      INT UNSIGNED NOT NULL PRIMARY KEY,
                `accent_color` VARCHAR(20) NOT NULL DEFAULT \'\',
                `bg_mode`      VARCHAR(20) NOT NULL DEFAULT \'\',
                `bg_color`     VARCHAR(20) NOT NULL DEFAULT \'\',
                `gradient_a`   VARCHAR(20) NOT NULL DEFAULT \'\',
                `gradient_b`   VARCHAR(20) NOT NULL DEFAULT \'\',
                `bg_image`     VARCHAR(500) NOT NULL DEFAULT \'\',
                `bg_overlay`   VARCHAR(10) NOT NULL DEFAULT \'\',
                `bg_fixed`     VARCHAR(2) NOT NULL DEFAULT \'\',
                `card_opacity` VARCHAR(10) NOT NULL DEFAULT \'\',
                `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    } catch (Throwable $e) {
        // 静默：无 DDL 权限时回退到全局设置
    }
}

/** 读取某用户一行个人外观设置（不存在返回空数组） */
function user_setting_row(int $userId): array
{
    ensure_user_settings_table();
    static $cache = [];
    if ($userId <= 0) {
        return [];
    }
    if (!array_key_exists($userId, $cache)) {
        $cache[$userId] = [];
        try {
            $stmt = db()->prepare('SELECT * FROM `user_settings` WHERE user_id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $row = $stmt->fetch();
            if ($row !== false) {
                $cache[$userId] = $row;
            }
        } catch (Throwable $e) {
            $cache[$userId] = [];
        }
    }
    return $cache[$userId];
}

/** 读取某用户个人设置项（空值回退到默认值，不再回退全局——全局回退由 appearance_values 统一处理） */
function user_setting(int $userId, string $key, string $default = ''): string
{
    $row = user_setting_row($userId);
    if (isset($row[$key]) && $row[$key] !== '') {
        return (string)$row[$key];
    }
    return $default !== '' ? $default : (DEFAULT_SETTINGS[$key] ?? '');
}

/**
 * 合并最终外观值：已登录用户有个人设置则逐键覆盖全局设置。
 * @return array 与 DEFAULT_SETTINGS 同键的外观配置
 */
function appearance_values(?int $userId): array
{
    $values = [];
    foreach (array_keys(DEFAULT_SETTINGS) as $k) {
        $values[$k] = setting($k);
    }
    if ($userId === null) {
        return $values;
    }
    $row = user_setting_row($userId);
    if (!$row) {
        return $values;
    }
    foreach (['accent_color', 'bg_mode', 'bg_color', 'gradient_a', 'gradient_b', 'bg_image', 'bg_overlay', 'bg_fixed', 'card_opacity'] as $k) {
        if (isset($row[$k]) && $row[$k] !== '') {
            $values[$k] = (string)$row[$k];
        }
    }
    return $values;
}

/** 保存某用户个人外观设置（9 个外观键；传空数组等价于恢复默认） */
function save_user_settings(int $userId, array $values): void
{
    ensure_user_settings_table();
    $keys   = ['accent_color', 'bg_mode', 'bg_color', 'gradient_a', 'gradient_b', 'bg_image', 'bg_overlay', 'bg_fixed', 'card_opacity'];
    $data   = [];
    foreach ($keys as $k) {
        $data[$k] = trim((string)($values[$k] ?? ''));
    }
    $stmt = db()->prepare(
        'INSERT INTO `user_settings` (user_id, accent_color, bg_mode, bg_color, gradient_a, gradient_b, bg_image, bg_overlay, bg_fixed, card_opacity)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
           accent_color = VALUES(accent_color), bg_mode = VALUES(bg_mode), bg_color = VALUES(bg_color),
           gradient_a = VALUES(gradient_a), gradient_b = VALUES(gradient_b), bg_image = VALUES(bg_image),
           bg_overlay = VALUES(bg_overlay), bg_fixed = VALUES(bg_fixed), card_opacity = VALUES(card_opacity)'
    );
    $stmt->execute([$userId, $data['accent_color'], $data['bg_mode'], $data['bg_color'], $data['gradient_a'], $data['gradient_b'], $data['bg_image'], $data['bg_overlay'], $data['bg_fixed'], $data['card_opacity']]);
}

/** 清除某用户个人外观设置（恢复全局默认） */
function clear_user_settings(int $userId): void
{
    ensure_user_settings_table();
    try {
        db()->prepare('DELETE FROM `user_settings` WHERE user_id = ?')->execute([$userId]);
    } catch (Throwable $e) {
        // 静默
    }
}
