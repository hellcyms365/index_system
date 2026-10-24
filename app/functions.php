<?php
/**
 * 上网入口 - 公共函数
 * 只包含不依赖数据库的通用工具函数。
 */

/** HTML 转义输出，防止 XSS */
function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/** 读取配置项，支持点语法：config('db.host') */
function config(string $key, $default = null)
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/config.php';
    }
    $keys = explode('.', $key);
    $value = $config;
    foreach ($keys as $k) {
        if (!is_array($value) || !array_key_exists($k, $value)) {
            return $default;
        }
        $value = $value[$k];
    }
    return $value;
}

/** 生成 / 获取当前会话的 CSRF Token */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** 输出隐藏的 CSRF 字段 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** 校验 POST 请求的 CSRF Token，失败直接终止 */
function csrf_check(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        exit('请求校验失败（CSRF），请返回重试。');
    }
}

/** 页面重定向 */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/** 写入一条一次性提示消息 */
function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/** 读取并清空一次性提示消息 */
function flash_get(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/** 输出提示消息的 HTML（在页面顶部调用） */
function flash_render(): string
{
    $flash = flash_get();
    if ($flash === null) {
        return '';
    }
    $type = $flash['type'] === 'error' ? 'error' : ($flash['type'] === 'warning' ? 'warning' : 'success');
    return '<div class="alert alert-' . $type . '">' . e($flash['message']) . '</div>';
}

/** 获取客户端 IP（支持反向代理透传） */
function get_ip(): string
{
    $candidates = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    foreach ($candidates as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(explode(',', (string)$_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '';
}

/** 站内基础地址：优先取配置，否则自动探测当前协议与域名 */
function base_url(): string
{
    $configured = config('site.base_url', '');
    if ($configured !== '') {
        return rtrim($configured, '/');
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

/** 生成站内绝对地址 */
function url(string $path = ''): string
{
    return base_url() . '/' . ltrim($path, '/');
}

/** 安全的字符串截断（按字符计数，UTF-8 安全；mbstring 不可用时用正则回退） */
function str_cut(string $s, int $length): string
{
    if ($length <= 0) {
        return '';
    }
    if (function_exists('mb_substr')) {
        return mb_substr($s, 0, $length, 'UTF-8');
    }
    $chars = preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY);
    if ($chars === false) {
        return substr($s, 0, $length);
    }
    return implode('', array_slice($chars, 0, $length));
}

/** 安全的字符串长度（按字符计数，UTF-8 安全；mbstring 不可用时用正则回退） */
function str_length(string $s): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($s, 'UTF-8');
    }
    $chars = preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY);
    return $chars === false ? strlen($s) : count($chars);
}

/** 规范化链接地址：缺少协议时自动补 http://，并只允许 http/https */
function normalize_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    if (!preg_match('#^[a-z][a-z0-9+.\-]*://#i', $url)) {
        $url = 'http://' . $url;
    }
    $scheme = strtolower(parse_url($url, PHP_URL_SCHEME) ?: '');
    if (!in_array($scheme, ['http', 'https'], true)) {
        return '';
    }
    return $url;
}

/**
 * 链接图标渲染：图片地址（http(s) 或 /assets/ 本地路径）渲染为 <img>，
 * 其他内容（emoji 等）作为纯文本返回；图标为空时回退标题首字符。
 * 返回值已做 HTML 转义，可直接输出。
 */
function link_icon_html(string $icon, string $fallbackTitle): string
{
    $icon = trim($icon);
    if ($icon !== '') {
        $isImage = preg_match('#^https?://#i', $icon) === 1
                || substr($icon, 0, 8) === '/assets/';
        if ($isImage) {
            return '<img class="link-icon-img" src="' . e($icon) . '" alt="" loading="lazy">';
        }
        return e($icon);
    }
    return e(str_cut($fallbackTitle, 1));
}

/** 校验 CSS 颜色值（仅允许 #rgb / #rrggbb），非法时返回默认值 */
function sanitize_css_color(string $value, string $default = '#2563eb'): string
{
    if (preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', trim($value))) {
        return strtolower(trim($value));
    }
    return $default;
}

/** CSS 单引号字符串转义（用于把地址安全地放进 url('...')） */
function css_quote(string $s): string
{
    return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $s) . "'";
}

/**
 * 生成一份写入指定数据库连接信息的 config.php 内容（安装向导回写配置用）。
 * 保留 getenv 环境变量优先，写入值作为兜底。
 */
function render_config_with_db(array $db): string
{
    $host = var_export((string)$db['host'], true);
    $port = var_export((string)$db['port'], true);
    $name = var_export((string)$db['name'], true);
    $user = var_export((string)$db['user'], true);
    $pass = var_export((string)$db['pass'], true);
    $ts   = date('Y-m-d H:i:s');

    return <<<PHP
<?php
/**
 * 上网入口 - 全局配置
 *
 * 部署时只需要修改本文件的数据库连接信息；
 * 也支持通过环境变量覆盖（DB_HOST/DB_PORT/DB_NAME/DB_USER/DB_PASS），
 * 方便 Docker、宝塔面板等不同方式部署。
 * 数据库连接信息已于 {$ts} 由安装向导写入，如需修改请直接编辑下方 db 段。
 */

return [
    // 数据库连接
    'db' => [
        'host'    => getenv('DB_HOST') ?: {$host},
        'port'    => getenv('DB_PORT') ?: {$port},
        'name'    => getenv('DB_NAME') ?: {$name},
        'user'    => getenv('DB_USER') ?: {$user},
        'pass'    => getenv('DB_PASS') ?: {$pass},
        'charset' => 'utf8mb4',
    ],

    // 站点信息
    'site' => [
        'name'     => getenv('SITE_NAME') ?: '上网入口',
        // 站点的完整地址（如 https://nav.example.com），留空则自动探测当前域名
        'base_url' => getenv('SITE_BASE_URL') ?: '',
    ],

    // 功能开关
    'app' => [
        // 是否开放注册（设为 0 则只保留登录，注册页不可用）
        'registration_open' => getenv('REG_OPEN') !== '0',
        // 是否开启调试模式（显示 PHP 错误，生产环境建议保持关闭）
        'debug' => getenv('APP_DEBUG') === '1',
    ],
];
PHP;
}

/** 确保 links.is_pinned 列存在（老库自动升级，无则 ALTER 添加） */
function ensure_links_pinned(): void
{
    try {
        $db = db();
        $row = $db->query(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'links' AND COLUMN_NAME = 'is_pinned'"
        )->fetch();
        if ($row === false) {
            $db->exec('ALTER TABLE `links` ADD COLUMN `is_pinned` TINYINT NOT NULL DEFAULT 0 AFTER `sort_order`');
        }
    } catch (Throwable $e) {
        // 静默：权限或结构异常时不阻塞页面
    }
}

/**
 * 确保 links 表含 user_id 列（老库自动升级）。
 * NULL = 管理员维护的全局链接；非 NULL = 该用户的个人链接。
 */
function ensure_links_user_id(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $db  = db();
        $row = $db->query(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'links' AND COLUMN_NAME = 'user_id'"
        )->fetch();
        if ($row === false) {
            $db->exec('ALTER TABLE `links` ADD COLUMN `user_id` INT UNSIGNED NULL DEFAULT NULL AFTER `id`, ADD KEY `idx_user` (`user_id`)');
        }
    } catch (Throwable $e) {
        // 静默：无 DDL 权限时页面不阻塞
    }
}

/**
 * 确保 categories 表含 user_id 列（老库自动升级）。
 * NULL = 管理员维护的全局分类；非 NULL = 该用户的个人分类。
 */
function ensure_categories_user_id(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $db  = db();
        $row = $db->query(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'categories' AND COLUMN_NAME = 'user_id'"
        )->fetch();
        if ($row === false) {
            $db->exec('ALTER TABLE `categories` ADD COLUMN `user_id` INT UNSIGNED NULL DEFAULT NULL AFTER `id`, ADD KEY `idx_user` (`user_id`)');
        }
    } catch (Throwable $e) {
        // 静默：无 DDL 权限时页面不阻塞
    }
}

/**
 * 确保 users 表含邮箱 / 手机号 / 姓名 / 邮箱验证列（老库自动升级）。
 */
function ensure_users_contact(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $db   = db();
        $cols = [];
        foreach ($db->query(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'"
        ) as $row) {
            $cols[$row['COLUMN_NAME']] = true;
        }
        if (!isset($cols['email'])) {
            $db->exec("ALTER TABLE `users` ADD COLUMN `email` VARCHAR(191) NULL DEFAULT NULL AFTER `username`, ADD UNIQUE KEY `uk_users_email` (`email`)");
        }
        if (!isset($cols['email_verified'])) {
            $db->exec("ALTER TABLE `users` ADD COLUMN `email_verified` TINYINT NOT NULL DEFAULT 0 AFTER `email`");
        }
        if (!isset($cols['phone'])) {
            $db->exec("ALTER TABLE `users` ADD COLUMN `phone` VARCHAR(50) NOT NULL DEFAULT '' AFTER `email_verified`");
        }
        if (!isset($cols['real_name'])) {
            $db->exec("ALTER TABLE `users` ADD COLUMN `real_name` VARCHAR(50) NOT NULL DEFAULT '' AFTER `phone`");
        }
    } catch (Throwable $e) {
        // 静默：无 DDL 权限时页面不阻塞
    }
}

/**
 * 确保 click_daily 按天聚合表存在（老库自动升级）。
 * 首次创建时从 click_logs 一次性回填历史数据（INSERT IGNORE，重复执行不会累加）。
 */
function ensure_click_daily_table(): void
{
    $db = db();
    $exists = false;
    try {
        $count = $db->query(
            "SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'click_daily'"
        )->fetchColumn();
        $exists = (int)$count > 0;
    } catch (Throwable $e) {
        $exists = true; // 无法探测时按已存在处理，避免每次请求都尝试建表
    }
    if (!$exists) {
        try {
            $db->exec(
                'CREATE TABLE IF NOT EXISTS `click_daily` (
                    `link_id` INT UNSIGNED NOT NULL,
                    `click_date` DATE NOT NULL,
                    `count` INT UNSIGNED NOT NULL DEFAULT 0,
                    PRIMARY KEY (`link_id`, `click_date`),
                    KEY `idx_date` (`click_date`),
                    CONSTRAINT `fk_daily_link` FOREIGN KEY (`link_id`) REFERENCES `links`(`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (Throwable $e) {
            // 建表失败不阻塞统计页
            return;
        }
        try {
            $db->exec(
                'INSERT IGNORE INTO `click_daily` (`link_id`, `click_date`, `count`)
                 SELECT `link_id`, DATE(`created_at`), COUNT(*) FROM `click_logs`
                 GROUP BY `link_id`, DATE(`created_at`)'
            );
        } catch (Throwable $e) {
            // 回填失败不影响后续增量
        }
    }
}

/** 受限 HTTP GET：限流限大小（抓 favicon 等用），返回字符串，失败返回空串 */
function http_get_limited(string $url, int $maxBytes = 262144, int $timeoutSec = 5): string
{
    if (!in_array(strtolower((string)parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
        return '';
    }
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_CONNECTTIMEOUT => $timeoutSec,
            CURLOPT_TIMEOUT        => $timeoutSec,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; NetPortal/1.0)',
            CURLOPT_BUFFERSIZE     => 16384,
        ]);
        $data = curl_exec($ch);
        curl_close($ch);
        return is_string($data) ? substr($data, 0, $maxBytes) : '';
    }
    $ctx = stream_context_create([
        'http' => [
            'timeout'        => $timeoutSec,
            'follow_location' => 1,
            'max_redirects'  => 3,
            'user_agent'     => 'Mozilla/5.0 (compatible; NetPortal/1.0)',
            'ignore_errors'  => true,
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $data = @file_get_contents($url, false, $ctx);
    return is_string($data) ? substr($data, 0, $maxBytes) : '';
}

/** 把相对路径解析为基于站点域名的绝对地址 */
function resolve_url(string $path, string $base): string
{
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    $parts = parse_url($base);
    $scheme = (string)($parts['scheme'] ?? 'http');
    $host   = (string)($parts['host'] ?? '');
    if ($host === '' || $path === '') {
        return '';
    }
    if ($path[0] === '/') {
        return $scheme . '://' . $host . $path;
    }
    $dir = dirname((string)($parts['path'] ?? '/'));
    $dir = str_replace('\\', '/', $dir);
    return $scheme . '://' . $host . ($dir === '/' ? '/' : rtrim($dir, '/') . '/') . $path;
}

/** 识别 favicon 图片类型（ICO/PNG/GIF/JPEG/WebP），无法识别返回 null */
function detect_favicon_type(string $data): ?string
{
    $head = substr($data, 0, 16);
    if (substr($head, 0, 4) === "\x00\x00\x01\x00") {
        return 'ico'; // ICO 文件头
    }
    if (substr($head, 0, 4) === "\x89PNG") {
        return 'png';
    }
    if (function_exists('getimagesize')) {
        $tmp = tempnam(sys_get_temp_dir(), 'ico');
        if ($tmp === false) {
            return null;
        }
        file_put_contents($tmp, $data);
        $info = @getimagesize($tmp);
        @unlink($tmp);
        if (is_array($info)) {
            $map = [IMAGETYPE_GIF => 'gif', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp'];
            return $map[$info[2] ?? 0] ?? null;
        }
    }
    return null;
}

/**
 * 抓取站点 favicon：优先解析首页 <link rel="icon">，兜底 /favicon.ico。
 * 返回 [二进制数据, 扩展名]，失败返回 [null, 错误说明]。
 */
function fetch_favicon(string $scheme, string $host): array
{
    $candidates = [];
    $html = http_get_limited(($scheme === 'https' ? 'https' : 'http') . '://' . $host . '/', 524288, 5);
    if ($html !== '' && preg_match_all('#<link[^>]+rel=["\'][^"\']*icon[^"\']*["\'][^>]*>#i', $html, $m)) {
        foreach ($m[0] as $tag) {
            if (preg_match('#href=["\']([^"\']+)["\']#i', $tag, $hm)) {
                $href = trim($hm[1]);
                if ($href !== '') {
                    $candidates[] = $href;
                }
            }
        }
    }
    $candidates[] = '/favicon.ico';
    $base = $scheme . '://' . $host;
    foreach ($candidates as $path) {
        $abs = resolve_url($path, $base);
        if ($abs === '') {
            continue;
        }
        $data = http_get_limited($abs, 262144, 5);
        if ($data === '') {
            continue;
        }
        $ext = detect_favicon_type($data);
        if ($ext !== null) {
            return [$data, $ext];
        }
    }
    return [null, '站点未提供可识别的图标（可能屏蔽了抓取）'];
}
