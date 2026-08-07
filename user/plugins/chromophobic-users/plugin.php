<?php

/*
Plugin Name: Chromophobic Users
Plugin URI: https://github.com/gian-source/chromophobic-yourls
Description: Datenbankbasierte Benutzerverwaltung für Chromophobic YOURLS.
Version: 0.3.0
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


// Datenbank-Benutzer in die bestehende YOURLS-Authentifizierung integrieren.
yourls_add_filter(
    'shunt_is_valid_user',
    'chromophobic_users_authenticate',
    10,
    1
);

yourls_add_filter(
    'set_cookie_value',
    'chromophobic_users_filter_cookie_value',
    10,
    2
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
    if (!chromophobic_users_current_user_is_admin()) {
        unset($links['tools'], $links['plugins']);
        return $links;
    }

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
    if (!chromophobic_users_current_user_is_admin()) {
        yourls_die(
            'Du hast keine Berechtigung für die Benutzerverwaltung.',
            'Zugriff verweigert',
            403
        );
    }

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

    if (chromophobic_users_is_config_username($username)) {
        chromophobic_users_redirect(
            'Dieser Benutzername ist für den Notfall-Administrator reserviert.',
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

    if (chromophobic_users_is_config_username($username)) {
        chromophobic_users_redirect(
            'Dieser Benutzername ist für den Notfall-Administrator reserviert.',
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

/**
 * Datenbank-Benutzer in die YOURLS-Anmeldung integrieren.
 *
 * Konfigurations-Benutzer bleiben als Notfallzugang vollständig erhalten.
 *
 * @param mixed $pre Shunt-Standardwert von YOURLS.
 * @return mixed
 */
function chromophobic_users_authenticate($pre)
{
    if (yourls_shunt_default() !== $pre) {
        return $pre;
    }

    // Logout weiterhin vollständig von YOURLS behandeln lassen.
    if (
        isset($_GET['action'])
        && $_GET['action'] === 'logout'
    ) {
        return $pre;
    }

    // Anmeldung mit Benutzername und Passwort.
    if (
        isset($_REQUEST['username'], $_REQUEST['password'])
        && $_REQUEST['username'] !== ''
        && $_REQUEST['password'] !== ''
    ) {
        $username = trim((string) $_REQUEST['username']);
        $password = (string) $_REQUEST['password'];

        // Bestehende config.php-Benutzer immer YOURLS selbst prüfen lassen.
        if (chromophobic_users_is_config_username($username)) {
            return $pre;
        }

        $user = chromophobic_users_get_auth_user($username);

        // Kein DB-Benutzer: YOURLS darf weiterhin seine Standardlogik versuchen.
        if (!$user) {
            return $pre;
        }

        if (!yourls_is_API()) {
            yourls_verify_nonce('admin_login');
        }

        if (
            $user->status !== 'active'
            || !password_verify($password, $user->password_hash)
        ) {
            return false;
        }

        yourls_set_user($user->username);
        chromophobic_users_update_last_login((int) $user->id);
        chromophobic_users_maybe_rehash_password($user, $password);

        return true;
    }

    // Normale Admin-Aufrufe über das Auth-Cookie.
    if (
        !yourls_is_API()
        && isset($_COOKIE[yourls_cookie_name()])
    ) {
        $cookie = (string) $_COOKIE[yourls_cookie_name()];
        $user = chromophobic_users_find_user_by_cookie($cookie);

        if ($user) {
            yourls_set_user($user->username);

            if (chromophobic_users_request_is_restricted($user)) {
                yourls_die(
                    'Du hast keine Berechtigung für diesen Bereich.',
                    'Zugriff verweigert',
                    403
                );
            }

            return true;
        }
    }

    return $pre;
}

/**
 * Für DB-Benutzer ein Cookie erzeugen, das auch vom Passwort-Hash abhängt.
 * Dadurch werden bestehende Sitzungen nach einer Passwortänderung ungültig.
 *
 * @param string $value YOURLS-Standardwert.
 * @param string $username Benutzername.
 * @return string
 */
function chromophobic_users_filter_cookie_value(
    string $value,
    string $username
): string {
    if ($username === '' || chromophobic_users_is_config_username($username)) {
        return $value;
    }

    $user = chromophobic_users_get_auth_user($username);

    if (!$user) {
        return $value;
    }

    return chromophobic_users_db_cookie_value($user);
}

/**
 * Benutzer inklusive Authentifizierungsdaten laden.
 */
function chromophobic_users_get_auth_user(string $username): ?object
{
    $table = CHROMOPHOBIC_USERS_TABLE;

    $user = yourls_get_db(
        'chromophobic-users-auth-get'
    )->fetchObject(
        "
        SELECT
            `id`,
            `username`,
            `display_name`,
            `email`,
            `password_hash`,
            `role`,
            `status`,
            `last_login_at`
        FROM `$table`
        WHERE `username` = :username
        LIMIT 1
        ",
        [
            'username' => $username,
        ]
    );

    return $user ?: null;
}

/**
 * Aktive DB-Benutzer anhand ihres Cookies finden.
 */
function chromophobic_users_find_user_by_cookie(string $cookie): ?object
{
    $table = CHROMOPHOBIC_USERS_TABLE;

    $users = yourls_get_db(
        'chromophobic-users-auth-cookie-list'
    )->fetchObjects(
        "
        SELECT
            `id`,
            `username`,
            `display_name`,
            `email`,
            `password_hash`,
            `role`,
            `status`,
            `last_login_at`
        FROM `$table`
        WHERE `status` = 'active'
        "
    );

    foreach ($users as $user) {
        if (hash_equals(
            chromophobic_users_db_cookie_value($user),
            $cookie
        )) {
            return $user;
        }
    }

    return null;
}

/**
 * Cookie-Wert für einen DB-Benutzer berechnen.
 */
function chromophobic_users_db_cookie_value(object $user): string
{
    return yourls_salt(
        'chromophobic-db-user|'
        . $user->username
        . '|'
        . $user->password_hash
    );
}

/**
 * Zeitpunkt der letzten erfolgreichen Passwort-Anmeldung speichern.
 */
function chromophobic_users_update_last_login(int $user_id): void
{
    $table = CHROMOPHOBIC_USERS_TABLE;

    yourls_get_db(
        'chromophobic-users-auth-last-login'
    )->fetchAffected(
        "
        UPDATE `$table`
        SET `last_login_at` = :last_login_at
        WHERE `id` = :id
        ",
        [
            'last_login_at' => date('Y-m-d H:i:s'),
            'id' => $user_id,
        ]
    );
}

/**
 * Passwort-Hash bei Bedarf auf den aktuellen PHP-Standard aktualisieren.
 */
function chromophobic_users_maybe_rehash_password(
    object $user,
    string $password
): void {
    if (!password_needs_rehash($user->password_hash, PASSWORD_DEFAULT)) {
        return;
    }

    $table = CHROMOPHOBIC_USERS_TABLE;

    yourls_get_db(
        'chromophobic-users-auth-rehash'
    )->fetchAffected(
        "
        UPDATE `$table`
        SET
            `password_hash` = :password_hash,
            `updated_at` = :updated_at
        WHERE `id` = :id
        ",
        [
            'password_hash' => password_hash(
                $password,
                PASSWORD_DEFAULT
            ),
            'updated_at' => date('Y-m-d H:i:s'),
            'id' => (int) $user->id,
        ]
    );
}

/**
 * Prüfen, ob ein Benutzername bereits in config.php existiert.
 */
function chromophobic_users_is_config_username(string $username): bool
{
    global $yourls_user_passwords;

    return isset($yourls_user_passwords[$username]);
}

/**
 * Aktuell angemeldeten DB-Benutzer laden.
 * config.php-Benutzer liefern bewusst null und gelten als Notfall-Admins.
 */
function chromophobic_users_current_db_user(): ?object
{
    if (!defined('YOURLS_USER')) {
        return null;
    }

    if (chromophobic_users_is_config_username(YOURLS_USER)) {
        return null;
    }

    return chromophobic_users_get_auth_user(YOURLS_USER);
}

/**
 * Prüfen, ob der aktuelle Benutzer administrative Rechte hat.
 */
function chromophobic_users_current_user_is_admin(): bool
{
    if (!defined('YOURLS_USER')) {
        return false;
    }

    // Bestehende YOURLS-config.php-Zugänge sind unser Notfall-Admin.
    if (chromophobic_users_is_config_username(YOURLS_USER)) {
        return true;
    }

    $user = chromophobic_users_current_db_user();

    return $user
        && $user->status === 'active'
        && $user->role === 'admin';
}

/**
 * Sensible YOURLS-Bereiche für normale DB-Benutzer sperren.
 */
function chromophobic_users_request_is_restricted(object $user): bool
{
    if ($user->role === 'admin') {
        return false;
    }

    $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));

    return in_array(
        $script,
        [
            'plugins.php',
            'tools.php',
            'upgrade.php',
        ],
        true
    );
}