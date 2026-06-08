<?php

if (!defined('ABSPATH')) {
    fwrite(STDERR, "Run this file with WP-CLI: wp eval-file seed-wpforo-forum.php\n");
    exit(1);
}

if (!function_exists('WPF')) {
    fwrite(STDERR, "wpForo is not loaded. Activate the wpforo plugin first.\n");
    exit(1);
}

function wcu_forum_bootstrap_wpforo()
{
    if (empty($_SERVER['HTTP_HOST'])) {
        $_SERVER['HTTP_HOST'] = 'forum.wcuedu.net';
    }
    if (empty($_SERVER['SERVER_NAME'])) {
        $_SERVER['SERVER_NAME'] = $_SERVER['HTTP_HOST'];
    }
    if (empty($_SERVER['REQUEST_URI'])) {
        $_SERVER['REQUEST_URI'] = '/community/';
    }
    if (empty($_SERVER['SERVER_PORT'])) {
        $_SERVER['SERVER_PORT'] = '443';
    }
    if (empty($_SERVER['HTTPS'])) {
        $_SERVER['HTTPS'] = 'on';
    }

    $wpforo = WPF();

    if (empty($wpforo->settings) || empty($wpforo->board) || empty($wpforo->tables)) {
        try {
            $reflection = new ReflectionObject($wpforo);
            $method = $reflection->getMethod('init_base_classes');
            $method->setAccessible(true);
            $method->invoke($wpforo);
        } catch (ReflectionException $exception) {
            fwrite(STDERR, "Could not initialize wpForo base classes: {$exception->getMessage()}\n");
            exit(1);
        }
    }

    if (method_exists($wpforo, 'change_board')) {
        $wpforo->change_board(['boardid' => 0]);
    }

    if (empty($wpforo->forum) || empty($wpforo->topic) || empty($wpforo->post)) {
        $wpforo->init();
    }

    if (
        empty($wpforo->forum)
        || empty($wpforo->topic)
        || empty($wpforo->tables->forums)
        || empty($wpforo->tables->topics)
        || empty($wpforo->tables->posts)
    ) {
        fwrite(STDERR, "wpForo did not expose the expected forum, topic, and table APIs.\n");
        exit(1);
    }
}

wcu_forum_bootstrap_wpforo();

$admin_login = getenv('WCU_FORUM_ADMIN_USER') ?: 'wcu_forum_admin';
$admin = get_user_by('login', $admin_login);

if (!$admin) {
    $admins = get_users([
        'role' => 'administrator',
        'number' => 1,
        'orderby' => 'ID',
        'order' => 'ASC',
    ]);
    $admin = $admins ? $admins[0] : null;
}

if (!$admin) {
    fwrite(STDERR, "No WordPress administrator found for seeding topics.\n");
    exit(1);
}

wp_set_current_user((int) $admin->ID);
if (!empty(WPF()->member) && method_exists(WPF()->member, 'init_current_user')) {
    WPF()->member->init_current_user();
} else {
    WPF()->current_userid = (int) $admin->ID;
}

add_filter(
    'wpforo_permissions_forum_can',
    static function ($can) {
        return current_user_can('administrator') ? 1 : $can;
    },
    10,
    4
);

function wcu_forum_existing_by_slug($slug)
{
    return WPF()->db->get_row(
        WPF()->db->prepare(
            'SELECT * FROM ' . WPF()->tables->forums . ' WHERE slug = %s LIMIT 1',
            $slug
        ),
        ARRAY_A
    );
}

function wcu_forum_add_or_get($title, $slug, $description, $parentid, $layout, $is_cat, $order, $color)
{
    $existing = wcu_forum_existing_by_slug($slug);
    if ($existing) {
        return (int) $existing['forumid'];
    }

    $forumid = WPF()->forum->add([
        'title' => $title,
        'slug' => $slug,
        'description' => $description,
        'parentid' => (int) $parentid,
        'layout' => (int) $layout,
        'is_cat' => (int) $is_cat,
        'status' => 1,
        'order' => (int) $order,
        'color' => $color,
        'icon' => '',
        'type' => 'forum',
        'meta_key' => '',
        'meta_desc' => $description,
    ], false);

    if (!$forumid) {
        throw new RuntimeException("Could not create wpForo forum: {$title}");
    }

    return (int) $forumid;
}

function wcu_forum_delete_default_if_empty()
{
    $topic_count = (int) WPF()->db->get_var('SELECT COUNT(*) FROM ' . WPF()->tables->topics);
    $post_count = (int) WPF()->db->get_var('SELECT COUNT(*) FROM ' . WPF()->tables->posts);

    if ($topic_count !== 0 || $post_count !== 0) {
        return;
    }

    $main_category = wcu_forum_existing_by_slug('main-category');
    $main_forum = wcu_forum_existing_by_slug('main-forum');

    if (
        $main_category
        && $main_forum
        && (int) $main_category['parentid'] === 0
        && (int) $main_forum['parentid'] === (int) $main_category['forumid']
    ) {
        WPF()->forum->delete((int) $main_category['forumid'], false);
    }
}

function wcu_forum_configure_board()
{
    if (empty(WPF()->board) || !method_exists(WPF()->board, 'edit')) {
        return;
    }

    $pageid = (int) get_option('wpforo_pageid');
    WPF()->board->edit([
        'title' => 'Forums',
        'slug' => 'community',
        'pageid' => $pageid,
        'settings' => [
            'title' => 'William Chichi University Forum',
            'desc' => 'A community hub for projects, academic questions, resources, activities, and collaboration.',
        ],
    ], 0);
}

function wcu_forum_update_authorization_settings()
{
    update_option('users_can_register', 1);
    update_option('default_role', 'subscriber');

    $authorization = get_option('wpforo_authorization', []);
    if (!is_array($authorization)) {
        $authorization = [];
    }

    $authorization['user_register'] = 1;
    $authorization['user_register_email_confirm'] = 1;
    $authorization['manually_approval'] = 0;
    $authorization['use_our_register_url'] = 0;
    $authorization['use_our_login_url'] = 0;
    $authorization['use_our_lostpassword_url'] = 1;

    update_option('wpforo_authorization', $authorization);
}

function wcu_forum_update_email_settings()
{
    $from_email = defined('WCU_FORUM_MAIL_FROM') && WCU_FORUM_MAIL_FROM
        ? WCU_FORUM_MAIL_FROM
        : get_option('admin_email');
    $from_name = defined('WCU_FORUM_MAIL_FROM_NAME') && WCU_FORUM_MAIL_FROM_NAME
        ? WCU_FORUM_MAIL_FROM_NAME
        : get_option('blogname') . ' - Forum';

    $email = get_option('wpforo_email', []);
    if (!is_array($email)) {
        $email = [];
    }

    $email['from_name'] = $from_name;
    $email['from_email'] = $from_email;
    $email['admin_emails'] = [get_option('admin_email')];
    $email['overwrite_new_user_notification'] = 1;
    $email['wp_new_user_notification_email_subject'] = '[blogname] Confirm your forum account';
    $email['wp_new_user_notification_email_message'] = "Welcome to [blogname].\n\nUsername: [user_login]\n\nTo confirm your email address and set your password, visit:\n\n[set_password_url]\n\nIf you did not request this account, you can ignore this message.";

    update_option('wpforo_email', $email);
}

function wcu_forum_topic_exists($forumid, $slug)
{
    return (bool) WPF()->db->get_var(
        WPF()->db->prepare(
            'SELECT topicid FROM ' . WPF()->tables->topics . ' WHERE forumid = %d AND slug = %s LIMIT 1',
            (int) $forumid,
            $slug
        )
    );
}

function wcu_forum_seed_topic($forumid, $title, $slug, $body)
{
    if (wcu_forum_topic_exists($forumid, $slug)) {
        return;
    }

    if (!empty(WPF()->notice) && method_exists(WPF()->notice, 'clear')) {
        WPF()->notice->clear();
    }

    $topicid = WPF()->topic->add([
        'forumid' => (int) $forumid,
        'title' => $title,
        'slug' => $slug,
        'body' => $body,
        'userid' => WPF()->current_userid,
        'status' => 0,
        'type' => 0,
        'private' => 0,
        'is_ai_generated' => 0,
    ]);

    if (!$topicid) {
        $notice = '';
        if (!empty(WPF()->notice) && method_exists(WPF()->notice, 'get_notices')) {
            $notice = trim(wp_strip_all_tags(WPF()->notice->get_notices()));
        }
        $suffix = $notice ? ": {$notice}" : '';
        throw new RuntimeException("Could not create wpForo topic: {$title}{$suffix}");
    }
}

function wcu_forum_update_antispam_settings()
{
    $antispam = get_option('wpforo_antispam', []);
    if (!is_array($antispam)) {
        $antispam = [];
    }

    $antispam['spam_filter'] = 1;
    $antispam['spam_user_ban'] = 0;
    $antispam['new_user_max_posts'] = 3;
    $antispam['unapprove_post_if_user_is_new'] = 1;
    $antispam['min_number_posts_to_attach'] = 3;
    $antispam['min_number_posts_to_link'] = 3;
    $antispam['flood_protection_enabled'] = 1;
    $antispam['flood_posts_per_minute'] = 5;
    $antispam['flood_posts_per_hour'] = 30;
    $antispam['flood_ip_protection_enabled'] = 0;
    $antispam['flood_posts_per_ip_hour'] = 30;
    $antispam['flood_action'] = 'block';
    $antispam['flood_temp_ban_duration'] = '15';

    update_option('wpforo_antispam', $antispam);
}

function wcu_forum_update_recaptcha_settings()
{
    $site_key = trim((string) getenv('WCU_FORUM_RECAPTCHA_SITE_KEY'));
    $secret_key = trim((string) getenv('WCU_FORUM_RECAPTCHA_SECRET_KEY'));

    if ($site_key === '' && $secret_key === '') {
        return;
    }

    if ($site_key === '' || $secret_key === '') {
        fwrite(STDERR, "Skipping forum reCAPTCHA: both WCU_FORUM_RECAPTCHA_SITE_KEY and WCU_FORUM_RECAPTCHA_SECRET_KEY are required.\n");
        return;
    }

    $recaptcha = get_option('wpforo_recaptcha', []);
    if (!is_array($recaptcha)) {
        $recaptcha = [];
    }

    $recaptcha['site_key'] = $site_key;
    $recaptcha['secret_key'] = $secret_key;
    $recaptcha['theme'] = trim((string) getenv('WCU_FORUM_RECAPTCHA_THEME')) ?: 'light';

    $version = trim((string) getenv('WCU_FORUM_RECAPTCHA_VERSION'));
    if ($version !== '') {
        $recaptcha['version'] = $version;
    }

    $score_threshold = trim((string) getenv('WCU_FORUM_RECAPTCHA_SCORE_THRESHOLD'));
    if ($score_threshold !== '') {
        $recaptcha['score_threshold'] = $score_threshold;
    }

    foreach ([
        'topic_editor',
        'post_editor',
        'wpf_login_form',
        'wpf_reg_form',
        'wpf_lostpass_form',
        'login_form',
        'reg_form',
        'lostpass_form',
    ] as $flag) {
        $recaptcha[$flag] = 1;
    }

    update_option('wpforo_recaptcha', $recaptcha);
}

function wcu_forum_disable_ai_usergroup_permissions()
{
    if (empty(WPF()->usergroup) || !method_exists(WPF()->usergroup, 'edit')) {
        return;
    }

    $groups = WPF()->usergroup->get_usergroups();
    foreach ((array) $groups as $group) {
        $cans = maybe_unserialize($group['cans']);
        if (!is_array($cans)) {
            $cans = [];
        }

        $cans['ai_search'] = 0;
        $cans['ai_summary'] = 0;
        $cans['ai_translation'] = 0;
        $cans['ai_suggestion'] = 0;

        WPF()->usergroup->edit(
            (int) $group['groupid'],
            $group['name'],
            $cans,
            $group['description'],
            $group['role'],
            $group['access'],
            $group['color'],
            (int) $group['visible'],
            (int) $group['secondary']
        );
    }
}

wcu_forum_delete_default_if_empty();
wcu_forum_configure_board();
wcu_forum_update_authorization_settings();
wcu_forum_update_email_settings();

$structure = [
    [
        'title' => 'Start Here',
        'slug' => 'start-here',
        'description' => 'Forum orientation, announcements, guidelines, and feedback.',
        'layout' => 4,
        'color' => '#0b3d91',
        'forums' => [
            ['Announcements', 'announcements', 'Official updates about the WCU community and forum.'],
            ['Community Guidelines', 'community-guidelines', 'How to participate with clarity, respect, and useful detail.'],
            ['Site Feedback', 'site-feedback', 'Report issues and suggest improvements for the WCU website and forum.'],
        ],
    ],
    [
        'title' => 'Projects & Collaboration',
        'slug' => 'projects-collaboration',
        'description' => 'Find teammates, share build progress, and organize activities.',
        'layout' => 4,
        'color' => '#1f5dff',
        'forums' => [
            ['Project Ideas & Team-Up', 'project-ideas-team-up', 'Pitch ideas and find collaborators.'],
            ['Build Logs & Showcases', 'build-logs-showcases', 'Share progress, demos, prototypes, and finished work.'],
            ['Events & Activities', 'events-activities', 'Plan workshops, study sessions, and community activities.'],
        ],
    ],
    [
        'title' => 'Academic Q&A',
        'slug' => 'academic-qa',
        'description' => 'Ask subject questions and work through answers together.',
        'layout' => 3,
        'color' => '#b88a2d',
        'forums' => [
            ['Computing & AI', 'computing-ai', 'Programming, AI, data, systems, and responsible technology.'],
            ['Engineering & Natural Sciences', 'engineering-natural-sciences', 'Engineering, physics, chemistry, biology, and applied science.'],
            ['Business & Management', 'business-management', 'Business models, economics, finance, management, and ventures.'],
            ['Arts, Humanities & Social Science', 'arts-humanities-social-science', 'Writing, literature, art, culture, history, and society.'],
        ],
    ],
    [
        'title' => 'Resources & Experience',
        'slug' => 'resources-experience',
        'description' => 'Share notes, tools, templates, and learning experience.',
        'layout' => 4,
        'color' => '#0f766e',
        'forums' => [
            ['Study Notes & Guides', 'study-notes-guides', 'Post concise notes, study systems, and course guides.'],
            ['IB Experience', 'ib-experience', 'Discuss IB learning, assessment, and preparation experience.'],
            ['Tools & Templates', 'tools-templates', 'Share useful tools, templates, checklists, and workflows.'],
            ['Fun & Useful Tools', 'fun-useful-tools', 'Share small interactive tools, calculators, visualizers, templates, and learning utilities.'],
        ],
    ],
    [
        'title' => 'Admissions & Campus',
        'slug' => 'admissions-campus',
        'description' => 'Ask about admissions, campus life, and international student experience.',
        'layout' => 4,
        'color' => '#7c3aed',
        'forums' => [
            ['Admissions Questions', 'admissions-questions', 'Application, requirements, scholarships, and program questions.'],
            ['Campus Life', 'campus-life', 'Housing, student life, services, and community culture.'],
            ['International Students', 'international-students', 'Visas, travel, preparation, and cross-cultural support.'],
        ],
    ],
];

$created_forums = [];
$category_order = 10;

foreach ($structure as $category) {
    $category_id = wcu_forum_add_or_get(
        $category['title'],
        $category['slug'],
        $category['description'],
        0,
        $category['layout'],
        1,
        $category_order,
        $category['color']
    );

    $forum_order = 10;
    foreach ($category['forums'] as $forum) {
        $created_forums[$forum[1]] = wcu_forum_add_or_get(
            $forum[0],
            $forum[1],
            $forum[2],
            $category_id,
            $category['layout'],
            0,
            $forum_order,
            $category['color']
        );
        $forum_order += 10;
    }

    $category_order += 10;
}

$welcome = <<<HTML
Welcome to the William Chichi University forum.

Use this community to ask precise questions, find collaborators, share notes and tools, and show what you are building. Clear titles, useful context, and respectful replies make the forum easier for everyone to learn from.
HTML;

$guidelines = <<<HTML
Please keep discussions specific, constructive, and safe.

- Use descriptive titles.
- Share enough context for others to help.
- Credit sources and collaborators.
- Do not post private credentials, private documents, or personal data.
- Keep criticism focused on ideas and work, not people.
HTML;

$project_prompt = <<<HTML
Starting a project? Include the problem, the skills needed, the current status, and what kind of help you want.

Good project posts make it easy for classmates, mentors, and future students to decide whether they can contribute.
HTML;

$logic_lab_prompt = <<<HTML
Logic Lab is an interactive tool for exploring logic gates, truth tables, Boolean expressions, and simple logic statements.

Open it here:
https://forum.wcuedu.net/tools/logic-lab/

Use this board to share other small utilities that help students learn, prototype, calculate, visualize, or organize their work.
HTML;

wcu_forum_seed_topic($created_forums['announcements'], 'Welcome to the WCU Forum', 'welcome-to-the-wcu-forum', $welcome);
wcu_forum_seed_topic($created_forums['community-guidelines'], 'Community Guidelines', 'community-guidelines', $guidelines);
wcu_forum_seed_topic($created_forums['project-ideas-team-up'], 'How to post a project idea', 'how-to-post-a-project-idea', $project_prompt);
wcu_forum_seed_topic($created_forums['fun-useful-tools'], 'Logic Lab: logic gates, truth tables, and Boolean expressions', 'logic-lab-logic-gates-truth-tables-boolean-expressions', $logic_lab_prompt);

wcu_forum_update_antispam_settings();
wcu_forum_update_recaptcha_settings();
wcu_forum_disable_ai_usergroup_permissions();

if (method_exists(WPF()->forum, 'delete_tree_cache')) {
    WPF()->forum->delete_tree_cache();
}
if (function_exists('wpforo_clean_cache')) {
    wpforo_clean_cache();
}

echo "WCU wpForo forum structure seeded.\n";
