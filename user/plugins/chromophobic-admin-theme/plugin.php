<?php

/*
Plugin Name: Chromophobic Admin Theme
Plugin URI: https://github.com/gian-source/chromophobic-yourls
Description: Benutzerfreundliche Admin-Oberfläche für Chromophobic YOURLS.
Version: 5.0.1
Author: gian-source
Author URI: https://github.com/gian-source
*/

if (!defined('YOURLS_ABSPATH')) {
    die();
}

define('CHROMOPHOBIC_ADMIN_THEME_VERSION', '5.0.1');

yourls_add_action(
    'html_head',
    'chromophobic_admin_theme_styles',
    100
);

yourls_add_action(
    'html_footer',
    'chromophobic_admin_theme_scripts',
    100
);

function chromophobic_admin_theme_asset_url(string $file): string
{
    return rtrim((string) YOURLS_SITE, '/')
        . '/user/plugins/chromophobic-admin-theme/assets/'
        . ltrim($file, '/');
}

function chromophobic_admin_theme_styles($hook_arguments = null): void
{
    $url = chromophobic_admin_theme_asset_url('admin.css')
        . '?v='
        . rawurlencode(CHROMOPHOBIC_ADMIN_THEME_VERSION);

    echo '<link rel="stylesheet" href="'
        . yourls_esc_url($url)
        . '">';
}

function chromophobic_admin_theme_scripts($hook_arguments = null): void
{
    $url = chromophobic_admin_theme_asset_url('admin.js')
        . '?v='
        . rawurlencode(CHROMOPHOBIC_ADMIN_THEME_VERSION);

    echo '<script src="'
        . yourls_esc_url($url)
        . '" defer></script>';
}
