<?php

/*
Plugin Name: Chromophobic Admin Theme
Plugin URI: https://github.com/gian-source/chromophobic-yourls
Description: Modernes, macOS-inspiriertes Admin-Interface für YOURLS.
Version: 2.0.2
Author: gian-source
Author URI: https://github.com/gian-source
*/

if (!defined('YOURLS_ABSPATH')) {
    die();
}

define('CHROMOPHOBIC_ADMIN_THEME_VERSION', '2.0.2');
define(
    'CHROMOPHOBIC_ADMIN_THEME_URL',
    rtrim(YOURLS_SITE, '/') .
    '/user/plugins/chromophobic-admin-theme/assets'
);

yourls_add_action(
    'html_head',
    'chromophobic_admin_theme_head',
    20,
    1
);

yourls_add_action(
    'html_footer',
    'chromophobic_admin_theme_footer',
    20,
    1
);

/**
 * Lädt Styles und setzt die Theme-Klasse frühzeitig.
 */
function chromophobic_admin_theme_head($hook_arguments): void
{
    $context = chromophobic_admin_theme_context($hook_arguments);

    if (!chromophobic_admin_theme_is_supported($context)) {
        return;
    }

    $css_url =
        CHROMOPHOBIC_ADMIN_THEME_URL .
        '/admin.css?v=' .
        rawurlencode(CHROMOPHOBIC_ADMIN_THEME_VERSION);

    printf(
        '<link rel="stylesheet" href="%s" type="text/css" media="screen">',
        yourls_esc_url($css_url)
    );

    echo <<<'HTML'
<script>
document.documentElement.classList.add('cp-theme-loading');
</script>
HTML;
}

/**
 * Lädt JavaScript am Ende der Adminseite.
 */
function chromophobic_admin_theme_footer($hook_arguments): void
{
    $context = chromophobic_admin_theme_context($hook_arguments);

    if (!chromophobic_admin_theme_is_supported($context)) {
        return;
    }

    $js_url =
        CHROMOPHOBIC_ADMIN_THEME_URL .
        '/admin.js?v=' .
        rawurlencode(CHROMOPHOBIC_ADMIN_THEME_VERSION);

    printf(
        '<script src="%s" defer></script>',
        yourls_esc_url($js_url)
    );
}

/**
 * Liest den von YOURLS übergebenen Seitenkontext.
 */
function chromophobic_admin_theme_context($hook_arguments): string
{
    if (is_array($hook_arguments)) {
        return (string) ($hook_arguments[0] ?? '');
    }

    return (string) $hook_arguments;
}

/**
 * Beschränkt das Theme auf YOURLS-Adminseiten.
 */
function chromophobic_admin_theme_is_supported(string $context): bool
{
    $supported_contexts = [
        'index',
        'plugins',
        'tools',
        'bookmark',
        'login',
        'infos',
        'install',
        'upgrade',
    ];

    return in_array($context, $supported_contexts, true);
}