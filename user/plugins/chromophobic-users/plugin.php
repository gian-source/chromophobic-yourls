<?php

/*
Plugin Name: Chromophobic Users
Plugin URI: https://github.com/gian-source/chromophobic-yourls
Description: Datenbankbasierte Benutzerverwaltung für Chromophobic YOURLS.
Version: 0.1.0
Author: gian-source
Author URI: https://github.com/gian-source
*/

if (!defined('YOURLS_ABSPATH')) {
    die();
}

define('CHROMOPHOBIC_USERS_SCHEMA_VERSION', '1');
define(
    'CHROMOPHOBIC_USERS_TABLE',
    YOURLS_DB_PREFIX . 'chromophobic_users'
);

yourls_add_action(
    'plugins_loaded',
    'chromophobic_users_init'
);

yourls_add_filter(
    'admin_links',
    'chromophobic_users_add_admin_link'
);

/**
 * Plugin initialisieren.
 */
function chromophobic_users_init(): void
{
    chromophobic_users_maybe_install();

    yourls_register_plugin_page(
        'chromophobic-users',
        'Benutzer',
        'chromophobic_users_admin_page'
    );
}

/**
 * Eigene Tabelle einmalig erstellen.
 */
function chromophobic_users_maybe_install(): void
{
    $installed_version = yourls_get_option(
        'chromophobic_users_schema_version'
    );

    if (
        (string) $installed_version ===
        CHROMOPHOBIC_USERS_SCHEMA_VERSION
    ) {
        return;
    }

    $table = CHROMOPHOBIC_USERS_TABLE;

    $sql = "
        CREATE TABLE IF NOT EXISTS `$table` (
            `id` bigint unsigned NOT NULL AUTO_INCREMENT,
            `username` varchar(64) NOT NULL,
            `display_name` varchar(120) NOT NULL,
            `email` varchar(190) NOT NULL,
            `password_hash` varchar(255) NOT NULL,
            `role` varchar(20) NOT NULL DEFAULT 'user',
            `status` varchar(20) NOT NULL DEFAULT 'active',
            `created_at` datetime NOT NULL,
            `updated_at` datetime NOT NULL,
            `last_login_at` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `username` (`username`),
            UNIQUE KEY `email` (`email`)
        )
        ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_unicode_ci
    ";

    yourls_get_db('chromophobic-users-install')->perform($sql);

    yourls_update_option(
        'chromophobic_users_schema_version',
        CHROMOPHOBIC_USERS_SCHEMA_VERSION
    );
}

/**
 * Benutzer als eigenen Punkt in die Hauptnavigation aufnehmen.
 */
function chromophobic_users_add_admin_link(array $links): array
{
    $links['chromophobic_users'] = [
        'url' =>
            yourls_admin_url(
                'plugins.php?page=chromophobic-users'
            ),
        'anchor' => 'Benutzer',
        'title' => 'Benutzer verwalten',
    ];

    return $links;
}

/**
 * Admin-Seite ausgeben.
 */
function chromophobic_users_admin_page(): void
{
    chromophobic_users_handle_create();

    $users = chromophobic_users_get_all();

    ?>
    <div class="cp-users-page">

        <div class="cp-users-heading">
            <div>
                <h2>Benutzer</h2>
                <p>
                    Verwalte Personen, die Zugriff auf
                    den Link-Shortener erhalten sollen.
                </p>
            </div>
        </div>

        <div class="cp-users-layout">

            <section class="cp-users-card">
                <h3>Benutzer hinzufügen</h3>

                <form method="post">
                    <?php
                    yourls_nonce_field(
                        'chromophobic_users_create',
                        'chromophobic_users_nonce'
                    );
                    ?>

                    <input
                        type="hidden"
                        name="chromophobic_users_action"
                        value="create"
                    >

                    <p>
                        <label>
                            Benutzername
                            <input
                                type="text"
                                name="username"
                                required
                                autocomplete="off"
                            >
                        </label>
                    </p>

                    <p>
                        <label>
                            Anzeigename
                            <input
                                type="text"
                                name="display_name"
                                required
                            >
                        </label>
                    </p>

                    <p>
                        <label>
                            E-Mail
                            <input
                                type="email"
                                name="email"
                                required
                                autocomplete="email"
                            >
                        </label>
                    </p>

                    <p>
                        <label>
                            Passwort
                            <input
                                type="password"
                                name="password"
                                required
                                minlength="12"
                                autocomplete="new-password"
                            >
                        </label>
                    </p>

                    <p>
                        <label>
                            Rolle
                            <select name="role">
                                <option value="user">
                                    Benutzer
                                </option>
                                <option value="admin">
                                    Administrator
                                </option>
                            </select>
                        </label>
                    </p>

                    <p>
                        <button
                            type="submit"
                            class="button primary"
                        >
                            Benutzer erstellen
                        </button>
                    </p>
                </form>
            </section>

            <section class="cp-users-card cp-users-list">
                <h3>Vorhandene Benutzer</h3>

                <?php if (!$users): ?>

                    <p class="cp-users-empty">
                        Noch keine Benutzer in der neuen
                        Benutzerverwaltung vorhanden.
                    </p>

                <?php else: ?>

                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Benutzername</th>
                                <th>Rolle</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <?php
                                    echo yourls_esc_html(
                                        $user->display_name
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo yourls_esc_html(
                                        $user->username
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo $user->role === 'admin'
                                        ? 'Administrator'
                                        : 'Benutzer';
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo $user->status === 'active'
                                        ? 'Aktiv'
                                        : 'Deaktiviert';
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>

                <?php endif; ?>
            </section>

        </div>
    </div>
    <?php
}

/**
 * Formular zum Erstellen eines Benutzers verarbeiten.
 */
function chromophobic_users_handle_create(): void
{
    if (
        ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
        || ($_POST['chromophobic_users_action'] ?? '') !== 'create'
    ) {
        return;
    }

    yourls_verify_nonce(
        'chromophobic_users_create',
        $_POST['chromophobic_users_nonce'] ?? ''
    );

    $username = trim(
        (string) ($_POST['username'] ?? '')
    );

    $display_name = trim(
        (string) ($_POST['display_name'] ?? '')
    );

    $email = trim(
        (string) ($_POST['email'] ?? '')
    );

    $password = (string) ($_POST['password'] ?? '');

    $role = ($_POST['role'] ?? '') === 'admin'
        ? 'admin'
        : 'user';

    if (
        $username === ''
        || $display_name === ''
        || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || strlen($password) < 12
    ) {
        echo '<div class="error">';
        echo 'Bitte alle Felder korrekt ausfüllen.';
        echo '</div>';
        return;
    }

    if (
        !preg_match(
            '/^[a-zA-Z0-9._-]{3,64}$/',
            $username
        )
    ) {
        echo '<div class="error">';
        echo 'Der Benutzername enthält ungültige Zeichen.';
        echo '</div>';
        return;
    }

    $table = CHROMOPHOBIC_USERS_TABLE;

    $existing = yourls_get_db(
        'chromophobic-users-check'
    )->fetchValue(
        "
        SELECT COUNT(*)
        FROM `$table`
        WHERE `username` = :username
           OR `email` = :email
        ",
        [
            'username' => $username,
            'email' => $email,
        ]
    );

    if ((int) $existing > 0) {
        echo '<div class="error">';
        echo 'Benutzername oder E-Mail existiert bereits.';
        echo '</div>';
        return;
    }

    $now = date('Y-m-d H:i:s');

    $created = yourls_get_db(
        'chromophobic-users-create'
    )->fetchAffected(
        "
        INSERT INTO `$table`
        (
            `username`,
            `display_name`,
            `email`,
            `password_hash`,
            `role`,
            `status`,
            `created_at`,
            `updated_at`
        )
        VALUES
        (
            :username,
            :display_name,
            :email,
            :password_hash,
            :role,
            'active',
            :created_at,
            :updated_at
        )
        ",
        [
            'username' => $username,
            'display_name' => $display_name,
            'email' => $email,
            'password_hash' =>
                password_hash(
                    $password,
                    PASSWORD_DEFAULT
                ),
            'role' => $role,
            'created_at' => $now,
            'updated_at' => $now,
        ]
    );

    if ($created) {
        echo '<div class="success">';
        echo 'Benutzer wurde erstellt.';
        echo '</div>';
    }
}

/**
 * Alle Benutzer laden.
 */
function chromophobic_users_get_all(): array
{
    $table = CHROMOPHOBIC_USERS_TABLE;

    return yourls_get_db(
        'chromophobic-users-list'
    )->fetchObjects(
        "
        SELECT
            `id`,
            `username`,
            `display_name`,
            `email`,
            `role`,
            `status`,
            `created_at`,
            `last_login_at`
        FROM `$table`
        ORDER BY `display_name` ASC
        "
    );
}