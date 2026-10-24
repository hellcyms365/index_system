<?php
/**
 * 上网入口 - 全局配置
 *
 * 部署时只需要修改本文件的数据库连接信息；
 * 也支持通过环境变量覆盖（DB_HOST/DB_PORT/DB_NAME/DB_USER/DB_PASS），
 * 方便 Docker、宝塔面板等不同方式部署。
 */

return [
    // 数据库连接
    'db' => [
        'host'    => getenv('DB_HOST') ?: '127.0.0.1',
        'port'    => getenv('DB_PORT') ?: '3306',
        'name'    => getenv('DB_NAME') ?: 'net_portal',
        'user'    => getenv('DB_USER') ?: 'root',
        'pass'    => getenv('DB_PASS') ?: '',
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

    // 邮件发送（注册邮箱验证码等）
    // mode = smtp 走内置 SMTP 客户端；mode = mail 降级用 PHP mail()（依赖服务器 MTA）
    // 常用免费发信服务：QQ邮箱 smtp.qq.com:465(ssl)；163 smtp.163.com:465(ssl)；
    // Gmail smtp.gmail.com:587(tls)；阿里/腾讯企业邮见各自文档
    'mail' => [
        'mode'      => getenv('MAIL_MODE') ?: 'mail',   // mail | smtp
        'host'      => getenv('MAIL_HOST') ?: '',       // 如 smtp.qq.com
        'port'      => getenv('MAIL_PORT') ?: '465',
        'secure'    => getenv('MAIL_SECURE') ?: 'ssl',  // ssl | tls | none
        'user'      => getenv('MAIL_USER') ?: '',       // 发信账号（QQ 邮箱需授权码）
        'pass'      => getenv('MAIL_PASS') ?: '',       // 密码 / 授权码
        'from'      => getenv('MAIL_FROM') ?: '',       // 发件人地址（留空使用收件人）
        'from_name' => getenv('MAIL_FROM_NAME') ?: '上网入口',
    ],
];
