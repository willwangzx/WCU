<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_enqueue_scripts', static function () {
    wp_enqueue_style(
        'wcu-forum-fonts',
        'https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Inter:wght@300;400;500;600;700&display=swap',
        [],
        null
    );

    wp_enqueue_style(
        'wcu-forum-overrides',
        get_stylesheet_directory_uri() . '/assets/css/wcu-forum.css',
        [],
        '2026.05.18'
    );
}, 30);

add_filter('body_class', static function ($classes) {
    $classes[] = 'wcu-forum-theme';
    return $classes;
});
