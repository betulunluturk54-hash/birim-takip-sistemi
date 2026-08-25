<?php

session_start();

if (!isset($_SESSION["kullanici_id"])) {
    header("Location: login.php");
    exit;
}

require_once "config/database.php";


/* ==========================================
   DASHBOARD İSTATİSTİKLERİ
========================================== */

// Toplam birim
$stmt = $db->query("
    SELECT COUNT(*)
    FROM birimler
");

$toplam_birim = (int) $stmt->fetchColumn();


// Aktif personel
$stmt = $db->query("
    SELECT COUNT(*)
    FROM personeller
    WHERE durum = 'aktif'
");

$toplam_personel = (int) $stmt->fetchColumn();


// Toplam görev
$stmt = $db->query("
    SELECT COUNT(*)
    FROM gorevler
");

$aktif_gorev = (int) $stmt->fetchColumn();


// Toplam evrak
$stmt = $db->query("
    SELECT COUNT(*)
    FROM evraklar
");

$toplam_evrak = (int) $stmt->fetchColumn();


/* ==========================================
   SON GÖREVLER
========================================== */

$stmt = $db->query("
    SELECT
        g.id,
        g.baslik,
        g.durum,
        g.oncelik,
        g.teslim_tarihi,
        p.ad,
        p.soyad
    FROM gorevler g
    LEFT JOIN personeller p
        ON g.personel_id = p.id
    ORDER BY g.id DESC
    LIMIT 5
");

$son_gorevler = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* ==========================================
   SON EVRAKLAR
========================================== */

$stmt = $db->query("
    SELECT
        e.id,
        e.evrak_adi,
        e.evrak_no,
        e.tarih,
        e.durum,
        p.ad,
        p.soyad
    FROM evraklar e
    LEFT JOIN personeller p
        ON e.personel_id = p.id
    ORDER BY e.id DESC
    LIMIT 5
");

$son_evraklar = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* ==========================================
   GÖREV DURUMLARI
========================================== */

$stmt = $db->query("
    SELECT durum, COUNT(*) AS toplam
    FROM gorevler
    GROUP BY durum
");

$gorev_durumlari = $stmt->fetchAll(PDO::FETCH_ASSOC);

$tamamlanan_gorev = 0;
$devam_eden_gorev = 0;
$bekleyen_gorev = 0;

foreach ($gorev_durumlari as $durum) {

    $durum_adi = strtolower(
        trim(
            $durum["durum"] ?? ""
        )
    );

    $toplam = (int) $durum["toplam"];

    if (
        $durum_adi === "tamamlandi" ||
        $durum_adi === "tamamlandı"
    ) {

        $tamamlanan_gorev += $toplam;

    } elseif (
        $durum_adi === "devam_ediyor" ||
        $durum_adi === "devam ediyor"
    ) {

        $devam_eden_gorev += $toplam;

    } elseif (
        $durum_adi === "bekliyor" ||
        $durum_adi === "beklemede" ||
        $durum_adi === "bekleyen"
    ) {

        $bekleyen_gorev += $toplam;
    }
}

$toplam_gorev_durumu =
    $tamamlanan_gorev +
    $devam_eden_gorev +
    $bekleyen_gorev;

?>

<!DOCTYPE html>
<html lang="tr">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        Birim Takip Sistemi
    </title>

    <script src="https://unpkg.com/lucide@latest"></script>

    <link
        rel="stylesheet"
        href="css/style.css">


    <style>

        /* ==========================================
           KULLANICI AÇILIR MENÜ
        ========================================== */

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

            border: 1px solid var(--border);

            border-radius: 11px;

            box-shadow:
                0 10px 28px rgba(25, 45, 80, 0.12);

            opacity: 0;
            visibility: hidden;

            transform: translateY(-5px);

            transition:
                opacity 0.18s ease,
                transform 0.18s ease,
                visibility 0.18s ease;

            z-index: 5000;
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

            text-decoration: none;

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


        /* ==========================================
           DARK MODE
        ========================================== */

        body.dark-mode .user-dropdown {
            background: #121b2d;
            border-color: #26344a;

            box-shadow:
                0 12px 30px rgba(0, 0, 0, 0.28);
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


    <!-- ================= SIDEBAR ================= -->

    <aside class="sidebar">


        <!-- LOGO -->

        <div class="brand">

            <div class="brand-logo">
                BT
            </div>

            <div>

                <h2>
                    BİRİM TAKİP
                </h2>

                <span>
                    Yönetim Sistemi
                </span>

            </div>

        </div>


        <!-- MENÜ -->

        <nav class="sidebar-menu">


            <!-- ANA SAYFA -->

            <a
                href="dashboard.php"
                class="sidebar-link active">

                <i
                    data-lucide="house"
                    class="menu-icon">
                </i>

                <span>
                    Ana Sayfa
                </span>

            </a>


            <!-- BİRİMLER -->

            <a
                href="pages/birimler.php"
                class="sidebar-link">

                <i
                    data-lucide="building-2"
                    class="menu-icon">
                </i>

                <span>
                    Birimler
                </span>

            </a>


            <!-- PERSONELLER -->

            <a
                href="pages/personeller.php"
                class="sidebar-link">

                <i
                    data-lucide="users"
                    class="menu-icon">
                </i>

                <span>
                    Personeller
                </span>

            </a>


            <!-- GÖREVLER -->

            <a
                href="pages/gorevler.php"
                class="sidebar-link">

                <i
                    data-lucide="clipboard-list"
                    class="menu-icon">
                </i>

                <span>
                    Görevler
                </span>

            </a>


            <!-- EVRAKLAR -->

            <a
                href="pages/evraklar.php"
                class="sidebar-link">

                <i
                    data-lucide="file-text"
                    class="menu-icon">
                </i>

                <span>
                    Evraklar
                </span>

            </a>


        </nav>


        <!-- ÇIKIŞ -->

        <div class="sidebar-bottom">

            <a
                href="logout.php"
                class="sidebar-link logout">

                <i
                    data-lucide="log-out"
                    class="menu-icon">
                </i>

                <span>
                    Çıkış Yap
                </span>

            </a>

        </div>

    </aside>


    <!-- ================= ANA ALAN ================= -->

    <main class="main-content">


        <!-- TOPBAR -->

        <header class="topbar">


            <div class="topbar-left">


                <!-- MENÜ BUTONU -->

                <button
                    type="button"
                    class="menu-button"
                    aria-label="Menüyü aç veya kapat">

                    <i data-lucide="menu"></i>

                </button>


                <span class="page-title">
                    Ana Sayfa
                </span>


            </div>


            <div class="topbar-right">


                <!-- TEMA -->

                <div class="theme-switch">


                    <!-- AÇIK TEMA -->

                    <button
                        type="button"
                        class="theme-button active"
                        id="lightTheme"
                        aria-label="Açık tema">

                        <i data-lucide="sun"></i>

                    </button>


                    <!-- KOYU TEMA -->

                    <button
                        type="button"
                        class="theme-button"
                        id="darkTheme"
                        aria-label="Koyu tema">

                        <i data-lucide="moon"></i>

                    </button>


                </div>


                <div class="topbar-divider"></div>


                <!-- KULLANICI -->

                <div class="user-dropdown-wrapper">


                    <div
                        class="user-area"
                        id="userArea">


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

                                <?php

                                echo htmlspecialchars(
                                    $_SESSION["rol"]
                                );

                                ?>

                            </span>


                        </div>


                        <i
                            data-lucide="chevron-down"
                            class="user-arrow">
                        </i>


                    </div>


                    <!-- AÇILIR MENÜ -->

                    <div
                        class="user-dropdown"
                        id="userDropdown">


                        <a
                            href="logout.php"
                            class="user-dropdown-item">


                            <i
                                data-lucide="log-out">
                            </i>


                            <span>
                                Çıkış Yap
                            </span>


                        </a>


                    </div>


                </div>


            </div>


        </header>


        <!-- ================= SAYFA İÇERİĞİ ================= -->

        <section class="page-content">


            <!-- HOŞ GELDİNİZ -->

            <div class="welcome-section">


                <h1>

                    Hoş Geldiniz,

                    <?php

                    echo htmlspecialchars(
                        $_SESSION["kullanici_adi"]
                    );

                    ?>

                </h1>


                <p>
                    Birim takip sisteminize genel bakış
                </p>


            </div>


            <!-- ================= İSTATİSTİKLER ================= -->

            <section class="stats-grid">


                <!-- TOPLAM BİRİM -->

                <div class="stat-card">


                    <div class="stat-icon blue">

                        <i data-lucide="building-2"></i>

                    </div>


                    <div class="stat-info">


                        <span>
                            Toplam Birim
                        </span>


                        <strong>
                            <?php echo $toplam_birim; ?>
                        </strong>


                        <a href="pages/birimler.php">
                            Tüm birimler →
                        </a>


                    </div>


                </div>


                <!-- TOPLAM PERSONEL -->

                <div class="stat-card">


                    <div class="stat-icon purple">

                        <i data-lucide="users"></i>

                    </div>


                    <div class="stat-info">


                        <span>
                            Toplam Personel
                        </span>


                        <strong>
                            <?php echo $toplam_personel; ?>
                        </strong>


                        <a href="pages/personeller.php">
                            Tüm personeller →
                        </a>


                    </div>


                </div>


                <!-- AKTİF GÖREV -->

                <div class="stat-card">


                    <div class="stat-icon green">

                        <i data-lucide="clipboard-list"></i>

                    </div>


                    <div class="stat-info">


                        <span>
                            Aktif Görev
                        </span>


                        <strong>
                            <?php echo $aktif_gorev; ?>
                        </strong>


                        <a href="pages/gorevler.php">
                            Tüm görevler →
                        </a>


                    </div>


                </div>


                <!-- TOPLAM EVRAK -->

                <div class="stat-card">


                    <div class="stat-icon orange">

                        <i data-lucide="file-text"></i>

                    </div>


                    <div class="stat-info">


                        <span>
                            Toplam Evrak
                        </span>


                        <strong>
                            <?php echo $toplam_evrak; ?>
                        </strong>


                        <a href="pages/evraklar.php">
                            Tüm evraklar →
                        </a>


                    </div>


                </div>


            </section>


            <!-- ================= SON GÖREVLER / SON EVRAKLAR ================= -->

            <section class="two-column">


                <!-- SON GÖREVLER -->

                <div class="large-card">


                    <div class="card-title-area">


                        <div>

                            <h2>
                                Son Görevler
                            </h2>

                            <p>
                                Yakın zamanda eklenen görevler
                            </p>

                        </div>


                        <a
                            href="pages/gorevler.php"
                            class="view-button">

                            Tümünü Gör →

                        </a>


                    </div>


                    <div class="dashboard-list">


                        <?php if (count($son_gorevler) > 0): ?>


                            <?php foreach ($son_gorevler as $gorev): ?>


                                <div class="dashboard-list-item">


                                    <div class="dashboard-list-icon">

                                        <i data-lucide="clipboard-list"></i>

                                    </div>


                                    <div class="dashboard-list-info">


                                        <strong>

                                            <?php

                                            echo htmlspecialchars(
                                                $gorev["baslik"]
                                            );

                                            ?>

                                        </strong>


                                        <span>

                                            <?php

                                            if (
                                                !empty($gorev["ad"]) &&
                                                !empty($gorev["soyad"])
                                            ) {

                                                echo htmlspecialchars(
                                                    $gorev["ad"] .
                                                    " " .
                                                    $gorev["soyad"]
                                                );

                                            } else {

                                                echo "Personel atanmadı";

                                            }

                                            ?>

                                        </span>


                                    </div>


                                    <div class="dashboard-list-status">


                                        <?php

                                        $gorev_durum =
                                            $gorev["durum"] ?? "";

                                        if (
                                            $gorev_durum ===
                                            "tamamlandi"
                                        ) {

                                            echo '
                                                <span class="document-status approved">
                                                    Tamamlandı
                                                </span>
                                            ';

                                        } elseif (
                                            $gorev_durum ===
                                            "devam_ediyor"
                                        ) {

                                            echo '
                                                <span class="document-status pending">
                                                    Devam Ediyor
                                                </span>
                                            ';

                                        } elseif (
                                            $gorev_durum ===
                                            "bekliyor"
                                        ) {

                                            echo '
                                                <span class="document-status pending">
                                                    Bekliyor
                                                </span>
                                            ';

                                        } else {

                                            echo '
                                                <span class="document-status pending">'
                                                . htmlspecialchars(
                                                    $gorev_durum
                                                    ?: "Belirtilmedi"
                                                )
                                                . '
                                                </span>';
                                        }

                                        ?>


                                    </div>


                                </div>


                            <?php endforeach; ?>


                        <?php else: ?>


                            <div class="empty-area">


                                <div class="empty-icon">

                                    <i
                                        data-lucide="clipboard-list">
                                    </i>

                                </div>


                                <p>
                                    Henüz görev bulunmuyor.
                                </p>


                            </div>


                        <?php endif; ?>


                    </div>


                </div>


                <!-- SON EVRAKLAR -->

                <div class="large-card">


                    <div class="card-title-area">


                        <div>

                            <h2>
                                Son Evraklar
                            </h2>

                            <p>
                                Son eklenen evraklar
                            </p>

                        </div>


                        <a
                            href="pages/evraklar.php"
                            class="view-button">

                            Tümünü Gör →

                        </a>


                    </div>


                    <div class="dashboard-list">


                        <?php if (count($son_evraklar) > 0): ?>


                            <?php foreach ($son_evraklar as $evrak): ?>


                                <div class="dashboard-list-item">


                                    <div class="dashboard-list-icon">

                                        <i data-lucide="file-text"></i>

                                    </div>


                                    <div class="dashboard-list-info">


                                        <strong>

                                            <?php

                                            echo htmlspecialchars(
                                                $evrak["evrak_adi"]
                                            );

                                            ?>

                                        </strong>


                                        <span>

                                            <?php

                                            if (
                                                !empty($evrak["ad"]) &&
                                                !empty($evrak["soyad"])
                                            ) {

                                                echo htmlspecialchars(
                                                    $evrak["ad"] .
                                                    " " .
                                                    $evrak["soyad"]
                                                );

                                            } else {

                                                echo "Personel atanmadı";

                                            }

                                            ?>

                                        </span>


                                    </div>


                                    <div class="dashboard-list-status">


                                        <?php

                                        $evrak_durum =
                                            $evrak["durum"] ?? "";

                                        if (
                                            $evrak_durum ===
                                            "onaylandi"
                                        ) {

                                            echo '
                                                <span class="document-status approved">
                                                    Onaylandı
                                                </span>
                                            ';

                                        } elseif (
                                            $evrak_durum ===
                                            "reddedildi"
                                        ) {

                                            echo '
                                                <span class="document-status rejected">
                                                    Reddedildi
                                                </span>
                                            ';

                                        } elseif (
                                            $evrak_durum ===
                                            "beklemede"
                                        ) {

                                            echo '
                                                <span class="document-status pending">
                                                    Beklemede
                                                </span>
                                            ';

                                        } else {

                                            echo '
                                                <span class="document-status pending">'
                                                . htmlspecialchars(
                                                    $evrak_durum
                                                    ?: "Belirtilmedi"
                                                )
                                                . '
                                                </span>';
                                        }

                                        ?>


                                    </div>


                                </div>


                            <?php endforeach; ?>


                        <?php else: ?>


                            <div class="empty-area">


                                <div class="empty-icon">

                                    <i
                                        data-lucide="file-text">
                                    </i>

                                </div>


                                <p>
                                    Henüz evrak bulunmuyor.
                                </p>


                            </div>


                        <?php endif; ?>


                    </div>


                </div>


            </section>


            <!-- ================= RAPORLAR ================= -->

            <section class="two-column report-section">


                <!-- GÖREV DURUMU -->

                <div class="report-card">


                    <h2>
                        Görev Durumu
                    </h2>


                    <div class="task-report">


                        <div class="donut">

                            <div class="donut-inner">

                                <?php
                                echo $toplam_gorev_durumu;
                                ?>

                            </div>

                        </div>


                        <div class="legend">


                            <div class="legend-item">

                                <span class="legend-dot completed"></span>

                                <span>
                                    Tamamlanan
                                </span>

                                <strong>
                                    <?php
                                    echo $tamamlanan_gorev;
                                    ?>
                                </strong>

                            </div>


                            <div class="legend-item">

                                <span class="legend-dot ongoing"></span>

                                <span>
                                    Devam Eden
                                </span>

                                <strong>
                                    <?php
                                    echo $devam_eden_gorev;
                                    ?>
                                </strong>

                            </div>


                            <div class="legend-item">

                                <span class="legend-dot waiting"></span>

                                <span>
                                    Bekleyen
                                </span>

                                <strong>
                                    <?php
                                    echo $bekleyen_gorev;
                                    ?>
                                </strong>

                            </div>


                        </div>


                    </div>


                </div>


                <!-- PERSONEL DAĞILIMI -->

                <div class="report-card">


                    <h2>
                        Birimlere Göre Personel Dağılımı
                    </h2>


                    <div class="chart-placeholder">

                        <span>
                            Veri bulunmuyor.
                        </span>

                    </div>


                </div>


            </section>


        </section>


    </main>


</div>


<!-- JS -->

<script src="js/script.js"></script>


<script>

    lucide.createIcons();

</script>


</body>

</html>