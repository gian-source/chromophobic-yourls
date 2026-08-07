<?php

/*
Plugin Name: Chromophobic Users
Plugin URI: https://github.com/gian-source/chromophobic-yourls
Description: Datenbankbasierte Benutzerverwaltung für Chromophobic YOURLS.
Version: 0.2.0
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
    chromophobic_users_handle_update();
    chromophobic_users_handle_status();
    chromophobic_users_handle_delete();

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

        <?php chromophobic_users_render_notice(); ?>

        <section class="cp-users-card">
            <div class="cp-users-card-header">
                <div>
                    <h3>Benutzer hinzufügen</h3>
                    <p>Neuen Zugang für den Shortener erstellen.</p>
                </div>
            </div>

            <form method="post" class="cp-users-form">
                <?php
                yourls_nonce_field(
                    'chromophobic_users_create'
                );
                ?>

                <input
                    type="hidden"
                    name="chromophobic_users_action"
                    value="create"
                >

                <div class="cp-users-form-grid">

                    <label>
                        <span>Benutzername</span>
                        <input
                            type="text"
                            name="new_username"
                            required
                            minlength="3"
                            maxlength="64"
                            autocomplete="off"
                            placeholder="z. B. max"
                        >
                    </label>

                    <label>
                        <span>Anzeigename</span>
                        <input
                            type="text"
                            name="display_name"
                            required
                            maxlength="120"
                            placeholder="Max Muster"
                        >
                    </label>

                    <label>
                        <span>E-Mail</span>
                        <input
                            type="email"
                            name="email"
                            required
                            maxlength="190"
                            autocomplete="email"
                            placeholder="max@example.com"
                        >
                    </label>

                    <label>
                        <span>Rolle</span>
                        <select name="role">
                            <option value="user">Benutzer</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </label>

                    <label class="cp-users-password">
                        <span>Passwort</span>
                        <input
                            type="password"
                            name="new_password"
                            required
                            minlength="12"
                            autocomplete="new-password"
                        >
                        <small>Mindestens 12 Zeichen</small>
                    </label>

                </div>

                <div class="cp-users-form-actions">
                    <button
                        type="submit"
                        class="button primary"
                    >
                        Benutzer erstellen
                    </button>
                </div>
            </form>
        </section>

        <div class="cp-users-layout">
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
                                <th>E-Mail</th>
                                <th>Rolle</th>
                                <th>Status</th>
                                <th>Aktionen</th>
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
                                    echo yourls_esc_html(
                                        $user->email
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

                                <td>
                                    <div class="cp-users-actions">
                                        <form method="post">
                                            <?php
                                            yourls_nonce_field(
                                                'chromophobic_users_status_' .
                                                $user->id
                                            );
                                            ?>

                                            <input
                                                type="hidden"
                                                name="chromophobic_users_action"
                                                value="toggle_status"
                                            >

                                            <input
                                                type="hidden"
                                                name="user_id"
                                                value="<?php echo (int) $user->id; ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="button"
                                            >
                                                <?php
                                                echo $user->status === 'active'
                                                    ? 'Deaktivieren'
                                                    : 'Aktivieren';
                                                ?>
                                            </button>
                                        </form>

                                        <details class="cp-users-edit">
                                            <summary class="button">
                                                Bearbeiten
                                            </summary>

                                            <form
                                                method="post"
                                                class="cp-users-form"
                                            >
                                                <?php
                                                yourls_nonce_field(
                                                    'chromophobic_users_update_' .
                                                    $user->id
                                                );
                                                ?>

                                                <input
                                                    type="hidden"
                                                    name="chromophobic_users_action"
                                                    value="update"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="user_id"
                                                    value="<?php echo (int) $user->id; ?>"
                                                >

                                                <div class="cp-users-form-grid">
                                                    <label>
                                                        <span>Benutzername</span>
                                                        <input
                                                            type="text"
                                                            name="edit_username"
                                                            required
                                                            minlength="3"
                                                            maxlength="64"
                                                            autocomplete="off"
                                                            value="<?php echo chromophobic_users_esc_attr((string) $user->username); ?>"
                                                        >
                                                    </label>

                                                    <label>
                                                        <span>Anzeigename</span>
                                                        <input
                                                            type="text"
                                                            name="display_name"
                                                            required
                                                            maxlength="120"
                                                            value="<?php echo chromophobic_users_esc_attr((string) $user->display_name); ?>"
                                                        >
                                                    </label>

                                                    <label>
                                                        <span>E-Mail</span>
                                                        <input
                                                            type="email"
                                                            name="email"
                                                            required
                                                            maxlength="190"
                                                            value="<?php echo chromophobic_users_esc_attr((string) $user->email); ?>"
                                                        >
                                                    </label>

                                                    <label>
                                                        <span>Rolle</span>
                                                        <select name="role">
                                                            <option
                                                                value="user"
                                                                <?php echo $user->role === 'user' ? 'selected' : ''; ?>
                                                            >
                                                                Benutzer
                                                            </option>
                                                            <option
                                                                value="admin"
                                                                <?php echo $user->role === 'admin' ? 'selected' : ''; ?>
                                                            >
                                                                Administrator
                                                            </option>
                                                        </select>
                                                    </label>

                                                    <label class="cp-users-password">
                                                        <span>Neues Passwort</span>
                                                        <input
                                                            type="password"
                                                            name="edit_password"
                                                            minlength="12"
                                                            autocomplete="new-password"
                                                        >
                                                        <small>
                                                            Leer lassen, um das
                                                            aktuelle Passwort zu behalten.
                                                        </small>
                                                    </label>
                                                </div>

                                                <div class="cp-users-form-actions">
                                                    <button
                                                        type="submit"
                                                        class="button primary"
                                                    >
                                                        Änderungen speichern
                                                    </button>
                                                </div>
                                            </form>
                                        </details>

                                        <form
                                            method="post"
                                            onsubmit="return confirm('Diesen Benutzer wirklich löschen?');"
                                        >
                                            <?php
                                            yourls_nonce_field(
                                                'chromophobic_users_delete_' .
                                                $user->id
                                            );
                                            ?>

                                            <input
                                                type="hidden"
                                                name="chromophobic_users_action"
                                                value="delete"
                                            >

                                            <input
                                                type="hidden"
                                                name="user_id"
                                                value="<?php echo (int) $user->id; ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="button"
                                            >
                                                Löschen
                                            </button>
                                        </form>
                                    </div>
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
        'chromophobic_users_create'
    );

    $username = trim(
        (string) ($_POST['new_username'] ?? '')
    );

    $display_name = trim(
        (string) ($_POST['display_name'] ?? '')
    );

    $email = strtolower(trim(
        (string) ($_POST['email'] ?? '')
    ));

    $password = (string) ($_POST['new_password'] ?? '');

    $role = ($_POST['role'] ?? '') === 'admin'
        ? 'admin'
        : 'user';

    if (
        $username === ''
        || $display_name === ''
        || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || strlen($password) < 12
    ) {
        chromophobic_users_redirect(
            'Bitte alle Felder korrekt ausfüllen.',
            'error'
        );
    }

    if (!chromophobic_users_valid_username($username)) {
        chromophobic_users_redirect(
            'Der Benutzername enthält ungültige Zeichen.',
            'error'
        );
    }

    $table = CHROMOPHOBIC_USERS_TABLE;

    $existing = yourls_get_db(
        'chromophobic-users-create-check'
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
        chromophobic_users_redirect(
            'Benutzername oder E-Mail existiert bereits.',
            'error'
        );
    }

    $now = date('Y-m-d H:i:s');

    yourls_get_db(
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
            'password_hash' => password_hash(
                $password,
                PASSWORD_DEFAULT
            ),
            'role' => $role,
            'created_at' => $now,
            'updated_at' => $now,
        ]
    );

    chromophobic_users_redirect(
        'Benutzer wurde erstellt.'
    );
}

/**
 * Benutzer bearbeiten und optional Passwort ändern.
 */
function chromophobic_users_handle_update(): void
{
    if (
        ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
        || ($_POST['chromophobic_users_action'] ?? '') !== 'update'
    ) {
        return;
    }

    $user_id = (int) ($_POST['user_id'] ?? 0);

    if ($user_id <= 0) {
        chromophobic_users_redirect(
            'Ungültiger Benutzer.',
            'error'
        );
    }

    yourls_verify_nonce(
        'chromophobic_users_update_' . $user_id
    );

    $user = chromophobic_users_get_by_id($user_id);

    if (!$user) {
        chromophobic_users_redirect(
            'Benutzer wurde nicht gefunden.',
            'error'
        );
    }

    $username = trim(
        (string) ($_POST['edit_username'] ?? '')
    );

    $display_name = trim(
        (string) ($_POST['display_name'] ?? '')
    );

    $email = strtolower(trim(
        (string) ($_POST['email'] ?? '')
    ));

    $password = (string) ($_POST['edit_password'] ?? '');

    $role = ($_POST['role'] ?? '') === 'admin'
        ? 'admin'
        : 'user';

    if (
        $username === ''
        || $display_name === ''
        || !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        chromophobic_users_redirect(
            'Bitte alle Felder korrekt ausfüllen.',
            'error'
        );
    }

    if (!chromophobic_users_valid_username($username)) {
        chromophobic_users_redirect(
            'Der Benutzername enthält ungültige Zeichen.',
            'error'
        );
    }

    if ($password !== '' && strlen($password) < 12) {
        chromophobic_users_redirect(
            'Das neue Passwort muss mindestens 12 Zeichen lang sein.',
            'error'
        );
    }

    if (
        $user->role === 'admin'
        && $user->status === 'active'
        && $role !== 'admin'
        && chromophobic_users_active_admin_count() <= 1
    ) {
        chromophobic_users_redirect(
            'Der letzte aktive Administrator kann nicht zum Benutzer herabgestuft werden.',
            'error'
        );
    }

    $table = CHROMOPHOBIC_USERS_TABLE;

    $existing = yourls_get_db(
        'chromophobic-users-update-check'
    )->fetchValue(
        "
        SELECT COUNT(*)
        FROM `$table`
        WHERE (`username` = :username OR `email` = :email)
          AND `id` <> :id
        ",
        [
            'username' => $username,
            'email' => $email,
            'id' => $user_id,
        ]
    );

    if ((int) $existing > 0) {
        chromophobic_users_redirect(
            'Benutzername oder E-Mail existiert bereits.',
            'error'
        );
    }

    $params = [
        'username' => $username,
        'display_name' => $display_name,
        'email' => $email,
        'role' => $role,
        'updated_at' => date('Y-m-d H:i:s'),
        'id' => $user_id,
    ];

    if ($password !== '') {
        $params['password_hash'] = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $sql = "
            UPDATE `$table`
            SET
                `username` = :username,
                `display_name` = :display_name,
                `email` = :email,
                `role` = :role,
                `password_hash` = :password_hash,
                `updated_at` = :updated_at
            WHERE `id` = :id
        ";
    } else {
        $sql = "
            UPDATE `$table`
            SET
                `username` = :username,
                `display_name` = :display_name,
                `email` = :email,
                `role` = :role,
                `updated_at` = :updated_at
            WHERE `id` = :id
        ";
    }

    yourls_get_db(
        'chromophobic-users-update'
    )->fetchAffected(
        $sql,
        $params
    );

    chromophobic_users_redirect(
        $password === ''
            ? 'Benutzer wurde aktualisiert.'
            : 'Benutzer und Passwort wurden aktualisiert.'
    );
}

/**
 * Benutzer aktivieren oder deaktivieren.
 */
function chromophobic_users_handle_status(): void
{
    if (
        ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
        || ($_POST['chromophobic_users_action'] ?? '')
            !== 'toggle_status'
    ) {
        return;
    }

    $user_id = (int) ($_POST['user_id'] ?? 0);

    if ($user_id <= 0) {
        chromophobic_users_redirect(
            'Ungültiger Benutzer.',
            'error'
        );
    }

    yourls_verify_nonce(
        'chromophobic_users_status_' . $user_id
    );

    $user = chromophobic_users_get_by_id($user_id);

    if (!$user) {
        chromophobic_users_redirect(
            'Benutzer wurde nicht gefunden.',
            'error'
        );
    }

    if (
        $user->role === 'admin'
        && $user->status === 'active'
        && chromophobic_users_active_admin_count() <= 1
    ) {
        chromophobic_users_redirect(
            'Der letzte aktive Administrator kann nicht deaktiviert werden.',
            'error'
        );
    }

    $new_status = $user->status === 'active'
        ? 'disabled'
        : 'active';

    $table = CHROMOPHOBIC_USERS_TABLE;

    yourls_get_db(
        'chromophobic-users-status-update'
    )->fetchAffected(
        "
        UPDATE `$table`
        SET
            `status` = :status,
            `updated_at` = :updated_at
        WHERE `id` = :id
        ",
        [
            'status' => $new_status,
            'updated_at' => date('Y-m-d H:i:s'),
            'id' => $user_id,
        ]
    );

    chromophobic_users_redirect(
        $new_status === 'active'
            ? 'Benutzer wurde aktiviert.'
            : 'Benutzer wurde deaktiviert.'
    );
}

/**
 * Benutzer löschen.
 */
function chromophobic_users_handle_delete(): void
{
    if (
        ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
        || ($_POST['chromophobic_users_action'] ?? '') !== 'delete'
    ) {
        return;
    }

    $user_id = (int) ($_POST['user_id'] ?? 0);

    if ($user_id <= 0) {
        chromophobic_users_redirect(
            'Ungültiger Benutzer.',
            'error'
        );
    }

    yourls_verify_nonce(
        'chromophobic_users_delete_' . $user_id
    );

    $user = chromophobic_users_get_by_id($user_id);

    if (!$user) {
        chromophobic_users_redirect(
            'Benutzer wurde nicht gefunden.',
            'error'
        );
    }

    if (
        $user->role === 'admin'
        && $user->status === 'active'
        && chromophobic_users_active_admin_count() <= 1
    ) {
        chromophobic_users_redirect(
            'Der letzte aktive Administrator kann nicht gelöscht werden.',
            'error'
        );
    }

    $table = CHROMOPHOBIC_USERS_TABLE;

    yourls_get_db(
        'chromophobic-users-delete'
    )->fetchAffected(
        "
        DELETE FROM `$table`
        WHERE `id` = :id
        ",
        [
            'id' => $user_id,
        ]
    );

    chromophobic_users_redirect(
        'Benutzer wurde gelöscht.'
    );
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

/**
 * Einzelnen Benutzer laden.
 */
function chromophobic_users_get_by_id(int $user_id): ?object
{
    $table = CHROMOPHOBIC_USERS_TABLE;

    $user = yourls_get_db(
        'chromophobic-users-get'
    )->fetchObject(
        "
        SELECT
            `id`,
            `username`,
            `display_name`,
            `email`,
            `role`,
            `status`,
            `created_at`,
            `updated_at`,
            `last_login_at`
        FROM `$table`
        WHERE `id` = :id
        LIMIT 1
        ",
        [
            'id' => $user_id,
        ]
    );

    return $user ?: null;
}

/**
 * Anzahl aktiver Administratoren ermitteln.
 */
function chromophobic_users_active_admin_count(): int
{
    $table = CHROMOPHOBIC_USERS_TABLE;

    return (int) yourls_get_db(
        'chromophobic-users-admin-count'
    )->fetchValue(
        "
        SELECT COUNT(*)
        FROM `$table`
        WHERE `role` = 'admin'
          AND `status` = 'active'
        "
    );
}

/**
 * Benutzername validieren.
 */
function chromophobic_users_valid_username(string $username): bool
{
    return (bool) preg_match(
        '/^[a-zA-Z0-9._-]{3,64}$/',
        $username
    );
}

/**
 * Nach POST auf die Benutzerseite zurückleiten.
 */
function chromophobic_users_redirect(
    string $message,
    string $type = 'success'
): void {
    $type = $type === 'error'
        ? 'error'
        : 'success';

    $url = yourls_admin_url(
        'plugins.php?page=chromophobic-users'
    );

    $url .= '&chromophobic_notice=' . rawurlencode($message);
    $url .= '&chromophobic_notice_type=' . rawurlencode($type);

    yourls_redirect($url, 303);
    exit;
}

/**
 * Rückmeldung nach einem Redirect ausgeben.
 */
function chromophobic_users_render_notice(): void
{
    $message = trim(
        (string) ($_GET['chromophobic_notice'] ?? '')
    );

    if ($message === '') {
        return;
    }

    $type = ($_GET['chromophobic_notice_type'] ?? '') === 'error'
        ? 'error'
        : 'success';

    echo '<div class="' . $type . '">';
    echo yourls_esc_html($message);
    echo '</div>';
}

/**
 * Wert sicher in einem HTML-Attribut ausgeben.
 */
function chromophobic_users_esc_attr(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}