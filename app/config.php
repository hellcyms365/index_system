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
];
