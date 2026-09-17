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

    const notificationButton =
        document.getElementById("notificationButton");

    const notificationDropdown =
        document.getElementById("notificationDropdown");


    /* ============================================
       BİLDİRİM MENÜSÜ
    ============================================ */

    if (notificationButton && notificationDropdown) {

        notificationButton.addEventListener("click", function (event) {
            event.preventDefault();
            event.stopPropagation();

            notificationDropdown.classList.toggle("show");

            /* Kullanıcı menüsünü kapat */
            document
                .querySelectorAll(".user-dropdown.show")
                .forEach(function (menu) {
                    menu.classList.remove("show");
                });

            document
                .querySelectorAll(".user-area.open")
                .forEach(function (area) {
                    area.classList.remove("open");
                });
        });

        notificationDropdown.addEventListener("click", function (event) {
            event.stopPropagation();
        });
    }


    /* ============================================
       SIDEBAR AÇ / KAPAT
    ============================================ */

    if (menuButton && dashboard) {

        menuButton.addEventListener("click", function () {
            dashboard.classList.toggle("sidebar-collapsed");

            localStorage.setItem(
                "sidebarCollapsed",
                dashboard.classList.contains("sidebar-collapsed")
            );
        });
    }

    const sidebarState = localStorage.getItem("sidebarCollapsed");

    if (sidebarState === "true" && dashboard) {
        dashboard.classList.add("sidebar-collapsed");
    }


    /* ============================================
       TEMA YÖNETİMİ
    ============================================ */

    function applyTheme(theme) {
        if (theme === "dark") {
            document.body.classList.add("dark-mode");

            if (darkThemeButton) darkThemeButton.classList.add("active");
            if (lightThemeButton) lightThemeButton.classList.remove("active");
        } else {
            document.body.classList.remove("dark-mode");

            if (lightThemeButton) lightThemeButton.classList.add("active");
            if (darkThemeButton) darkThemeButton.classList.remove("active");
        }

        localStorage.setItem("theme", theme);
    }

    const savedTheme = localStorage.getItem("theme");

    if (savedTheme === "dark") {
        applyTheme("dark");
    } else {
        applyTheme("light");
    }

    if (lightThemeButton) {
        lightThemeButton.addEventListener("click", function (event) {
            event.preventDefault();
            applyTheme("light");
        });
    }

    if (darkThemeButton) {
        darkThemeButton.addEventListener("click", function (event) {
            event.preventDefault();
            applyTheme("dark");
        });
    }


    /* ============================================
       KULLANICI MENÜSÜ
    ============================================ */

    const userAreas = document.querySelectorAll(".user-area");

    userAreas.forEach(function (userArea) {

        const sidebarLogout = document.querySelector(".sidebar .logout");
        let logoutHref = "logout.php";

        if (sidebarLogout && sidebarLogout.getAttribute("href")) {
            logoutHref = sidebarLogout.getAttribute("href");
        }

        let wrapper = userArea.closest(".user-dropdown-wrapper");

        if (!wrapper) {
            wrapper = document.createElement("div");
            wrapper.className = "user-dropdown-wrapper";
            userArea.parentNode.insertBefore(wrapper, userArea);
            wrapper.appendChild(userArea);
        }

        let arrow = userArea.querySelector(".user-arrow");

        if (!arrow) {
            arrow = document.createElement("i");
            arrow.setAttribute("data-lucide", "chevron-down");
            arrow.className = "user-arrow";
            userArea.appendChild(arrow);
        }

        let dropdown = wrapper.querySelector(".user-dropdown");

        if (!dropdown) {
            dropdown = document.createElement("div");
            dropdown.className = "user-dropdown";

            const link = document.createElement("a");
            link.href = logoutHref;
            link.className = "user-dropdown-item";

            const icon = document.createElement("i");
            icon.setAttribute("data-lucide", "log-out");

            const text = document.createElement("span");
            text.textContent = "Çıkış Yap";

            link.appendChild(icon);
            link.appendChild(text);
            dropdown.appendChild(link);
            wrapper.appendChild(dropdown);
        }

        /* Tıklama Olayı */
        if (!userArea.dataset.dropdownReady) {
            userArea.dataset.dropdownReady = "true";

            userArea.addEventListener("click", function (event) {
                event.stopPropagation();

                /* Bildirim menüsü açıksa kapat */
                if (notificationDropdown) {
                    notificationDropdown.classList.remove("show");
                }

                /* Diğer kullanıcı menülerini kapat */
                document
                    .querySelectorAll(".user-dropdown.show")
                    .forEach(function (menu) {
                        if (menu !== dropdown) {
                            menu.classList.remove("show");
                        }
                    });

                dropdown.classList.toggle("show");
                userArea.classList.toggle("open");
            });

            dropdown.addEventListener("click", function (event) {
                event.stopPropagation();
            });
        }
    });


    /* ============================================
       SAYFA DIŞINA TIKLANINCA MENÜLERİ KAPAT
    ============================================ */

    document.addEventListener("click", function () {
        if (notificationDropdown) {
            notificationDropdown.classList.remove("show");
        }

        document
            .querySelectorAll(".user-dropdown.show")
            .forEach(function (menu) {
                menu.classList.remove("show");
            });

        document
            .querySelectorAll(".user-area.open")
            .forEach(function (area) {
                area.classList.remove("open");
            });
    });


    /* ============================================
       LUCIDE İKONLARI OLUŞTUR
    ============================================ */

    if (typeof lucide !== "undefined") {
        lucide.createIcons();
    }
});