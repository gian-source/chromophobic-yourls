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

    if (!form || form.dataset.cpEnhanced === "true") {
        return;
    }

    const originalContainer = form.firstElementChild;
    const urlInput = document.getElementById("add-url");
    const keywordInput = document.getElementById("add-keyword");
    const submitButton = document.getElementById("add-button");
    const nonceInput = document.getElementById("nonce-add");

    if (
        !originalContainer ||
        !urlInput ||
        !keywordInput ||
        !submitButton
    ) {
        return;
    }

    form.dataset.cpEnhanced = "true";

    urlInput.placeholder = "https://example.com/meine-lange-url";
    urlInput.removeAttribute("size");
    urlInput.setAttribute("inputmode", "url");
    urlInput.setAttribute("aria-label", "Ziel-URL");

    keywordInput.placeholder = "mein-link";
    keywordInput.removeAttribute("size");
    keywordInput.setAttribute("aria-label", "Eigener Kurzname");

    submitButton.value = "Kurzlink erstellen";
    submitButton.classList.add("cp-shortener-submit");

    const panel = document.createElement("div");
    panel.className = "cp-shortener-panel";

    panel.innerHTML = `
        <div class="cp-shortener-header">
            <div class="cp-shortener-heading">
                <span class="cp-shortener-eyebrow">
                    Neuer Kurzlink
                </span>

                <h2>Link kürzen</h2>

                <p>
                    Erstelle einen kurzen, übersichtlichen Link
                    für eine beliebige Zieladresse.
                </p>
            </div>

            <div class="cp-shortener-symbol" aria-hidden="true">
                <svg
                    viewBox="0 0 24 24"
                    width="24"
                    height="24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path
                        d="M10.6 13.4a4.4 4.4 0 0 0 6.2 0l2.4-2.4
                           a4.4 4.4 0 0 0-6.2-6.2l-1.4 1.4"
                    />
                    <path
                        d="M13.4 10.6a4.4 4.4 0 0 0-6.2 0L4.8 13
                           a4.4 4.4 0 0 0 6.2 6.2l1.4-1.4"
                    />
                </svg>
            </div>
        </div>

        <div class="cp-shortener-body">
            <div class="cp-shortener-field cp-shortener-url-field">
                <label for="add-url">Ziel-URL</label>
                <div
                    class="cp-shortener-control"
                    data-cp-slot="url"
                ></div>
            </div>

            <div class="cp-shortener-bottom-row">
                <div class="cp-shortener-field">
                    <div class="cp-shortener-label-row">
                        <label for="add-keyword">
                            Eigener Kurzname
                        </label>

                        <span>Optional</span>
                    </div>

                    <div class="cp-shortener-keyword-control">
                        <span class="cp-shortener-prefix">
                            go.chromophobic.ch/
                        </span>

                        <div data-cp-slot="keyword"></div>
                    </div>
                </div>

                <div
                    class="cp-shortener-button-slot"
                    data-cp-slot="button"
                ></div>
            </div>
        </div>
    `;

    panel
        .querySelector('[data-cp-slot="url"]')
        .appendChild(urlInput);

    panel
        .querySelector('[data-cp-slot="keyword"]')
        .appendChild(keywordInput);

    panel
        .querySelector('[data-cp-slot="button"]')
        .appendChild(submitButton);

    if (nonceInput) {
        panel.appendChild(nonceInput);
    }

    originalContainer.replaceWith(panel);
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