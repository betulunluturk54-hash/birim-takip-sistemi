document.addEventListener("DOMContentLoaded", function () {

    /* ============================================
       GENEL ELEMANLAR
    ============================================ */

    const dashboard =
        document.querySelector(".dashboard");

    const menuButton =
        document.querySelector(".menu-button");

    const lightThemeButton =
        document.getElementById("lightTheme");

    const darkThemeButton =
        document.getElementById("darkTheme");


    /* ============================================
       SIDEBAR AÇ / KAPAT
    ============================================ */

    if (menuButton && dashboard) {

        menuButton.addEventListener(
            "click",
            function () {

                dashboard.classList.toggle(
                    "sidebar-collapsed"
                );

                localStorage.setItem(
                    "sidebarCollapsed",
                    dashboard.classList.contains(
                        "sidebar-collapsed"
                    )
                );

            }
        );

    }


    const sidebarState =
        localStorage.getItem(
            "sidebarCollapsed"
        );


    if (
        sidebarState === "true" &&
        dashboard
    ) {

        dashboard.classList.add(
            "sidebar-collapsed"
        );

    }


    /* ============================================
       TEMA
    ============================================ */

    function applyTheme(theme) {

        if (theme === "dark") {

            document.body.classList.add(
                "dark-mode"
            );


            if (darkThemeButton) {

                darkThemeButton.classList.add(
                    "active"
                );

            }


            if (lightThemeButton) {

                lightThemeButton.classList.remove(
                    "active"
                );

            }

        } else {

            document.body.classList.remove(
                "dark-mode"
            );


            if (lightThemeButton) {

                lightThemeButton.classList.add(
                    "active"
                );

            }


            if (darkThemeButton) {

                darkThemeButton.classList.remove(
                    "active"
                );

            }

        }


        localStorage.setItem(
            "theme",
            theme
        );

    }


    const savedTheme =
        localStorage.getItem(
            "theme"
        );


    if (savedTheme === "dark") {

        applyTheme("dark");

    } else {

        applyTheme("light");

    }


    /* ============================================
       AÇIK TEMA
    ============================================ */

    if (lightThemeButton) {

        lightThemeButton.addEventListener(
            "click",
            function (event) {

                event.preventDefault();

                applyTheme("light");

            }
        );

    }


    /* ============================================
       KOYU TEMA
    ============================================ */

    if (darkThemeButton) {

        darkThemeButton.addEventListener(
            "click",
            function (event) {

                event.preventDefault();

                applyTheme("dark");

            }
        );

    }


    /* ============================================
       KULLANICI MENÜSÜ
       TÜM ADMIN + PERSONEL SAYFALARINDA
       OTOMATİK OLARAK OLUŞTURULUR
    ============================================ */

    const userAreas =
        document.querySelectorAll(
            ".user-area"
        );


    userAreas.forEach(function (userArea) {

        /*
         * Sayfada sidebar'daki mevcut
         * Çıkış Yap bağlantısını bul.
         *
         * Böylece:
         *
         * Admin:
         * logout.php
         *
         * Pages:
         * ../logout.php
         *
         * otomatik olarak doğru alınır.
         */

        const sidebarLogout =
            document.querySelector(
                ".sidebar .logout"
            );


        let logoutHref =
            "logout.php";


        if (
            sidebarLogout &&
            sidebarLogout.getAttribute("href")
        ) {

            logoutHref =
                sidebarLogout.getAttribute("href");

        }


        /* -----------------------------------------
           WRAPPER
        ----------------------------------------- */

        let wrapper =
            userArea.closest(
                ".user-dropdown-wrapper"
            );


        /*
         * Eğer sayfada wrapper yoksa
         * otomatik oluştur.
         */

        if (!wrapper) {

            wrapper =
                document.createElement(
                    "div"
                );

            wrapper.className =
                "user-dropdown-wrapper";


            userArea.parentNode.insertBefore(
                wrapper,
                userArea
            );


            wrapper.appendChild(
                userArea
            );

        }


        /* -----------------------------------------
           AŞAĞI OK
        ----------------------------------------- */

        let arrow =
            userArea.querySelector(
                ".user-arrow"
            );


        /*
         * Eğer aşağı ok yoksa otomatik ekle.
         */

        if (!arrow) {

            arrow =
                document.createElement(
                    "i"
                );

            arrow.setAttribute(
                "data-lucide",
                "chevron-down"
            );

            arrow.className =
                "user-arrow";


            userArea.appendChild(
                arrow
            );

        }


        /* -----------------------------------------
           DROPDOWN
        ----------------------------------------- */

        let dropdown =
            wrapper.querySelector(
                ".user-dropdown"
            );


        /*
         * Eğer dropdown yoksa
         * otomatik oluştur.
         */

        if (!dropdown) {

            dropdown =
                document.createElement(
                    "div"
                );

            dropdown.className =
                "user-dropdown";


            const link =
                document.createElement(
                    "a"
                );

            link.href =
                logoutHref;

            link.className =
                "user-dropdown-item";


            const icon =
                document.createElement(
                    "i"
                );

            icon.setAttribute(
                "data-lucide",
                "log-out"
            );


            const text =
                document.createElement(
                    "span"
                );

            text.textContent =
                "Çıkış Yap";


            link.appendChild(
                icon
            );

            link.appendChild(
                text
            );


            dropdown.appendChild(
                link
            );


            wrapper.appendChild(
                dropdown
            );

        }


        /* -----------------------------------------
           AÇ / KAPAT
        ----------------------------------------- */

        if (
            !userArea.dataset.dropdownReady
        ) {

            userArea.dataset.dropdownReady =
                "true";


            userArea.addEventListener(
                "click",
                function (event) {

                    event.stopPropagation();


                    /*
                     * Diğer kullanıcı menülerini kapat
                     */

                    document
                        .querySelectorAll(
                            ".user-dropdown.show"
                        )
                        .forEach(
                            function (menu) {

                                if (
                                    menu !== dropdown
                                ) {

                                    menu.classList.remove(
                                        "show"
                                    );

                                }

                            }
                        );


                    dropdown.classList.toggle(
                        "show"
                    );


                    userArea.classList.toggle(
                        "open"
                    );

                }
            );


            dropdown.addEventListener(
                "click",
                function (event) {

                    event.stopPropagation();

                }
            );

        }

    });


    /* ============================================
       SAYFADA BAŞKA YERE TIKLANINCA
       TÜM MENÜLERİ KAPAT
    ============================================ */

    document.addEventListener(
        "click",
        function () {

            document
                .querySelectorAll(
                    ".user-dropdown.show"
                )
                .forEach(
                    function (menu) {

                        menu.classList.remove(
                            "show"
                        );

                    }
                );


            document
                .querySelectorAll(
                    ".user-area.open"
                )
                .forEach(
                    function (area) {

                        area.classList.remove(
                            "open"
                        );

                    }
                );

        }
    );


    /* ============================================
       LUCIDE
       Script hazırsa yeni eklenen ikonları oluştur
    ============================================ */

    if (
        typeof lucide !== "undefined"
    ) {

        lucide.createIcons();

    }

});