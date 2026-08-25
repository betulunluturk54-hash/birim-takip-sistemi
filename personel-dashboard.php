<?php

session_start();

if (!isset($_SESSION["kullanici_id"])) {
    header("Location: login.php");
    exit;
}

if ($_SESSION["rol"] != "personel") {
    header("Location: dashboard.php");
    exit;
}

require_once "config/database.php";


/* ==========================================
   GİRİŞ YAPAN PERSONELİ BUL
========================================== */

$sql = "
    SELECT
        p.id,
        p.ad,
        p.soyad
    FROM personeller p
    WHERE p.kullanici_id = ?
";

$stmt = $db->prepare($sql);
$stmt->execute([
    $_SESSION["kullanici_id"]
]);

$personel = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$personel) {

    die("Bu kullanıcıya bağlı bir personel kaydı bulunamadı.");

}


$personel_id = (int) $personel["id"];


/* ==========================================
   AKTİF EVRAK SAYISI
========================================== */

$sql = "
    SELECT COUNT(*)
    FROM evraklar
    WHERE personel_id = ?
    AND durum = 'aktif'
";

$stmt = $db->prepare($sql);
$stmt->execute([
    $personel_id
]);

$evrak_sayisi = (int) $stmt->fetchColumn();


/* ==========================================
   TOPLAM EVRAK SAYISI
========================================== */

$sql = "
    SELECT COUNT(*)
    FROM evraklar
    WHERE personel_id = ?
";

$stmt = $db->prepare($sql);
$stmt->execute([
    $personel_id
]);

$toplam_evrak_sayisi = (int) $stmt->fetchColumn();


/* ==========================================
   AKTİF GÖREVLERİ GETİR
========================================== */

$sql = "
    SELECT
        id,
        baslik,
        aciklama,
        baslangic_tarihi,
        teslim_tarihi,
        durum,
        oncelik
    FROM gorevler
    WHERE personel_id = ?
    AND durum != 'Tamamlandı'
    ORDER BY
        CASE
            WHEN teslim_tarihi IS NULL THEN 1
            ELSE 0
        END,
        teslim_tarihi ASC
";

$stmt = $db->prepare($sql);
$stmt->execute([
    $personel_id
]);

$gorevler = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* ==========================================
   AKTİF GÖREV SAYISI
========================================== */

$aktif_gorev_sayisi = count($gorevler);


/* ==========================================
   TAMAMLANAN GÖREV SAYISI
========================================== */

$sql = "
    SELECT COUNT(*)
    FROM gorevler
    WHERE personel_id = ?
    AND durum = 'Tamamlandı'
";

$stmt = $db->prepare($sql);
$stmt->execute([
    $personel_id
]);

$tamamlanan_gorev_sayisi =
    (int) $stmt->fetchColumn();


/* ==========================================
   TOPLAM GÖREV SAYISI
========================================== */

$toplam_gorev_sayisi =
    $aktif_gorev_sayisi +
    $tamamlanan_gorev_sayisi;


/* ==========================================
   SON EKLENEN EVRAKLARI GETİR
========================================== */

$sql = "
    SELECT
        id,
        evrak_adi,
        evrak_no,
        aciklama,
        tarih,
        dosya_yolu,
        durum,
        red_nedeni
    FROM evraklar
    WHERE personel_id = ?
    ORDER BY id DESC
    LIMIT 5
";

$stmt = $db->prepare($sql);
$stmt->execute([
    $personel_id
]);

$son_evraklar = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="tr">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Personel Paneli - Birim Takip
    </title>

    <script src="https://unpkg.com/lucide@latest"></script>

    <link
        rel="stylesheet"
        href="css/style.css"
    >

    <style>

        /* =========================================================
           KULLANICI AÇILIR MENÜ
        ========================================================= */

        .user-dropdown-wrapper {
            position: relative;
        }

        .user-dropdown-wrapper .user-area {
            cursor: pointer;
        }

        .user-dropdown {
            position: absolute;

            top: calc(100% + 10px);
            right: 0;

            min-width: 165px;

            padding: 6px;

            background: var(--white);

            border:
                1px solid var(--border);

            border-radius: 11px;

            box-shadow:
                0 10px 28px
                rgba(25, 45, 80, 0.12);

            opacity: 0;
            visibility: hidden;

            transform:
                translateY(-5px);

            transition:
                opacity 0.18s ease,
                transform 0.18s ease,
                visibility 0.18s ease;

            z-index: 3000;
        }

        .user-dropdown.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .user-dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;

            width: 100%;

            padding: 10px 11px;

            border-radius: 8px;

            color: #b33f3f;

            font-size: 13px;
            font-weight: 600;

            transition:
                background-color 0.2s ease,
                color 0.2s ease;
        }

        .user-dropdown-item:hover {
            background: #fff1f1;
            color: #a73535;
        }

        .user-dropdown-item svg {
            width: 17px;
            height: 17px;
            flex-shrink: 0;
        }


        /* =========================================================
           DARK MODE - KULLANICI MENÜSÜ
        ========================================================= */

        body.dark-mode .user-dropdown {
            background: #121b2d;
            border-color: #26344a;

            box-shadow:
                0 12px 30px
                rgba(0, 0, 0, 0.28);
        }

        body.dark-mode .user-dropdown-item {
            color: #f0a2a2;
        }

        body.dark-mode .user-dropdown-item:hover {
            background: #321d20;
            color: #ffb0b0;
        }

    </style>

</head>


<body>

<div class="dashboard">


    <!-- ==========================================
         SIDEBAR
    =========================================== -->

    <aside class="sidebar">

        <div class="brand">

            <div class="brand-logo">
                BT
            </div>

            <div>

                <h2>
                    BİRİM TAKİP
                </h2>

                <span>
                    Personel Paneli
                </span>

            </div>

        </div>


        <nav class="sidebar-menu">


            <!-- ANA SAYFA -->

            <a
                href="/BirimTakipSistemi/personel-dashboard.php"
                class="sidebar-link active"
            >

                <i
                    data-lucide="house"
                    class="menu-icon"
                ></i>

                <span>
                    Ana Sayfa
                </span>

            </a>


            <!-- GÖREVLERİM -->

            <a
                href="/BirimTakipSistemi/pages/personel-gorevlerim.php"
                class="sidebar-link"
            >

                <i
                    data-lucide="clipboard-list"
                    class="menu-icon"
                ></i>

                <span>
                    Görevlerim
                </span>

            </a>


            <!-- EVRAKLARIM -->

            <a
                href="/BirimTakipSistemi/pages/personel-evraklarim.php"
                class="sidebar-link"
            >

                <i
                    data-lucide="file-text"
                    class="menu-icon"
                ></i>

                <span>
                    Evraklarım
                </span>

            </a>


            <!-- PROFİLİM -->

            <a
                href="/BirimTakipSistemi/pages/personel-profil.php"
                class="sidebar-link"
            >

                <i
                    data-lucide="user"
                    class="menu-icon"
                ></i>

                <span>
                    Profilim
                </span>

            </a>

        </nav>


        <!-- ÇIKIŞ -->

        <div class="sidebar-bottom">

            <a
                href="logout.php"
                class="sidebar-link logout"
            >

                <i
                    data-lucide="log-out"
                    class="menu-icon"
                ></i>

                <span>
                    Çıkış Yap
                </span>

            </a>

        </div>

    </aside>


    <!-- ==========================================
         ANA İÇERİK
    =========================================== -->

    <main class="main-content">


        <!-- TOPBAR -->

        <header class="topbar">

            <div class="topbar-left">

                <button
                    type="button"
                    class="menu-button"
                >

                    <i data-lucide="menu"></i>

                </button>

                <span class="page-title">
                    Ana Sayfa
                </span>

            </div>


            <div class="topbar-right">


                <!-- TEMA -->

                <div class="theme-switch">

                    <button
                        type="button"
                        class="theme-button active"
                        id="lightTheme"
                    >

                        <i data-lucide="sun"></i>

                    </button>


                    <button
                        type="button"
                        class="theme-button"
                        id="darkTheme"
                    >

                        <i data-lucide="moon"></i>

                    </button>

                </div>


                <div class="topbar-divider"></div>


                <!-- KULLANICI -->

                <div class="user-dropdown-wrapper">

                    <div
                        class="user-area"
                        id="userArea"
                    >

                        <div class="user-avatar">

                            <?php

                            echo strtoupper(
                                substr(
                                    $_SESSION["kullanici_adi"],
                                    0,
                                    1
                                )
                            );

                            ?>

                        </div>


                        <div class="user-text">

                            <strong>

                                <?php

                                echo htmlspecialchars(
                                    $_SESSION["kullanici_adi"]
                                );

                                ?>

                            </strong>


                            <span>
                                Personel
                            </span>

                        </div>


                        <i
                            data-lucide="chevron-down"
                            class="user-arrow"
                        ></i>

                    </div>


                    <!-- AÇILIR MENÜ -->

                    <div
                        class="user-dropdown"
                        id="userDropdown"
                    >

                        <a
                            href="logout.php"
                            class="user-dropdown-item"
                        >

                            <i
                                data-lucide="log-out"
                            ></i>

                            <span>
                                Çıkış Yap
                            </span>

                        </a>

                    </div>

                </div>

            </div>

        </header>


        <!-- ==========================================
             SAYFA İÇERİĞİ
        =========================================== -->

        <section class="page-content">


            <!-- HOŞ GELDİNİZ -->

            <div class="welcome-section">

                <h1>

                    Hoş Geldiniz,
                    <?= htmlspecialchars(
                        $_SESSION["kullanici_adi"]
                    ) ?>

                </h1>

                <p>
                    Personel panelinize genel bakış
                </p>

            </div>


            <!-- ==========================================
                 İSTATİSTİKLER
            =========================================== -->

            <section
                class="stats-grid"
                style="
                    grid-template-columns:repeat(
                        3,
                        minmax(0,1fr)
                    );
                "
            >


                <!-- AKTİF GÖREV -->

                <div class="stat-card">

                    <div class="stat-icon green">

                        <i data-lucide="clipboard-list"></i>

                    </div>


                    <div class="stat-info">

                        <span>
                            Aktif Görevlerim
                        </span>

                        <strong>
                            <?= $aktif_gorev_sayisi ?>
                        </strong>

                    </div>

                </div>


                <!-- TAMAMLANAN GÖREV -->

                <div class="stat-card">

                    <div class="stat-icon blue">

                        <i data-lucide="circle-check"></i>

                    </div>


                    <div class="stat-info">

                        <span>
                            Tamamlanan Görevlerim
                        </span>

                        <strong>
                            <?= $tamamlanan_gorev_sayisi ?>
                        </strong>

                    </div>

                </div>


                <!-- EVRAK -->

                <div class="stat-card">

                    <div class="stat-icon orange">

                        <i data-lucide="file-text"></i>

                    </div>


                    <div class="stat-info">

                        <span>
                            Evraklarım
                        </span>

                        <strong>
                            <?= $evrak_sayisi ?>
                        </strong>

                    </div>

                </div>

            </section>


            <!-- ==========================================
                 GÖREVLER + EVRAKLAR
            =========================================== -->

            <section class="two-column">


                <!-- ======================================
                     GÖREVLERİM
                ======================================= -->

                <div class="large-card">


                    <div class="card-title-area">

                        <div>

                            <h2>
                                Görevlerim
                            </h2>

                            <p>
                                Size atanmış aktif görevler
                            </p>

                        </div>


                        <a
                            href="/BirimTakipSistemi/pages/personel-gorevlerim.php"
                            class="view-button"
                        >

                            Tümünü Gör

                        </a>

                    </div>


                    <?php if (count($gorevler) > 0): ?>


                        <div class="task-list">


                            <?php foreach (
                                $gorevler
                                as $gorev
                            ): ?>


                                <div class="task-item">


                                    <div class="task-item-icon">

                                        <i
                                            data-lucide="clipboard-list"
                                        ></i>

                                    </div>


                                    <div class="task-item-content">


                                        <strong>

                                            <?= htmlspecialchars(
                                                $gorev["baslik"]
                                            ) ?>

                                        </strong>


                                        <span>

                                            Teslim:

                                            <?php

                                            echo $gorev[
                                                "teslim_tarihi"
                                            ]
                                                ? date(
                                                    "d.m.Y",
                                                    strtotime(
                                                        $gorev[
                                                            "teslim_tarihi"
                                                        ]
                                                    )
                                                )
                                                : "-";

                                            ?>

                                        </span>

                                    </div>


                                    <div class="task-item-status">

                                        <span class="task-status">

                                            <?= htmlspecialchars(
                                                $gorev["durum"]
                                            ) ?>

                                        </span>

                                    </div>


                                </div>


                            <?php endforeach; ?>


                        </div>


                    <?php else: ?>


                        <div class="empty-area">

                            <div class="empty-icon">

                                <i
                                    data-lucide="clipboard-list"
                                ></i>

                            </div>

                            <p>
                                Henüz atanmış aktif görev bulunmuyor.
                            </p>

                        </div>


                    <?php endif; ?>


                </div>


                <!-- ======================================
                     EVRAKLARIM
                ======================================= -->

                <div class="large-card">


                    <div class="card-title-area">

                        <div>

                            <h2>
                                Evraklarım
                            </h2>

                            <p>
                                Size ait son evraklar
                            </p>

                        </div>


                        <a
                            href="/BirimTakipSistemi/pages/personel-evraklarim.php"
                            class="view-button"
                        >

                            Tümünü Gör

                        </a>

                    </div>


                    <?php if (count($son_evraklar) > 0): ?>


                        <div class="dashboard-list">


                            <?php foreach (
                                $son_evraklar
                                as $evrak
                            ): ?>


                                <div class="dashboard-list-item">


                                    <div class="dashboard-list-icon">

                                        <i
                                            data-lucide="file-text"
                                        ></i>

                                    </div>


                                    <div class="dashboard-list-info">

                                        <strong>

                                            <?= htmlspecialchars(
                                                $evrak["evrak_adi"]
                                            ) ?>

                                        </strong>


                                        <span>

                                            Evrak No:

                                            <?= !empty(
                                                $evrak["evrak_no"]
                                            )
                                                ? htmlspecialchars(
                                                    $evrak[
                                                        "evrak_no"
                                                    ]
                                                )
                                                : "-"
                                            ?>

                                            •

                                            <?= !empty(
                                                $evrak["tarih"]
                                            )
                                                ? date(
                                                    "d.m.Y",
                                                    strtotime(
                                                        $evrak[
                                                            "tarih"
                                                        ]
                                                    )
                                                )
                                                : "-"
                                            ?>

                                        </span>

                                    </div>


                                    <div
                                        class="
                                            dashboard-list-status
                                        "
                                    >

                                        <?php if (
                                            $evrak["durum"]
                                            === "onaylandi"
                                        ): ?>

                                            <span
                                                class="
                                                    document-status
                                                    approved
                                                "
                                            >
                                                Onaylandı
                                            </span>


                                        <?php elseif (
                                            $evrak["durum"]
                                            === "reddedildi"
                                        ): ?>

                                            <span
                                                class="
                                                    document-status
                                                    rejected
                                                "
                                            >
                                                Reddedildi
                                            </span>


                                        <?php else: ?>

                                            <span
                                                class="
                                                    document-status
                                                    pending
                                                "
                                            >
                                                Beklemede
                                            </span>

                                        <?php endif; ?>

                                    </div>

                                </div>


                            <?php endforeach; ?>


                        </div>


                    <?php else: ?>


                        <div class="empty-area">

                            <div class="empty-icon">

                                <i
                                    data-lucide="file-text"
                                ></i>

                            </div>

                            <p>
                                Henüz evrak bulunmuyor.
                            </p>

                        </div>


                    <?php endif; ?>


                </div>


            </section>


        </section>

    </main>

</div>


<script src="js/script.js"></script>


<script>

    lucide.createIcons();


    /* =====================================================
       KULLANICI AÇILIR MENÜSÜ
    ===================================================== */


</script>


</body>

</html>