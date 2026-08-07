(() => {
    'use strict';

    const qs = (selector, root = document) => root.querySelector(selector);
    const qsa = (selector, root = document) =>
        Array.from(root.querySelectorAll(selector));

    function element(tag, className, text) {
        const node = document.createElement(tag);

        if (className) {
            node.className = className;
        }

        if (typeof text === 'string') {
            node.textContent = text;
        }

        return node;
    }

    function currentPath() {
        return window.location.pathname + window.location.search;
    }

    function initBody() {
        document.body.classList.add('cp-admin-v4');

        if (qs('#login')) {
            document.body.classList.add('cp-login-page');
        }

        if (qs('#main_table')) {
            document.body.classList.add('cp-links-page');
        }

        if (qs('.cp-users-page')) {
            document.body.classList.add('cp-users-admin-page');
        }
    }

    function enhanceBrand() {
        const header = qs('header[role="banner"]');

        if (!header || header.dataset.cpEnhanced === '1') {
            return;
        }

        header.dataset.cpEnhanced = '1';

        const originalHeading = qs('h1', header);
        const originalLink = originalHeading
            ? qs('a', originalHeading)
            : null;

        if (originalHeading) {
            originalHeading.classList.add('cp-original-brand');
        }

        const brand = document.createElement('a');
        brand.className = 'cp-brand';
        brand.href = originalLink
            ? originalLink.href
            : '/admin/';
        brand.setAttribute('aria-label', 'Chromophobic Links – Startseite');

        const mark = element('span', 'cp-brand-mark');
        mark.setAttribute('aria-hidden', 'true');

        const wordmark = element('span', 'cp-brand-wordmark');
        const name = element('strong', '', 'chromophobic');
        const product = element('span', '', 'Links');

        wordmark.append(name, product);
        brand.append(mark, wordmark);
        header.append(brand);
    }

    function enhanceNavigation() {
        const nav = qs('nav[role="navigation"]');

        if (!nav || nav.dataset.cpEnhanced === '1') {
            return;
        }

        nav.dataset.cpEnhanced = '1';
        nav.setAttribute('aria-label', 'Hauptnavigation');

        const translations = new Map([
            ['Admin interface', 'Links'],
            ['Admin Interface', 'Links'],
            ['Tools', 'Werkzeuge'],
            ['Manage Plugins', 'Plugins'],
            ['Plugins', 'Plugins'],
            ['Help', 'Hilfe'],
            ['Logout', 'Abmelden'],
            ['Benutzer', 'Benutzer']
        ]);

        qsa('a', nav).forEach((link) => {
            const original = link.textContent.trim();

            if (translations.has(original)) {
                link.textContent = translations.get(original);
            }

            const href = link.getAttribute('href') || '';

            if (!href || href === '#') {
                return;
            }

            let isActive = false;

            try {
                const target = new URL(href, window.location.href);

                if (
                    target.pathname.endsWith('/admin/index.php')
                    && window.location.pathname.endsWith('/admin/index.php')
                ) {
                    isActive = true;
                } else if (
                    target.pathname === window.location.pathname
                    && target.searchParams.get('page') ===
                        new URL(window.location.href).searchParams.get('page')
                ) {
                    isActive = true;
                }
            } catch (error) {
                // Ein nicht standardmässiger Link bleibt einfach ohne Aktivstatus.
            }

            if (isActive) {
                link.classList.add('cp-nav-current');
                link.setAttribute('aria-current', 'page');
            }
        });
    }

    function createField(labelText, input, className = '') {
        const label = element(
            'label',
            `cp-field ${className}`.trim()
        );
        const title = element('span', 'cp-field-label', labelText);

        label.append(title, input);

        return label;
    }

    function enhanceShortenerForm() {
        const form = qs('#new_url_form');

        if (!form || form.dataset.cpEnhanced === '1') {
            return;
        }

        const urlInput = qs('#add-url');
        const keywordInput = qs('#add-keyword');
        const nonce = qs('#nonce-add');
        const button = qs('#add-button');
        const feedback = qs('#feedback');

        if (!urlInput || !keywordInput || !nonce || !button) {
            return;
        }

        form.dataset.cpEnhanced = '1';
        form.classList.add('cp-shortener-form');

        urlInput.placeholder = 'https://example.com/sehr-lange-url';
        urlInput.setAttribute('aria-describedby', 'cp-url-help');

        keywordInput.placeholder = 'z. B. kampagne';
        keywordInput.setAttribute('aria-describedby', 'cp-keyword-help');

        button.value = 'Kurzlink erstellen';
        button.classList.add('cp-primary-action');

        const pageIntro = element('section', 'cp-page-intro');
        const introCopy = element('div', 'cp-page-intro-copy');
        introCopy.append(
            element('p', 'cp-eyebrow', 'Link-Shortener'),
            element('h1', '', 'Links einfach verwalten'),
            element(
                'p',
                'cp-lead',
                'Erstelle kurze Links, behalte bestehende Ziele im Blick und lade QR-Codes direkt herunter.'
            )
        );
        pageIntro.append(introCopy);

        const newUrl = qs('#new_url');
        if (newUrl && !qs('.cp-page-intro')) {
            newUrl.parentNode.insertBefore(pageIntro, newUrl);
        }

        const panel = element('div', 'cp-shortener-panel');
        const header = element('div', 'cp-shortener-header');
        const headerText = element('div');

        headerText.append(
            element('p', 'cp-eyebrow', 'Neuer Kurzlink'),
            element('h2', '', 'Link kürzen'),
            element(
                'p',
                'cp-card-copy',
                'Füge die Zieladresse ein. Einen eigenen Kurzname kannst du optional vergeben.'
            )
        );

        const icon = element('span', 'cp-shortener-icon');
        icon.setAttribute('aria-hidden', 'true');

        header.append(headerText, icon);

        const fields = element('div', 'cp-shortener-fields');

        const urlField = createField('Lange URL', urlInput, 'cp-field-url');
        urlField.append(
            element(
                'small',
                'cp-field-help',
                'Die vollständige Zieladresse inklusive https://'
            )
        );
        qs('small', urlField).id = 'cp-url-help';

        const aliasShell = element('div', 'cp-alias-input');
        const prefix = element(
            'span',
            'cp-alias-prefix',
            `${window.location.host}/`
        );
        aliasShell.append(prefix, keywordInput);

        const aliasField = element('label', 'cp-field cp-field-keyword');
        aliasField.append(
            element('span', 'cp-field-label', 'Eigener Kurzname'),
            aliasShell,
            element(
                'small',
                'cp-field-help',
                'Optional – leer lassen für einen automatisch erzeugten Kurzlink.'
            )
        );
        qs('small', aliasField).id = 'cp-keyword-help';

        const action = element('div', 'cp-shortener-action');
        action.append(button);

        fields.append(urlField, aliasField, action);
        panel.append(header, fields, nonce);

        form.replaceChildren(panel);

        if (feedback) {
            feedback.classList.add('cp-feedback');
            form.after(feedback);
        }

        const host = newUrl ? qs(':scope > div', newUrl) : null;
        if (host) {
            host.classList.add('cp-shortener-host');
        }

        urlInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                button.click();
            }
        });

        keywordInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                button.click();
            }
        });
    }

    function enhanceShareBoxes() {
        const boxes = qs('#shareboxes');

        if (!boxes || boxes.dataset.cpEnhanced === '1') {
            return;
        }

        boxes.dataset.cpEnhanced = '1';
        boxes.classList.add('cp-share-result');

        const copyHeading = qs('#copybox h2');
        const shareHeading = qs('#sharebox h2');

        if (copyHeading) {
            copyHeading.textContent = 'Dein Kurzlink';
        }

        if (shareHeading) {
            shareHeading.textContent = 'Teilen';
        }

        const copyLink = qs('#copylink');
        if (copyLink) {
            copyLink.setAttribute('aria-label', 'Erstellter Kurzlink');
        }
    }

    function makeFilterField(label, nodes) {
        const field = element('label', 'cp-filter-field');
        field.append(element('span', 'cp-filter-label', label));

        const line = element('span', 'cp-filter-control');

        nodes.filter(Boolean).forEach((node) => {
            line.append(node);
        });

        field.append(line);

        return field;
    }

    function enhanceFilterForm(tableWrapper) {
        const filterForm = qs('#filter_form');

        if (
            !filterForm
            || filterForm.dataset.cpEnhanced === '1'
            || !tableWrapper
        ) {
            return;
        }

        filterForm.dataset.cpEnhanced = '1';

        const form = qs('form', filterForm);
        const options = qs('#filter_options', filterForm);

        if (!form || !options) {
            return;
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
            search.placeholder = 'Link, Titel, URL oder IP';
        }

        if (submit) {
            submit.value = 'Filter anwenden';
        }

        if (clear) {
            clear.value = 'Zurücksetzen';
        }

        const grid = element('div', 'cp-filter-grid');

        grid.append(
            makeFilterField('Suchen', [search, searchIn]),
            makeFilterField('Sortieren', [sortBy, sortOrder]),
            makeFilterField('Links pro Seite', [perPage]),
            makeFilterField('Klicks', [clickFilter, clickLimit]),
            makeFilterField('Zeitraum', [dateFilter, dateFirst, dateSecond])
        );

        const buttons = element('div', 'cp-filter-actions');
        if (submit) {
            buttons.append(submit);
        }
        if (clear) {
            buttons.append(clear);
        }

        options.replaceChildren(grid, buttons);

        const details = element('details', 'cp-filter-panel');
        const summary = element('summary', '', 'Filter und Sortierung');
        details.append(summary, filterForm);

        const params = new URL(window.location.href).searchParams;
        if (
            params.get('search')
            || params.get('click_limit')
            || params.get('date_first')
        ) {
            details.open = true;
        }

        const toolbar = element('div', 'cp-links-toolbar');
        const heading = element('div', 'cp-links-heading');
        heading.append(
            element('p', 'cp-eyebrow', 'Übersicht'),
            element('h2', '', 'Deine Links')
        );

        toolbar.append(heading, details);
        tableWrapper.before(toolbar);

        const pagination = qs('#pagination');
        if (pagination) {
            pagination.classList.add('cp-pagination');
            tableWrapper.after(pagination);
        }
    }

    function translateLinkRowActions(root = document) {
        const labels = {
            button_stats: 'Statistik',
            button_share: 'Teilen',
            button_edit: 'Bearbeiten',
            button_delete: 'Löschen',
            button_qr: 'QR-Code'
        };

        Object.entries(labels).forEach(([className, label]) => {
            qsa(`.${className}`, root).forEach((link) => {
                link.textContent = label;
                link.setAttribute('aria-label', label);
                link.setAttribute('title', label);
            });
        });
    }

    function enhanceMainTable() {
        const table = qs('#main_table');

        if (!table || table.dataset.cpEnhanced === '1') {
            return;
        }

        table.dataset.cpEnhanced = '1';

        const translations = [
            'Kurzlink',
            'Ziel',
            'Erstellt',
            'IP',
            'Klicks',
            'Aktionen'
        ];

        qsa('thead th', table).forEach((th, index) => {
            const span = qs('span', th);
            const text = translations[index];

            if (text) {
                if (span) {
                    span.textContent = text;
                } else {
                    th.textContent = text;
                }
            }
        });

        const wrapper = table.closest('.cp-table-scroll') || table.parentElement;
        if (wrapper) {
            wrapper.classList.add('cp-links-table-shell');
        }

        enhanceFilterForm(wrapper);
        translateLinkRowActions(table);
        addResponsiveLabels(table);

        const tbody = qs('tbody', table);

        if (tbody && !tbody.dataset.cpObserved) {
            tbody.dataset.cpObserved = '1';

            const observer = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    mutation.addedNodes.forEach((node) => {
                        if (node.nodeType !== Node.ELEMENT_NODE) {
                            return;
                        }

                        translateLinkRowActions(node);
                    });
                });

                addResponsiveLabels(table);
            });

            observer.observe(tbody, {
                childList: true,
                subtree: true
            });
        }
    }

    function addResponsiveLabels(table) {
        const headers = qsa('thead th', table).map((th) =>
            th.textContent.trim()
        );

        qsa('tbody tr', table).forEach((row) => {
            qsa('td', row).forEach((cell, index) => {
                if (headers[index]) {
                    cell.dataset.label = headers[index];
                }
            });
        });
    }

    function enhanceUserTable() {
        const table = qs('.cp-users-list table');

        if (!table || table.dataset.cpEnhanced === '1') {
            return;
        }

        table.dataset.cpEnhanced = '1';

        if (!table.closest('.cp-table-scroll')) {
            const shell = element('div', 'cp-table-scroll cp-users-table-shell');
            table.before(shell);
            shell.append(table);
        }

        addResponsiveLabels(table);

        qsa('tbody tr', table).forEach((row) => {
            const cells = qsa('td', row);

            const role = cells[3];
            const status = cells[4];

            if (role && !qs('.cp-badge', role)) {
                const text = role.textContent.trim();
                role.replaceChildren(
                    element(
                        'span',
                        text === 'Administrator'
                            ? 'cp-badge cp-badge-admin'
                            : 'cp-badge',
                        text
                    )
                );
            }

            if (status && !qs('.cp-badge', status)) {
                const text = status.textContent.trim();
                status.replaceChildren(
                    element(
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

    function enhanceUserEditDialogs() {
        qsa('details.cp-users-edit').forEach((details, index) => {
            if (details.dataset.cpEnhanced === '1') {
                return;
            }

            details.dataset.cpEnhanced = '1';

            const summary = qs('summary', details);
            const form = qs('form', details);

            if (!summary || !form) {
                return;
            }

            const trigger = element('button', 'button cp-user-edit-trigger', 'Bearbeiten');
            trigger.type = 'button';

            const dialog = element('dialog', 'cp-modal cp-user-edit-modal');
            dialog.id = `cp-user-edit-${index + 1}`;

            const header = element('div', 'cp-modal-header');
            const titleWrap = element('div');
            titleWrap.append(
                element('p', 'cp-eyebrow', 'Benutzerverwaltung'),
                element('h2', '', 'Benutzer bearbeiten')
            );

            const close = element('button', 'cp-modal-close', '×');
            close.type = 'button';
            close.setAttribute('aria-label', 'Fenster schliessen');

            header.append(titleWrap, close);

            const body = element('div', 'cp-modal-body');
            body.append(form);

            dialog.append(header, body);

            details.replaceWith(trigger);
            document.body.append(dialog);

            trigger.addEventListener('click', () => dialog.showModal());
            close.addEventListener('click', () => dialog.close());

            dialog.addEventListener('click', (event) => {
                const rect = dialog.getBoundingClientRect();

                if (
                    event.clientX < rect.left
                    || event.clientX > rect.right
                    || event.clientY < rect.top
                    || event.clientY > rect.bottom
                ) {
                    dialog.close();
                }
            });
        });
    }

    function enhanceUsersPage() {
        const page = qs('.cp-users-page');

        if (!page || page.dataset.cpEnhanced === '1') {
            return;
        }

        page.dataset.cpEnhanced = '1';

        const heading = qs('.cp-users-heading h2');
        if (heading) {
            heading.textContent = 'Benutzer verwalten';
        }

        enhanceUserTable();
        enhanceUserEditDialogs();
    }

    function enhanceQrDialog() {
        const dialog = qs('#chromophobic-qr-dialog');

        if (!dialog || dialog.dataset.cpEnhanced === '1') {
            return;
        }

        dialog.dataset.cpEnhanced = '1';
        dialog.classList.add('cp-modal', 'cp-qr-modal');

        const heading = qs('h2', dialog);
        if (heading) {
            heading.textContent = 'QR-Code herunterladen';
        }

        dialog.addEventListener('click', (event) => {
            const rect = dialog.getBoundingClientRect();

            if (
                event.clientX < rect.left
                || event.clientX > rect.right
                || event.clientY < rect.top
                || event.clientY > rect.bottom
            ) {
                dialog.close();
            }
        });
    }

    function enhanceLogin() {
        const login = qs('#login');

        if (!login || login.dataset.cpEnhanced === '1') {
            return;
        }

        login.dataset.cpEnhanced = '1';

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

        const form = qs('form', login);

        if (form && !qs('.cp-login-intro', form)) {
            const intro = element('div', 'cp-login-intro');
            intro.append(
                element('p', 'cp-eyebrow', 'Chromophobic Links'),
                element('h1', '', 'Willkommen zurück'),
                element(
                    'p',
                    'cp-card-copy',
                    'Melde dich an, um Kurzlinks und QR-Codes zu verwalten.'
                )
            );

            form.prepend(intro);
        }
    }

    function enhanceGenericTables() {
        qsa('main table:not(#main_table):not(.cp-users-list table)').forEach((table) => {
            if (table.dataset.cpGenericEnhanced === '1') {
                return;
            }

            table.dataset.cpGenericEnhanced = '1';

            if (!table.closest('.cp-table-scroll')) {
                const shell = element('div', 'cp-table-scroll');
                table.before(shell);
                shell.append(table);
            }
        });
    }

    function enhanceDialogs() {
        qsa('dialog').forEach((dialog) => {
            if (!dialog.classList.contains('cp-modal')) {
                dialog.classList.add('cp-modal');
            }
        });
    }

    function init() {
        initBody();
        enhanceBrand();
        enhanceNavigation();
        enhanceShortenerForm();
        enhanceShareBoxes();
        enhanceMainTable();
        enhanceUsersPage();
        enhanceQrDialog();
        enhanceLogin();
        enhanceGenericTables();
        enhanceDialogs();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
