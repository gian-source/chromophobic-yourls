<?php

/*
Plugin Name: Chromophobic Admin Theme
Plugin URI: https://github.com/gian-source/chromophobic-yourls
Description: Modernes und responsives Admin-Design für die Chromophobic-YOURLS-Installation.
Version: 1.0.0
Author: gian-source
Author URI: https://github.com/gian-source
*/

if (!defined('YOURLS_ABSPATH')) {
    die();
}

define('CHROMOPHOBIC_ADMIN_THEME_VERSION', '1.0.0');

yourls_add_action(
    'html_head',
    'chromophobic_admin_theme_load_styles',
    20,
    1
);

/**
 * Lädt das Theme nur auf YOURLS-Administrationsseiten.
 *
 * YOURLS übergibt Action-Argumente als Array.
 */
function chromophobic_admin_theme_load_styles($hook_arguments): void
{
    if (!yourls_is_admin()) {
        return;
    }

    $context = is_array($hook_arguments)
        ? (string) ($hook_arguments[0] ?? '')
        : (string) $hook_arguments;

    $allowed_contexts = [
        'index',
        'bookmark',
        'plugins',
        'tools',
        'login',
        'infos',
    ];

    if (!in_array($context, $allowed_contexts, true)) {
        return;
    }

    $stylesheet_url =
        rtrim(YOURLS_SITE, '/') .
        '/user/plugins/chromophobic-admin-theme/admin.css?v=' .
        rawurlencode(CHROMOPHOBIC_ADMIN_THEME_VERSION);

    printf(
        '<link rel="stylesheet" href="%s" type="text/css" media="screen">',
        yourls_esc_url($stylesheet_url)
    );
}
