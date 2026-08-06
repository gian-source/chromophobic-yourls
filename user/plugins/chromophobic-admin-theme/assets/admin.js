(() => {
    "use strict";

    const root = document.documentElement;
    const body = document.body;

    root.classList.remove("cp-theme-loading");
    root.classList.add("cp-theme-ready");
    body.classList.add("cp-admin");

    const wrap = document.getElementById("wrap");

    if (wrap) {
        wrap.classList.add("cp-window");
    }

    enhanceHeader();
    enhanceNavigation();
    enhanceLinkForm();
    enhanceTables();
    enhanceDialogs();

    function enhanceHeader() {
        const header = document.querySelector("header");

        if (!header || header.querySelector(".cp-window-controls")) {
            return;
        }

        const controls = document.createElement("div");
        controls.className = "cp-window-controls";
        controls.setAttribute("aria-hidden", "true");

        controls.innerHTML = `
            <span class="cp-window-control cp-window-close"></span>
            <span class="cp-window-control cp-window-minimise"></span>
            <span class="cp-window-control cp-window-expand"></span>
        `;

        header.prepend(controls);
    }

    function enhanceNavigation() {
        const navigation = document.querySelector("nav");

        if (!navigation) {
            return;
        }

        navigation.classList.add("cp-toolbar");

        const menu = document.getElementById("admin_menu");

        if (menu) {
            menu.classList.add("cp-toolbar-menu");
        }
    }

    function enhanceLinkForm() {
        const form = document.getElementById("new_url_form");

        if (!form) {
            return;
        }

        form.classList.add("cp-link-form");

        const urlInput = document.getElementById("add-url");
        const keywordInput = document.getElementById("add-keyword");

        if (urlInput) {
            urlInput.placeholder = "Lange URL einfügen";
            urlInput.setAttribute("autocomplete", "url");
        }

        if (keywordInput) {
            keywordInput.placeholder = "Eigener Kurzname";
            keywordInput.setAttribute("autocomplete", "off");
        }
    }

    function enhanceTables() {
        document.querySelectorAll("table").forEach((table) => {
            if (table.closest(".cp-table-scroll")) {
                return;
            }

            const wrapper = document.createElement("div");
            wrapper.className = "cp-table-scroll";

            table.parentNode.insertBefore(wrapper, table);
            wrapper.appendChild(table);
        });
    }

    function enhanceDialogs() {
        document.querySelectorAll("dialog").forEach((dialog) => {
            dialog.classList.add("cp-dialog");

            dialog.addEventListener("click", (event) => {
                const bounds = dialog.getBoundingClientRect();

                const outside =
                    event.clientX < bounds.left ||
                    event.clientX > bounds.right ||
                    event.clientY < bounds.top ||
                    event.clientY > bounds.bottom;

                if (outside) {
                    dialog.close();
                }
            });
        });
    }
})();