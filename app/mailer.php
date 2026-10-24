<?php
/**
 * 上网入口 - 邮件发送（纯原生实现，无 Composer 依赖）
 *
 * 支持两种模式（app/config.php 的 mail 段配置，均可用环境变量覆盖）：
 *   - mode = smtp：走内置 SMTP 客户端（SSL / STARTTLS / 无加密，AUTH LOGIN / PLAIN）
 *   - mode = mail：降级使用 PHP 的 mail() 函数（依赖服务器 MTA，宝塔默认可用性低，推荐 SMTP）
 */

/**
 * 读取邮件配置。
 * 优先级：后台「邮箱管理」页保存的数据库设置（settings 表） > app/config.php / 环境变量。
 * 数据库项留空表示未设置，自动回退到文件配置，因此后台不配置也能用文件默认值。
 */
function mail_config(): array
{
    $cfg  = config('mail', []);
    $dbOf = static function (string $key, string $fallback): string {
        $v = setting('mail_' . $key);
        return $v !== '' ? $v : $fallback;
    };
    return [
        'mode'      => $dbOf('mode', (string)($cfg['mode'] ?? 'mail')),     // mail | smtp
        'host'      => $dbOf('host', (string)($cfg['host'] ?? '')),
        'port'      => (int)$dbOf('port', (string)($cfg['port'] ?? 465)),
        'secure'    => $dbOf('secure', (string)($cfg['secure'] ?? 'ssl')),  // ssl | tls | none
        'user'      => $dbOf('user', (string)($cfg['user'] ?? '')),
        'pass'      => $dbOf('pass', (string)($cfg['pass'] ?? '')),
        'from'      => $dbOf('from', (string)($cfg['from'] ?? '')),
        'from_name' => $dbOf('from_name', (string)($cfg['from_name'] ?? '上网入口')),
    ];
}

/** RFC2047 编码（中文邮件头安全） */
function encode_mime(string $text): string
{
    if (preg_match('/[^\x20-\x7e]/', $text)) {
        return '=?UTF-8?B?' . base64_encode($text) . '?=';
    }
    return $text;
}

/**
 * 发送一封 HTML 邮件。
 * @return array [是否成功, 失败时错误信息]
 */
function send_mail(string $to, string $subject, string $bodyHtml): array
{
    $to = trim($to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return [false, '收件人邮箱格式不正确。'];
    }
    $cfg    = mail_config();
    $from   = trim($cfg['from']) !== '' ? trim($cfg['from']) : $to;
    $header = "From: " . encode_mime($cfg['from_name']) . " <{$from}>\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n"
            . "X-Mailer: NetPortal/1.0\r\n";
    $body = chunk_split(base64_encode($bodyHtml));

    if ($cfg['mode'] === 'smtp' && $cfg['host'] !== '') {
        return smtp_send($cfg, $to, $subject, $header, $body);
    }

    // 降级：PHP mail()（依赖服务器 MTA，需服务器支持发信）
    if (!function_exists('mail')) {
        return [false, '服务器未启用 mail() 且未配置 SMTP，请在 app/config.php 的 mail 段配置发信。'];
    }
    $ok = @mail($to, encode_mime($subject), $body, $header);
    return $ok ? [true, null] : [false, 'mail() 发送失败，请检查服务器邮件配置，或改用 SMTP 模式。'];
}

/**
 * 纯 PHP SMTP 客户端（不依赖任何扩展）。
 * @param array $cfg mail_config() 返回值
 * @return array [是否成功, 失败时错误信息]
 */
function smtp_send(array $cfg, string $to, string $subject, string $header, string $body): array
{
    $errno = 0;
    $errstr = '';
    $prefix = $cfg['secure'] === 'ssl' ? 'ssl://' : '';
    $conn = @stream_socket_client($prefix . $cfg['host'] . ':' . $cfg['port'], $errno, $errstr, 10);
    if ($conn === false) {
        return [false, '无法连接 SMTP 服务器：' . $errstr];
    }
    stream_set_timeout($conn, 15);

    $say = static function (string $cmd) use ($conn): void {
        fwrite($conn, $cmd . "\r\n");
    };
    $read = static function () use ($conn): string {
        $line = fgets($conn);
        // 合并多行响应（如 250-xxx 系列）
        while ($line !== false && strlen($line) >= 4 && $line[3] === '-') {
            $line = fgets($conn);
        }
        return trim((string)$line);
    };

    $greeting = $read();
    if (substr($greeting, 0, 3) !== '220') {
        fclose($conn);
        return [false, 'SMTP 服务器未就绪（' . $greeting . '）。'];
    }

    $heloHost = function_exists('gethostname') && gethostname() !== false ? gethostname() : 'localhost';
    $say('EHLO ' . $heloHost);
    $ehlo = $read();

    // STARTTLS 升级（tls 模式）
    if ($cfg['secure'] === 'tls') {
        $say('STARTTLS');
        $code = $read();
        if (substr($code, 0, 3) !== '220') {
            fclose($conn);
            return [false, 'STARTTLS 协商失败（' . $code . '）。'];
        }
        if (!@stream_socket_enable_crypto($conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($conn);
            return [false, 'TLS 加密握手失败。'];
        }
        $say('EHLO ' . $heloHost);
        $read();
    }

    // 认证（AUTH LOGIN，失败再试 AUTH PLAIN）
    if ($cfg['user'] !== '') {
        $say('AUTH LOGIN');
        $code = $read();
        if (substr($code, 0, 3) === '334') {
            $say(base64_encode($cfg['user']));
            $code = $read();
            if (substr($code, 0, 3) === '334') {
                $say(base64_encode($cfg['pass']));
                $code = $read();
                if (substr($code, 0, 3) !== '235') {
                    fclose($conn);
                    return [false, 'SMTP 用户名或密码被拒绝（' . $code . '）。'];
                }
            } else {
                fclose($conn);
                return [false, 'SMTP 用户名被拒绝（' . $code . '）。'];
            }
        } else {
            // LOGIN 不可用：尝试 PLAIN
            $say('AUTH PLAIN ' . base64_encode("\0" . $cfg['user'] . "\0" . $cfg['pass']));
            $code = $read();
            if (substr($code, 0, 3) !== '235') {
                fclose($conn);
                return [false, 'SMTP 认证失败（' . $code . '）。'];
            }
        }
    }

    // 发信
    $from = $cfg['from'] !== '' ? $cfg['from'] : $to;
    $say('MAIL FROM:<' . $from . '>');
    if (substr($read(), 0, 3) !== '250') {
        fclose($conn);
        return [false, 'MAIL FROM 被拒绝，请检查发件人地址。'];
    }
    $say('RCPT TO:<' . $to . '>');
    $rcpt = $read();
    if (substr($rcpt, 0, 3) !== '250' && substr($rcpt, 0, 3) !== '251') {
        fclose($conn);
        return [false, '收件人被服务器拒绝（' . $rcpt . '）。'];
    }
    $say('DATA');
    if (substr($read(), 0, 3) !== '354') {
        fclose($conn);
        return [false, 'DATA 阶段被拒绝。'];
    }
    fwrite($conn, "Subject: " . encode_mime($subject) . "\r\n");
    fwrite($conn, $header . "\r\n");
    fwrite($conn, $body . "\r\n.\r\n");
    $done = $read();
    $say('QUIT');
    fclose($conn);
    if (substr($done, 0, 3) !== '250') {
        return [false, '服务器投递失败（' . $done . '）。'];
    }
    return [true, null];
}
