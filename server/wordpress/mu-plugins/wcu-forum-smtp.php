<?php
/**
 * Routes WCU forum email through the SMTP settings stored in wp-config.php.
 *
 * The installer writes these constants from /root/.wcu-forum-prod.env. This
 * file intentionally contains no credentials.
 */

if (!defined('ABSPATH')) {
    exit;
}

function wcu_forum_constant($name, $default = '')
{
    return defined($name) ? constant($name) : $default;
}

add_filter('wp_mail_from', static function ($from) {
    $configured = wcu_forum_constant('WCU_FORUM_MAIL_FROM');
    return $configured ?: $from;
});

add_filter('wp_mail_from_name', static function ($name) {
    $configured = wcu_forum_constant('WCU_FORUM_MAIL_FROM_NAME');
    return $configured ?: $name;
});

add_action('phpmailer_init', static function ($phpmailer) {
    $host = wcu_forum_constant('WCU_FORUM_SMTP_HOST');
    if (!$host) {
        return;
    }

    $phpmailer->isSMTP();
    $phpmailer->Host = $host;
    $phpmailer->Port = (int) wcu_forum_constant('WCU_FORUM_SMTP_PORT', 587);

    $secure = strtolower((string) wcu_forum_constant('WCU_FORUM_SMTP_SECURE', 'tls'));
    if ($secure === 'ssl' || $secure === 'tls') {
        $phpmailer->SMTPSecure = $secure;
    } else {
        $phpmailer->SMTPSecure = '';
        $phpmailer->SMTPAutoTLS = false;
    }

    $username = wcu_forum_constant('WCU_FORUM_SMTP_USER');
    $password = wcu_forum_constant('WCU_FORUM_SMTP_PASSWORD');
    if ($username || $password) {
        $phpmailer->SMTPAuth = true;
        $phpmailer->Username = $username;
        $phpmailer->Password = $password;
    }

    $from = wcu_forum_constant('WCU_FORUM_MAIL_FROM');
    $from_name = wcu_forum_constant('WCU_FORUM_MAIL_FROM_NAME');
    if ($from) {
        $phpmailer->setFrom($from, $from_name ?: 'William Chichi University Forum', false);
    }
});
