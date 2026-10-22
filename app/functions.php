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
