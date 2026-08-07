(() => {
    'use strict';

    const qs = (selector, root = document) => root.querySelector(selector);
    const qsa = (selector, root = document) =>
        Array.from(root.querySelectorAll(selector));

    function el(tag, className = '', text = '') {
        const node = document.createElement(tag);

        if (className) {
            node.className = className;
        }

        if (text) {
            node.textContent = text;
        }

        return node;
    }

    function normaliseText(value) {
        return String(value || '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function escapeSelector(value) {
        if (window.CSS && typeof window.CSS.escape === 'function') {
            return window.CSS.escape(value);
        }

        return String(value).replace(/["\\]/g, '\\$&');
    }

    function readLegacyMeta() {
        const meta = {
            user: '',
            totalLinks: null,
            totalClicks: null
        };

        const displayPattern =
            /Display\s+\d+\s+to\s+\d+\s+of\s+(\d+)\s+URLs?\.?/i;

        const overallPattern =
            /Overall,\s*tracking\s+(\d+)\s+links?,\s*(\d+)\s+clicks?,\s*and\s+counting!?/i;

        const helloPattern =
            /^(?:Hello|Hallo)\s+([^(]+?)(?:\s*\(|$)/i;

        /*
         * YOURLS kann die Tracking-Texte über mehrere Text-/Strong-Knoten
         * verteilen. Deshalb lesen wir kurze Elemente ausserhalb von <main>
         * als Ganzes statt nur einzelne Textknoten.
         */
        qsa('body p, body div, body span').forEach((node) => {
            if (node.closest('main')) {
                return;
            }

            const text = normaliseText(node.textContent);

            if (!text || text.length > 180) {
                return;
            }

            let match = text.match(displayPattern);

            if (match) {
                meta.totalLinks = Number(match[1]);
                node.remove();
                return;
            }

            match = text.match(overallPattern);

            if (match) {
                meta.totalLinks = Number(match[1]);
                meta.totalClicks = Number(match[2]);
                node.remove();
                return;
            }

            match = text.match(helloPattern);

            if (match && !meta.user) {
                meta.user = normaliseText(match[1]);

                /*
                 * Den alten "Hello ..."-Text ebenfalls aus dem Legacy-
                 * Header entfernen. Logout/Nav werden separat neu aufgebaut.
                 */
                if (
                    text.length < 120
                    && !node.querySelector('#admin_menu')
                ) {
                    node.remove();
                }
            }
        });

        /*
         * Falls YOURLS die Zusammenfassung als nackte Textknoten ausgibt,
         * werden auch diese zuverlässig entfernt und ausgelesen.
         */
        const root = qs('#wrap') || document.body;
        const walker = document.createTreeWalker(
            root,
            NodeFilter.SHOW_TEXT
        );

        const textNodes = [];

        while (walker.nextNode()) {
            textNodes.push(walker.currentNode);
        }

        textNodes.forEach((node) => {
            const parent = node.parentElement;

            if (parent && parent.closest('main')) {
                return;
            }

            const text = normaliseText(node.nodeValue);

            if (!text || text.length > 180) {
                return;
            }

            let match = text.match(displayPattern);

            if (match) {
                meta.totalLinks = Number(match[1]);
                node.nodeValue = '';
                return;
            }

            match = text.match(overallPattern);

            if (match) {
                meta.totalLinks = Number(match[1]);
                meta.totalClicks = Number(match[2]);
                node.nodeValue = '';
                return;
            }

            match = text.match(helloPattern);

            if (match && !meta.user) {
                meta.user = normaliseText(match[1]);
            }
        });

        /*
         * Sicherer Fallback: Für die aktuelle Seite lassen sich Anzahl Links
         * und Klicks direkt aus der YOURLS-Tabelle berechnen. So bleiben die
         * Kennzahlen auch dann brauchbar, wenn sich der Legacy-Text ändert.
         */
        const table = qs('#main_table');

        if (table) {
            const rows = qsa('tbody tr', table).filter((row) => {
                return row.id !== 'nourl_found';
            });

            if (meta.totalLinks === null) {
                meta.totalLinks = rows.length;
            }

            if (meta.totalClicks === null) {
                meta.totalClicks = rows.reduce((sum, row) => {
                    const cell = qs('td.clicks', row);
                    const value = Number(
                        normaliseText(cell?.textContent)
                    );

                    return sum + (
                        Number.isFinite(value) ? value : 0
                    );
                }, 0);
            }
        }

        return meta;
    }

    function classifyNavLink(link) {
        const text = normaliseText(link.textContent).toLowerCase();
        const href = link.getAttribute('href') || '';

        let url;

        try {
            url = new URL(href, window.location.href);
        } catch (error) {
            return null;
        }

        const page = url.searchParams.get('page') || '';
        const path = url.pathname.toLowerCase();

        if (
            /logout|abmelden/.test(text)
            || url.searchParams.get('action') === 'logout'
            || /logout/.test(href)
        ) {
            return {
                key: 'logout',
                label: 'Abmelden',
                href: url.href
            };
        }

        if (
            page === 'chromophobic-users'
            || /benutzer|users?/.test(text)
        ) {
            return {
                key: 'users',
                label: 'Benutzer',
                href: url.href
            };
        }

        if (
            /plugins\.php$/.test(path)
            && !page
        ) {
            return {
                key: 'plugins',
                label: 'Plugins',
                href: url.href
            };
        }

        if (
            /tools\.php$/.test(path)
            || /werkzeuge|tools/.test(text)
        ) {
            return {
                key: 'tools',
                label: 'Werkzeuge',
                href: url.href
            };
        }

        if (
            /help/.test(path)
            || /hilfe|help/.test(text)
        ) {
            return {
                key: 'help',
                label: 'Hilfe',
                href: url.href
            };
        }

        if (
            /index\.php$/.test(path)
            || /admin interface/.test(text)
            || text === 'links'
        ) {
            return {
                key: 'links',
                label: 'Links',
                href: url.href
            };
        }

        return null;
    }

    function isCurrentNavItem(item) {
        const current = new URL(window.location.href);
        const target = new URL(item.href);

        if (item.key === 'links') {
            return (
                /\/admin\/(?:index\.php)?$/.test(current.pathname)
                && !current.searchParams.get('page')
            );
        }

        if (item.key === 'users') {
            return current.searchParams.get('page') === 'chromophobic-users';
        }

        return (
            current.pathname === target.pathname
            && (
                !target.searchParams.get('page')
                || current.searchParams.get('page') ===
                    target.searchParams.get('page')
            )
        );
    }

    function buildAppHeader(meta) {
        if (qs('.cp-app-header')) {
            return;
        }

        const legacyHeader = qs('header[role="banner"]');
        const legacyNav = qs('nav[role="navigation"]');

        const rawLinks = legacyNav
            ? qsa('a', legacyNav)
            : qsa('#admin_menu a');

        const unique = new Map();

        rawLinks.forEach((link) => {
            const item = classifyNavLink(link);

            if (item && !unique.has(item.key)) {
                unique.set(item.key, item);
            }
        });

        if (!unique.has('logout')) {
            qsa('a').some((link) => {
                const item = classifyNavLink(link);

                if (item?.key === 'logout') {
                    unique.set('logout', item);
                    return true;
                }

                return false;
            });
        }

        const desiredOrder = [
            'links',
            'users',
            'plugins',
            'tools',
            'help'
        ];

        const header = el('header', 'cp-app-header');
        const inner = el('div', 'cp-app-header-inner');

        const brand = document.createElement('a');
        brand.className = 'cp-app-brand';
        brand.href = unique.get('links')?.href || '/admin/';
        brand.setAttribute(
            'aria-label',
            'Chromophobic Links – Startseite'
        );

        const mark = el('span', 'cp-app-mark');
        mark.setAttribute('aria-hidden', 'true');

        const wordmark = el('span', 'cp-app-wordmark');
        wordmark.append(
            el('strong', '', 'chromophobic'),
            el('span', '', 'links')
        );

        brand.append(mark, wordmark);

        const nav = el('nav', 'cp-app-nav');
        nav.setAttribute('aria-label', 'Hauptnavigation');

        desiredOrder.forEach((key) => {
            const item = unique.get(key);

            if (!item) {
                return;
            }

            const link = document.createElement('a');
            link.href = item.href;
            link.textContent = item.label;

            if (isCurrentNavItem(item)) {
                link.classList.add('is-current');
                link.setAttribute('aria-current', 'page');
            }

            nav.append(link);
        });

        const account = el('div', 'cp-app-account');

        if (meta.user) {
            account.append(
                el('span', 'cp-app-user', meta.user)
            );
        }

        const logout = unique.get('logout');

        if (logout) {
            const logoutLink = document.createElement('a');
            logoutLink.className = 'cp-app-logout';
            logoutLink.href = logout.href;
            logoutLink.textContent = 'Abmelden';
            account.append(logoutLink);
        }

        inner.append(brand, nav, account);
        header.append(inner);

        const wrap = qs('#wrap') || document.body;
        wrap.prepend(header);

        if (legacyHeader) {
            legacyHeader.hidden = true;
        }

        if (legacyNav) {
            legacyNav.hidden = true;
        }
    }

    function iconLink() {
        const icon = el('span', 'cp-link-icon');
        icon.setAttribute('aria-hidden', 'true');

        return icon;
    }

    function buildPageHeader(meta) {
        const main = qs('main[role="main"]');

        if (
            !main
            || qs('.cp-dashboard-header', main)
            || (
                !qs('#new_url_form', main)
                && !qs('#main_table', main)
            )
        ) {
            return;
        }

        const header = el('section', 'cp-dashboard-header');

        const copy = el('div', 'cp-dashboard-copy');
        copy.append(
            el('p', 'cp-kicker', 'Link-Shortener'),
            el('h1', '', 'Kurzlinks'),
            el(
                'p',
                'cp-dashboard-lead',
                'Links und QR-Codes zentral erstellen und verwalten.'
            )
        );

        const stats = el('div', 'cp-dashboard-stats');

        const linkStat = el('div', 'cp-stat');
        linkStat.append(
            el(
                'strong',
                'cp-stat-value',
                meta.totalLinks === null
                    ? '–'
                    : String(meta.totalLinks)
            ),
            el('span', 'cp-stat-label', 'Links')
        );

        const clickStat = el('div', 'cp-stat');
        clickStat.append(
            el(
                'strong',
                'cp-stat-value',
                meta.totalClicks === null
                    ? '–'
                    : String(meta.totalClicks)
            ),
            el('span', 'cp-stat-label', 'Klicks gesamt')
        );

        stats.append(linkStat, clickStat);
        header.append(copy, stats);
        main.prepend(header);
    }

    function createField(
        labelText,
        input,
        helpText = '',
        className = ''
    ) {
        const label = el(
            'label',
            `cp-form-field ${className}`.trim()
        );

        const labelTextNode = el(
            'span',
            'cp-form-label',
            labelText
        );

        label.append(labelTextNode, input);

        if (helpText) {
            label.append(
                el('small', 'cp-form-help', helpText)
            );
        }

        return label;
    }

    function enhanceShortener() {
        const newUrl = qs('#new_url');
        const form = qs('#new_url_form');

        if (
            !newUrl
            || !form
            || form.dataset.cpV5 === '1'
        ) {
            return;
        }

        const urlInput = qs('#add-url', form);
        const keywordInput = qs('#add-keyword', form);
        const nonce = qs('#nonce-add', form);
        const button = qs('#add-button', form);

        if (!urlInput || !keywordInput || !nonce || !button) {
            return;
        }

        form.dataset.cpV5 = '1';

        urlInput.placeholder = 'https://example.com/zieladresse';
        keywordInput.placeholder = 'kampagne';

        button.value = 'Kurzlink erstellen';

        const card = el('section', 'cp-create-card');

        const heading = el('div', 'cp-card-heading');
        const headingCopy = el('div');
        headingCopy.append(
            el('p', 'cp-kicker', 'Neuer Link'),
            el('h2', '', 'Link kürzen'),
            el(
                'p',
                'cp-card-description',
                'Zieladresse einfügen und bei Bedarf einen eigenen Kurznamen festlegen.'
            )
        );

        heading.append(headingCopy, iconLink());

        const body = el('div', 'cp-create-body');

        const urlField = createField(
            'Zieladresse',
            urlInput,
            'Vollständige URL inklusive https://',
            'cp-url-field'
        );

        const aliasShell = el('div', 'cp-alias-control');
        const prefix = el(
            'span',
            'cp-alias-prefix',
            `${window.location.host}/`
        );

        aliasShell.append(prefix, keywordInput);

        const aliasField = el(
            'label',
            'cp-form-field cp-alias-field'
        );
        aliasField.append(
            el('span', 'cp-form-label', 'Kurzname'),
            aliasShell,
            el(
                'small',
                'cp-form-help',
                'Optional – leer lassen für einen automatisch erzeugten Namen.'
            )
        );

        const footer = el('div', 'cp-create-footer');
        const hint = el(
            'p',
            'cp-create-hint',
            'Der Kurzlink kann später geändert werden, ohne einen neuen QR-Code drucken zu müssen.'
        );

        button.classList.add('cp-primary-button');

        footer.append(hint, button);
        body.append(urlField, aliasField, footer);

        card.append(heading, body, nonce);
        form.replaceChildren(card);
        newUrl.classList.add('cp-new-url');

        const feedback = qs('#feedback');
        if (feedback) {
            feedback.classList.add('cp-feedback');
        }
    }

    function enhanceShareBoxes() {
        const shareboxes = qs('#shareboxes');

        if (!shareboxes || shareboxes.dataset.cpV5 === '1') {
            return;
        }

        shareboxes.dataset.cpV5 = '1';
        shareboxes.classList.add('cp-shareboxes');

        const copyHeading = qs('#copybox h2');
        if (copyHeading) {
            copyHeading.textContent = 'Kurzlink erstellt';
        }

        const shareHeading = qs('#sharebox h2');
        if (shareHeading) {
            shareHeading.textContent = 'Teilen';
        }
    }

    function translateSelectOptions(select, mapping) {
        if (!select) {
            return;
        }

        qsa('option', select).forEach((option) => {
            const text = normaliseText(option.textContent);

            if (mapping[text]) {
                option.textContent = mapping[text];
            }
        });
    }

    function buildFilterPanel(tableSection) {
        const filterForm = qs('#filter_form');

        if (
            !filterForm
            || filterForm.dataset.cpV5 === '1'
        ) {
            return null;
        }

        filterForm.dataset.cpV5 = '1';

        const form = qs('form', filterForm);
        const options = qs('#filter_options', filterForm);

        if (!form || !options) {
            return null;
        }

        const search = qs('[name="search"]', form);
        const searchIn = qs('[name="search_in"]', form);
        const sortBy = qs('[name="sort_by"]', form);
        const sortOrder = qs('[name="sort_order"]', form);
        const perPage = qs('[name="perpage"]', form);
        const clickFilter = qs('[name="click_filter"]', form);
        const clickLimit = qs('[name="click_limit"]', form);
        const dateFilter = qs('[name="date_filter"]', form);
        const dateFirst = qs('[name="date_first"]', form);
        const dateSecond = qs('[name="date_second"]', form);
        const submit = qs('#submit-sort', form);
        const clear = qs('#submit-clear-filter', form);

        if (search) {
            search.placeholder = 'Link oder Ziel suchen';
        }

        if (submit) {
            submit.value = 'Anwenden';
        }

        if (clear) {
            clear.value = 'Zurücksetzen';
        }

        translateSelectOptions(searchIn, {
            'All fields': 'Alle Felder',
            'Short URL': 'Kurzlink',
            'URL': 'Ziel-URL',
            'Title': 'Titel',
            'IP': 'IP-Adresse'
        });

        translateSelectOptions(sortBy, {
            'Short URL': 'Kurzlink',
            'URL': 'Ziel-URL',
            'Title': 'Titel',
            'Date': 'Datum',
            'IP': 'IP-Adresse',
            'Clicks': 'Klicks'
        });

        translateSelectOptions(sortOrder, {
            'Ascending': 'Aufsteigend',
            'Descending': 'Absteigend'
        });

        translateSelectOptions(clickFilter, {
            'more': 'mehr',
            'less': 'weniger'
        });

        translateSelectOptions(dateFilter, {
            'before': 'vor',
            'after': 'nach',
            'between': 'zwischen'
        });

        const panel = el('details', 'cp-filter');
        const summary = el('summary', 'cp-filter-trigger');
        summary.append(
            el('span', 'cp-filter-icon', '≡'),
            el('span', '', 'Filter')
        );

        const surface = el('div', 'cp-filter-surface');
        const grid = el('div', 'cp-filter-grid');

        function field(label, controls) {
            const item = el('label', 'cp-filter-field');
            item.append(
                el('span', 'cp-filter-label', label)
            );

            const control = el('div', 'cp-filter-controls');
            controls.filter(Boolean).forEach((node) => {
                control.append(node);
            });

            item.append(control);
            return item;
        }

        grid.append(
            field('Suchen', [search, searchIn]),
            field('Sortierung', [sortBy, sortOrder]),
            field('Links pro Seite', [perPage]),
            field('Klicks', [clickFilter, clickLimit]),
            field('Zeitraum', [dateFilter, dateFirst, dateSecond])
        );

        const actions = el('div', 'cp-filter-actions');
        if (clear) {
            actions.append(clear);
        }
        if (submit) {
            actions.append(submit);
        }

        options.replaceChildren(grid, actions);
        surface.append(filterForm);
        panel.append(summary, surface);

        const params = new URL(window.location.href).searchParams;

        if (
            params.get('search')
            || params.get('click_limit')
            || params.get('date_first')
        ) {
            panel.open = true;
        }

        return panel;
    }

    function formatDates(table) {
        qsa('td.timestamp', table).forEach((cell) => {
            if (cell.dataset.cpDateFormatted === '1') {
                return;
            }

            const stamp = qs('span.timestamp', cell);

            if (!stamp) {
                return;
            }

            const seconds = Number(normaliseText(stamp.textContent));

            if (!Number.isFinite(seconds) || seconds <= 0) {
                return;
            }

            const formatted = new Intl.DateTimeFormat(
                'de-CH',
                {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                }
            ).format(new Date(seconds * 1000));

            Array.from(cell.childNodes).forEach((node) => {
                if (node !== stamp) {
                    node.remove();
                }
            });

            stamp.hidden = true;
            cell.append(
                document.createTextNode(formatted)
            );
            cell.dataset.cpDateFormatted = '1';
        });
    }

    function translateTableHeaders(table) {
        const labels = [
            'Kurzlink',
            'Ziel',
            'Erstellt',
            'IP',
            'Klicks',
            ''
        ];

        qsa('thead th', table).forEach((th, index) => {
            const target = qs('span', th) || th;

            if (typeof labels[index] === 'string') {
                target.textContent = labels[index];
            }
        });
    }

    function enhanceRowActions(row) {
        const cell = qs('td.actions', row);

        if (!cell || qs('.cp-row-actions', cell)) {
            return;
        }

        const actions = qsa(
            'a.button_stats, a.button_share, a.button_edit, a.button_delete, a.button_qr',
            cell
        );

        if (!actions.length) {
            return;
        }

        const actionDefinitions = [
            {
                className: 'button_stats',
                key: 'stats',
                label: 'Statistik'
            },
            {
                className: 'button_share',
                key: 'share',
                label: 'Teilen'
            },
            {
                className: 'button_edit',
                key: 'edit',
                label: 'Bearbeiten'
            },
            {
                className: 'button_qr',
                key: 'qr',
                label: 'QR-Code herunterladen'
            },
            {
                className: 'button_delete',
                key: 'delete',
                label: 'Löschen'
            }
        ];

        const details = el('details', 'cp-row-actions');
        const summary = el(
            'summary',
            'cp-row-actions-trigger',
            '•••'
        );

        summary.setAttribute(
            'aria-label',
            'Aktionen öffnen'
        );
        summary.setAttribute('title', 'Aktionen');

        const menu = el('div', 'cp-row-actions-menu');
        menu.setAttribute('role', 'menu');

        actionDefinitions.forEach((definition) => {
            const action = actions.find((candidate) => {
                return candidate.classList.contains(
                    definition.className
                );
            });

            if (!action) {
                return;
            }

            /*
             * Wichtig: Wir verschieben den echten YOURLS-Link. Dadurch bleiben
             * IDs, href und onclick-Funktionen vollständig intakt.
             *
             * Die alten .button_* Klassen werden entfernt, weil YOURLS und das
             * QR-Plugin diese als kleine Icon-Buttons positionieren. Genau das
             * hat im Dropdown zum Überlappen der Funktionen geführt.
             */
            action.classList.remove(
                'button',
                'button_stats',
                'button_share',
                'button_edit',
                'button_delete',
                'button_qr'
            );

            action.classList.add(
                'cp-menu-action',
                `cp-menu-action-${definition.key}`
            );

            action.textContent = definition.label;
            action.title = definition.label;
            action.setAttribute('role', 'menuitem');

            action.addEventListener('click', () => {
                details.open = false;
            });

            menu.append(action);
        });

        const hiddenInputs = qsa(
            'input[type="hidden"]',
            cell
        );

        cell.replaceChildren();
        details.append(summary, menu);
        cell.append(details, ...hiddenInputs);
    }

    function enhanceEditRows(table) {
        qsa('tr.edit-row', table).forEach((row) => {
            if (
                row.dataset.cpV5 === '1'
                || row.classList.contains('notfound')
            ) {
                return;
            }

            const longInput = qs(
                'input[id^="edit-url-"]',
                row
            );
            const keywordInput = qs(
                'input[id^="edit-keyword-"]',
                row
            );
            const titleInput = qs(
                'input[id^="edit-title-"]',
                row
            );
            const save = qs(
                'input[id^="edit-submit-"]',
                row
            );
            const cancel = qs(
                'input[id^="edit-close-"]',
                row
            );

            if (
                !longInput
                || !keywordInput
                || !titleInput
                || !save
                || !cancel
            ) {
                return;
            }

            row.dataset.cpV5 = '1';
            row.classList.add('cp-edit-row');

            longInput.placeholder =
                'https://example.com/zieladresse';
            keywordInput.placeholder = 'kurzname';
            titleInput.placeholder =
                'Optionaler Titel des Links';

            save.value = 'Speichern';
            save.title = 'Änderungen speichern';
            save.classList.add('cp-primary-button');

            cancel.value = 'Abbrechen';
            cancel.title = 'Bearbeitung abbrechen';

            const hiddenInputs = qsa(
                'input[type="hidden"]',
                row
            );

            const cell = document.createElement('td');
            cell.colSpan = 6;
            cell.className = 'cp-inline-edit-cell';

            const editor = el(
                'div',
                'cp-inline-editor'
            );

            const header = el(
                'div',
                'cp-inline-editor-header'
            );

            const headerCopy = el('div');
            headerCopy.append(
                el('p', 'cp-kicker', 'Link bearbeiten'),
                el(
                    'h3',
                    '',
                    'Ziel und Kurzlink anpassen'
                ),
                el(
                    'p',
                    'cp-inline-editor-description',
                    'Änderungen wirken sofort nach dem Speichern.'
                )
            );

            header.append(headerCopy);

            const fields = el(
                'div',
                'cp-inline-editor-fields'
            );

            const longField = createField(
                'Zieladresse',
                longInput,
                'Vollständige URL inklusive https://',
                'cp-inline-long-field'
            );

            const aliasShell = el(
                'div',
                'cp-alias-control'
            );
            const aliasPrefix = el(
                'span',
                'cp-alias-prefix',
                `${window.location.origin}/`
            );
            aliasShell.append(
                aliasPrefix,
                keywordInput
            );

            const aliasField = el(
                'label',
                'cp-form-field cp-inline-alias-field'
            );
            aliasField.append(
                el(
                    'span',
                    'cp-form-label',
                    'Kurzname'
                ),
                aliasShell,
                el(
                    'small',
                    'cp-form-help',
                    'Nur den Teil nach der Domain eingeben.'
                )
            );

            const titleField = createField(
                'Titel',
                titleInput,
                'Optional – hilft bei der Übersicht.',
                'cp-inline-title-field'
            );

            fields.append(
                longField,
                aliasField,
                titleField
            );

            const footer = el(
                'div',
                'cp-inline-editor-footer'
            );

            const hint = el(
                'p',
                'cp-inline-editor-hint',
                'Wenn du den Kurznamen änderst, ändert sich auch die öffentliche Short-URL.'
            );

            const actions = el(
                'div',
                'cp-inline-editor-actions'
            );
            actions.append(cancel, save);

            footer.append(hint, actions);

            editor.append(
                header,
                fields,
                footer,
                ...hiddenInputs
            );

            cell.append(editor);
            row.replaceChildren(cell);
        });
    }

    function enhanceLinkRows(table) {
        enhanceEditRows(table);

        qsa('tbody tr:not(.edit-row)', table).forEach((row) => {
            enhanceRowActions(row);

            const keywordCell = qs('td.keyword', row);
            const keywordLink = keywordCell
                ? qs('a', keywordCell)
                : null;

            if (
                keywordCell
                && keywordLink
                && !qs('.cp-short-host', keywordCell)
            ) {
                const short = keywordLink.textContent.trim();

                keywordCell.append(
                    el(
                        'small',
                        'cp-short-host',
                        `${window.location.host}/${short}`
                    )
                );
            }
        });
    }

    function buildLinksSection() {
        const main = qs('main[role="main"]');
        const table = qs('#main_table');

        if (
            !main
            || !table
            || table.dataset.cpV5 === '1'
        ) {
            return;
        }

        table.dataset.cpV5 = '1';

        translateTableHeaders(table);
        formatDates(table);
        enhanceLinkRows(table);

        const tfoot = qs('tfoot', table);
        const pagination = qs('#pagination');
        const filterPanel = buildFilterPanel();

        if (tfoot) {
            tfoot.hidden = true;
        }

        const previousWrapper = table.parentElement;

        const section = el('section', 'cp-links-section');

        const header = el('div', 'cp-section-header');
        const copy = el('div');
        copy.append(
            el('p', 'cp-kicker', 'Übersicht'),
            el('h2', '', 'Gespeicherte Links')
        );

        const tools = el('div', 'cp-section-tools');
        if (filterPanel) {
            tools.append(filterPanel);
        }

        header.append(copy, tools);

        const tableShell = el('div', 'cp-table-shell');
        tableShell.append(table);

        section.append(header, tableShell);

        if (pagination) {
            pagination.classList.add('cp-pagination');
            section.append(pagination);
        }

        if (
            previousWrapper
            && previousWrapper !== main
            && previousWrapper.childElementCount === 0
        ) {
            previousWrapper.remove();
        }

        main.append(section);

        const tbody = qs('tbody', table);

        if (tbody && !tbody.dataset.cpV5Observer) {
            tbody.dataset.cpV5Observer = '1';

            const observer = new MutationObserver(() => {
                enhanceEditRows(table);
                formatDates(table);
                enhanceLinkRows(table);
                updateDashboardStats();
            });

            observer.observe(tbody, {
                childList: true,
                subtree: true
            });
        }
    }

    function updateDashboardStats() {
        const table = qs('#main_table');

        if (!table) {
            return;
        }

        const rows = qsa('tbody tr', table).filter((row) => {
            return row.id !== 'nourl_found';
        });

        const clicks = rows.reduce((sum, row) => {
            const value = Number(
                normaliseText(
                    qs('td.clicks', row)?.textContent
                )
            );

            return sum + (
                Number.isFinite(value) ? value : 0
            );
        }, 0);

        const values = qsa('.cp-stat-value');

        /*
         * Diese Werte sind der sichere Fallback für die aktuell dargestellten
         * Links. Beim initialen Laden bevorzugt readLegacyMeta weiterhin die
         * vollständigen YOURLS-Gesamtwerte, sofern verfügbar.
         */
        if (values.length >= 2) {
            if (values[0].textContent.trim() === '–') {
                values[0].textContent = String(rows.length);
            }

            if (values[1].textContent.trim() === '–') {
                values[1].textContent = String(clicks);
            }
        }
    }

    function enhanceUsersPage() {
        const main = qs('main[role="main"]');
        const page = qs('.cp-users-page');

        if (!main || !page || page.dataset.cpV5 === '1') {
            return;
        }

        page.dataset.cpV5 = '1';

        const heading = qs('.cp-users-heading');
        if (heading) {
            const title = qs('h2', heading);
            if (title) {
                title.textContent = 'Benutzer';
            }

            heading.classList.add('cp-users-page-heading');
        }

        const table = qs('.cp-users-list table');

        if (table) {
            const shell = el('div', 'cp-table-shell cp-users-table-shell');
            table.before(shell);
            shell.append(table);

            qsa('tbody tr', table).forEach((row) => {
                const cells = qsa('td', row);

                if (cells[3]) {
                    const text = normaliseText(cells[3].textContent);
                    cells[3].replaceChildren(
                        el(
                            'span',
                            text === 'Administrator'
                                ? 'cp-badge cp-badge-admin'
                                : 'cp-badge',
                            text
                        )
                    );
                }

                if (cells[4]) {
                    const text = normaliseText(cells[4].textContent);
                    cells[4].replaceChildren(
                        el(
                            'span',
                            text === 'Aktiv'
                                ? 'cp-badge cp-badge-success'
                                : 'cp-badge cp-badge-muted',
                            text
                        )
                    );
                }
            });
        }

        qsa('details.cp-users-edit').forEach((details, index) => {
            const form = qs('form', details);

            if (!form) {
                return;
            }

            const trigger = el(
                'button',
                'button cp-user-edit-trigger',
                'Bearbeiten'
            );
            trigger.type = 'button';

            const dialog = el(
                'dialog',
                'cp-dialog cp-user-dialog'
            );

            const header = el('div', 'cp-dialog-header');
            const copy = el('div');
            copy.append(
                el('p', 'cp-kicker', 'Benutzer'),
                el('h2', '', 'Zugang bearbeiten')
            );

            const close = el(
                'button',
                'cp-dialog-close',
                '×'
            );
            close.type = 'button';
            close.setAttribute(
                'aria-label',
                'Dialog schliessen'
            );

            header.append(copy, close);

            const body = el('div', 'cp-dialog-body');
            body.append(form);

            dialog.append(header, body);
            document.body.append(dialog);

            details.replaceWith(trigger);

            trigger.addEventListener(
                'click',
                () => dialog.showModal()
            );

            close.addEventListener(
                'click',
                () => dialog.close()
            );
        });

        qsa('.cp-users-actions').forEach((actions) => {
            if (actions.dataset.cpV5 === '1') {
                return;
            }

            actions.dataset.cpV5 = '1';

            const details = el(
                'details',
                'cp-user-actions-menu'
            );
            const summary = el(
                'summary',
                'cp-row-actions-trigger',
                '•••'
            );
            summary.setAttribute(
                'aria-label',
                'Benutzeraktionen öffnen'
            );

            const menu = el(
                'div',
                'cp-user-actions-surface'
            );

            Array.from(actions.children).forEach((child) => {
                menu.append(child);
            });

            details.append(summary, menu);
            actions.append(details);
        });
    }

    function enhanceQrDialog() {
        const dialog = qs('#chromophobic-qr-dialog');

        if (!dialog) {
            return;
        }

        dialog.classList.add('cp-dialog', 'cp-qr-dialog');
    }

    function enhanceLogin() {
        const login = qs('#login');

        if (!login) {
            return false;
        }

        document.body.classList.add('cp-login-page');

        const form = qs('form', login);

        if (form && !qs('.cp-login-heading', form)) {
            const heading = el('div', 'cp-login-heading');
            heading.append(
                el('p', 'cp-kicker', 'Chromophobic Links'),
                el('h1', '', 'Anmelden'),
                el(
                    'p',
                    'cp-card-description',
                    'Kurzlinks und QR-Codes verwalten.'
                )
            );
            form.prepend(heading);
        }

        const username = qs('#username', login);
        const password = qs('#password', login);
        const submit = qs('#submit', login);

        if (username) {
            username.placeholder = 'Benutzername';
            username.autocomplete = 'username';
        }

        if (password) {
            password.placeholder = 'Passwort';
            password.autocomplete = 'current-password';
        }

        if (submit) {
            submit.value = 'Anmelden';
        }

        return true;
    }

    function closeMenusOnOutsideClick() {
        document.addEventListener('click', (event) => {
            qsa(
                '.cp-row-actions[open], .cp-user-actions-menu[open], .cp-filter[open]'
            ).forEach((details) => {
                if (!details.contains(event.target)) {
                    details.open = false;
                }
            });
        });
    }

    function init() {
        document.body.classList.add('cp-admin-v5');

        const meta = readLegacyMeta();

        if (enhanceLogin()) {
            return;
        }

        buildAppHeader(meta);
        buildPageHeader(meta);
        enhanceShortener();
        enhanceShareBoxes();
        buildLinksSection();
        updateDashboardStats();
        enhanceUsersPage();
        enhanceQrDialog();
        closeMenusOnOutsideClick();
    }

    if (document.readyState === 'loading') {
        document.addEventListener(
            'DOMContentLoaded',
            init,
            { once: true }
        );
    } else {
        init();
    }
})();
