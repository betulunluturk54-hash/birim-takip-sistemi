<?php

session_start();

if (!isset($_SESSION["kullanici_id"])) {
    header("Location: login.php");
    exit;
}

require_once "config/database.php";
/* ==========================================
   GİRİŞ YAPAN KULLANICI
========================================== */

$stmt = $db->prepare("
    SELECT
        id,
        ad,
        soyad,
        kullanici_adi,
        rol
    FROM kullanicilar
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([
    $_SESSION["kullanici_id"]
]);

$giris_yapan_kullanici = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$giris_yapan_kullanici) {
    session_destroy();
    header("Location: login.php");
    exit;
}

/* Gerçek ad soyad */
$kullanici_ad_soyad = trim(
    ($giris_yapan_kullanici["ad"] ?? "") .
    " " .
    ($giris_yapan_kullanici["soyad"] ?? "")
);

/* Ad soyad boşsa kullanıcı adını kullan */
if ($kullanici_ad_soyad === "") {
    $kullanici_ad_soyad =
        $giris_yapan_kullanici["kullanici_adi"];
}

/* Rolü kullanıcıya gösterilecek hale getir */
if (
    in_array(
        $giris_yapan_kullanici["rol"],
        ["yonetici", "admin"]
    )
) {
    $rol_etiketi = "Yönetici";
} elseif (
    $giris_yapan_kullanici["rol"] === "personel"
) {
    $rol_etiketi = "Personel";
} else {
    $rol_etiketi = "Kullanıcı";
}

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
   DUYURULAR
========================================== */

$sql = "
    SELECT
        d.id,
        d.baslik,
        d.icerik,
        d.yayin_tarihi,
        d.son_tarihi,
        d.oncelik,
        CASE
            WHEN o.id IS NULL THEN 0
            ELSE 1
        END AS okundu
    FROM duyurular d
    LEFT JOIN duyuru_okumalari o
        ON d.id = o.duyuru_id
        AND o.kullanici_id = ?
    WHERE d.aktif = 1
      AND (
          d.son_tarihi IS NULL
          OR d.son_tarihi >= NOW()
      )
    ORDER BY d.yayin_tarihi DESC
";

$stmt = $db->prepare($sql);

$stmt->execute([
    $_SESSION["kullanici_id"]
]);

$duyurular = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* ==========================================
   OKUNMAMIŞ / OKUNMUŞ AYIR
========================================== */

$okunmamis_duyurular = array_filter(
    $duyurular,
    function ($duyuru) {
        return (int) $duyuru["okundu"] === 0;
    }
);

$sql = "
    SELECT
        id,
        baslik,
        icerik,
        yayin_tarihi,
        son_tarihi,
        oncelik
    FROM duyurular
    WHERE aktif = 0
       OR (
           son_tarihi IS NOT NULL
           AND son_tarihi < NOW()
       )
    ORDER BY yayin_tarihi DESC
";

$stmt = $db->query($sql);
$gecmis_duyurular = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* ==========================================
   OKUNMAMIŞ DUYURU SAYISI
========================================== */

$okunmamis_duyuru = count($okunmamis_duyurular);


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


/* ==========================================
   GÖREV DURUMU GRAFİĞİ
========================================== */

if ($toplam_gorev_durumu > 0) {

    $tamamlanan_yuzde =
        ($tamamlanan_gorev / $toplam_gorev_durumu) * 100;

    $devam_eden_yuzde =
        ($devam_eden_gorev / $toplam_gorev_durumu) * 100;

    $bekleyen_yuzde =
        ($bekleyen_gorev / $toplam_gorev_durumu) * 100;

} else {

    $tamamlanan_yuzde = 0;
    $devam_eden_yuzde = 0;
    $bekleyen_yuzde = 0;
}

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


        /* =====================================================
           DUYURULAR
        ===================================================== */

        .announcement-wrapper {
            position: relative;

            display: flex;
            align-items: center;
        }


        .announcement-button {
            position: relative;

            width: 40px;
            height: 40px;

            padding: 0;

            border: 1px solid var(--border);
            border-radius: 10px;

            background: var(--white);
            color: var(--text);

            display: flex;
            align-items: center;
            justify-content: center;

            cursor: pointer;

            transition:
                background-color 0.2s ease,
                border-color 0.2s ease;
        }


        .announcement-button:hover {
            background: #f6f8fb;
        }


        .announcement-button svg {
            width: 19px;
            height: 19px;
        }


        .announcement-badge {
            position: absolute;

            top: -4px;
            right: -4px;

            min-width: 18px;
            height: 18px;

            padding: 0 5px;

            border-radius: 20px;

            background: #dc3545;
            color: #ffffff;

            font-size: 10px;
            font-weight: 700;

            display: flex;
            align-items: center;
            justify-content: center;

            border: 2px solid var(--white);
        }


        .announcement-dropdown {
            position: absolute;

            top: calc(100% + 10px);
            right: 0;

            width: 360px;
            max-width: calc(100vw - 30px);

            padding: 0;

            background: var(--white);

            border: 1px solid var(--border);
            border-radius: 13px;

            box-shadow:
                0 12px 30px rgba(25, 45, 80, 0.14);

            opacity: 0;
            visibility: hidden;

            transform: translateY(-5px);

            transition:
                opacity 0.18s ease,
                transform 0.18s ease,
                visibility 0.18s ease;

            z-index: 9999;

            overflow: hidden;
        }


        .announcement-dropdown.show {
            opacity: 1;
            visibility: visible;

            transform: translateY(0);
        }


        .announcement-header {
            padding: 14px 16px;

            border-bottom: 1px solid var(--border);

            display: flex;
            align-items: center;
            justify-content: space-between;

            gap: 12px;
        }


        .announcement-header-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }


        .announcement-header strong {
            font-size: 14px;
            color: var(--text);
        }


        .announcement-header span {
            font-size: 11px;
            color: var(--muted);
        }


        /* YÖNETİCİ - DUYURU EKLE */

        .announcement-add-button {
            display: inline-flex;

            align-items: center;
            justify-content: center;

            gap: 5px;

            padding: 6px 9px;

            border-radius: 7px;

            background: #eef3ff;

            color: #4d6fd8;

            text-decoration: none;

            font-size: 10px;

            font-weight: 700;

            white-space: nowrap;

            transition:
                background-color 0.2s ease,
                color 0.2s ease;
        }


        .announcement-add-button:hover {
            background: #e2e9ff;
            color: #405fbe;
        }


        .announcement-add-button svg {
            width: 13px;
            height: 13px;
        }


        .announcement-list {
            max-height: 390px;

            overflow-y: auto;
        }


        .announcement-item {
            display: flex;

            align-items: flex-start;

            gap: 11px;

            width: 100%;

            box-sizing: border-box;

            padding: 13px 15px;

            border-bottom: 1px solid var(--border);

            background: transparent;

            cursor: pointer;

            transition:
                background-color 0.2s ease,
                opacity 0.2s ease;
        }


        .announcement-item:hover {
            background: #f8fafc;
        }


        .announcement-item.unread {
            background: #f5f8ff;
        }


        .announcement-item.read {
            opacity: 0.82;
        }


        .announcement-icon {
            width: 34px;
            height: 34px;

            flex-shrink: 0;

            border-radius: 9px;

            display: flex;
            align-items: center;
            justify-content: center;

            background: #eef3ff;

            color: #4d6fd8;
        }


        .announcement-icon svg {
            width: 17px;
            height: 17px;
        }


        .announcement-content {
            flex: 1;

            min-width: 0;
        }


        .announcement-content strong {
            display: block;

            margin-bottom: 4px;

            font-size: 12px;

            color: var(--text);
        }


        .announcement-content p {
            margin: 0 0 5px;

            font-size: 11px;

            line-height: 1.45;

            color: var(--text);

            display: -webkit-box;

            -webkit-line-clamp: 3;

            -webkit-box-orient: vertical;

            overflow: hidden;
        }


        .announcement-content span {
            font-size: 10px;

            color: var(--muted);
        }


        .announcement-empty {
            padding: 30px 20px;

            text-align: center;

            color: var(--muted);

            font-size: 12px;
        }


        .announcement-empty svg {
            display: block;

            margin: 0 auto 8px;
        }


        /* =====================================================
           TÜMÜNÜ GÖSTER
        ===================================================== */

        .announcement-show-all {
            display: block;

            width: 100%;

            padding: 11px 15px;

            border: none;

            border-top: 1px solid var(--border);

            background: transparent;

            color: #4d6fd8;

            font-size: 11px;

            font-weight: 700;

            text-align: center;

            cursor: pointer;

            transition:
                background-color 0.2s ease,
                color 0.2s ease;
        }


        .announcement-show-all:hover {
            background: #f8fafc;

            color: #405fbe;
        }


        .announcement-read-list {
            display: none;
        }


        .announcement-read-list.show {
            display: block;
        }


        /* =====================================================
           DARK MODE - DUYURULAR
        ===================================================== */

        body.dark-mode .announcement-button {
            background: #121b2d;
            border-color: #26344a;
            color: #ffffff;
        }


        body.dark-mode .announcement-badge {
            border-color: #121b2d;
        }


        body.dark-mode .announcement-dropdown {
            background: #121b2d;
            border-color: #26344a;

            box-shadow:
                0 12px 30px rgba(0, 0, 0, 0.30);
        }


        body.dark-mode .announcement-header {
            border-color: #26344a;
        }


        body.dark-mode .announcement-item {
            border-color: #26344a;
        }


        body.dark-mode .announcement-item:hover {
            background: #182338;
        }


        body.dark-mode .announcement-item.unread {
            background: #17233b;
        }


        body.dark-mode .announcement-content strong,
        body.dark-mode .announcement-content p {
            color: #ffffff;
        }


        body.dark-mode .announcement-add-button {
            background: #1d2a47;

            color: #9eb3ff;
        }


        body.dark-mode .announcement-add-button:hover {
            background: #26385d;

            color: #b7c7ff;
        }


        body.dark-mode .announcement-show-all {
            border-color: #26344a;

            color: #9eb3ff;
        }


        body.dark-mode .announcement-show-all:hover {
            background: #182338;

            color: #b7c7ff;
        }


        /* ==========================================
           DARK MODE - KULLANICI
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


        /* ==========================================
           GÖREV DURUMU DONUT GRAFİĞİ
        ========================================== */

        .task-report .donut {

            --completed: <?= $tamamlanan_yuzde ?>%;
            --ongoing: <?= $devam_eden_yuzde ?>%;
            --waiting: <?= $bekleyen_yuzde ?>%;

            background:
                conic-gradient(

                    #348f4fc1 0 var(--completed),

                    #3d61a3cc
                    var(--completed)
                    calc(
                        var(--completed) +
                        var(--ongoing)
                    ),

                    #cf8e2dc2
                    calc(
                        var(--completed) +
                        var(--ongoing)
                    )
                    100%
                );

            position: relative;

            width: 145px;
            height: 145px;

            border-radius: 50%;

            display: flex;
            align-items: center;
            justify-content: center;
        }


        .task-report .donut::before {

            content: "";

            position: absolute;

            width: 96px;
            height: 96px;

            background: var(--white);

            border-radius: 50%;
        }


        body.dark-mode .task-report .donut::before {

            background: #121b2d;
        }


        .task-report .donut-inner {

            position: relative;

            z-index: 2;

            display: flex;

            flex-direction: column;

            align-items: center;
            justify-content: center;

            color: var(--text);

            font-size: 28px;
            font-weight: 800;
        }


        .task-report .donut-inner::after {

            content: "Toplam";

            display: block;

            margin-top: 3px;

            font-size: 9px;

            font-weight: 600;

            color: var(--muted);
        }


        .task-report {

            display: flex;

            align-items: center;
            justify-content: center;

            gap: 35px;
        }


        body.dark-mode .task-report .donut {

            box-shadow:
                0 0 0 1px
                rgba(255,255,255,.03);
        }


        .task-report .legend-dot.completed {
            background: #348f4fea;
        }


        .task-report .legend-dot.ongoing {
            background: #3d61a3ec;
        }


        .task-report .legend-dot.waiting {
            background: #cf8e2d;
        }


        /* ==========================================
           MOBİL
        ========================================== */

        @media (max-width: 700px) {

            .task-report {

                flex-direction: column;

                gap: 25px;
            }


            .announcement-dropdown {

                right: -60px;
            }


            .announcement-header {

                align-items: flex-start;
            }


            .announcement-add-button {

                font-size: 9px;

                padding: 6px 7px;
            }

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

            <!-- PROFİL -->

            <a
                href="profil.php"
                class="sidebar-link">

                <i
                    data-lucide="user"
                    class="menu-icon">
                </i>

                <span>
                    Profilim
                </span>

            </a>

        </nav>


        <!-- ÇIKIŞ -->

       <div
    class="user-dropdown"
    id="userDropdown">

    <a
        href="profil.php"
        class="user-dropdown-item profile-item">

        <i
            data-lucide="user-round">
        </i>

        <span>
            Profilim
        </span>

    </a>


    <a
        href="logout.php"
        class="user-dropdown-item logout-item">

        <i
            data-lucide="log-out">
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


                <!-- =================================================
                     DUYURULAR
                ================================================== -->

                <div class="announcement-wrapper">


                    <button
                        type="button"
                        class="announcement-button"
                        id="announcementButton"
                        title="Duyurular">


                        <i data-lucide="megaphone"></i>


                        <?php if ($okunmamis_duyuru > 0): ?>


                            <span
                                class="announcement-badge"
                                id="announcementBadge">


                                <?= $okunmamis_duyuru > 99
                                    ? "99+"
                                    : $okunmamis_duyuru ?>


                            </span>


                        <?php endif; ?>


                    </button>


                    <div
                        class="announcement-dropdown"
                        id="announcementDropdown">

<!-- DUYURU BAŞLIĞI -->

                        <div class="announcement-header">

                            <div class="announcement-header-left">

                                <strong>
                                    Duyurular
                                </strong>

                                <span
                                    id="announcementUnreadCount">

                                    <?= $okunmamis_duyuru ?>
                                    okunmamış

                                </span>

                            </div>

                            <!-- Butonların yan yana düzgün durması için kapsayıcı eklendi -->
                            <div style="display: flex; gap: 8px; align-items: center;">

                                <!-- "Tüm Duyurular" LİNKİ -->
                                <a
                                    href="pages/duyurular.php?gorunum=liste"
                                    class="announcement-archive-link"
                                    style="margin-left: 0;"> <!-- Ortadan hizalama sorunu için margin sıfırlandı -->

                                    <i data-lucide="list"></i>

                                    Tüm Duyurular

                                </a>

                                <!-- DUYURU EKLE (Admin İçin) -->

                                <?php if (
                                    in_array(
                                        $_SESSION["rol"],
                                        ["yonetici", "admin"]
                                    )
                                ): ?>

                                    <a
                                        href="pages/duyurular.php"
                                        class="announcement-add-button"
                                        title="Duyuru Ekle">

                                        <i data-lucide="plus"></i>

                                        Duyuru Ekle

                                    </a>

                                <?php endif; ?>

                            </div>

                        </div>

                        <!-- DUYURU LİSTESİ -->

                        <div
                            class="announcement-list"
                            id="announcementList">


                            <!-- ==========================
                                 OKUNMAMIŞ DUYURULAR
                            =========================== -->

                            <?php if (
                                count($okunmamis_duyurular) > 0
                            ): ?>


                                <?php foreach (
                                    $okunmamis_duyurular
                                    as $duyuru
                                ): ?>


                                    <div
                                        class="announcement-item unread"
                                        data-id="<?= (int) $duyuru["id"] ?>">


                                        <div
                                            class="announcement-icon">


                                            <i
                                                data-lucide="megaphone">
                                            </i>


                                        </div>


                                        <div
                                            class="announcement-content">


                                            <strong>


                                                <?= htmlspecialchars(
                                                    $duyuru["baslik"]
                                                ) ?>


                                            </strong>


                                            <p>


                                                <?= htmlspecialchars(
                                                    $duyuru["icerik"]
                                                ) ?>


                                            </p>


                                            <span>


                                                <?= date(
                                                    "d.m.Y H:i",
                                                    strtotime(
                                                        $duyuru["yayin_tarihi"]
                                                    )
                                                ) ?>


                                            </span>


                                        </div>


                                    </div>


                                <?php endforeach; ?>


                            <?php else: ?>


                                <div
                                    class="announcement-empty">


                                    <i
                                        data-lucide="megaphone-off"
                                        width="22"
                                        height="22">
                                    </i>


                                    Okunmamış duyuru bulunmuyor.


                                </div>


                            <?php endif; ?>


                            <!-- ==========================
                                  GEÇMİŞ DUYURULAR
                            =========================== -->


                            <?php if (
                                 count($gecmis_duyurular) > 0
                            ): ?>


                                <button
                                    type="button"
                                    class="announcement-show-all"
                                    id="announcementShowPast">


                                    Geçmiş Duyurular ↓


                                </button>


                                <div
                                    class="announcement-read-list"
                                    id="announcementPastList">


                                    <?php foreach (
                                        $gecmis_duyurular
                                        as $duyuru
                                    ): ?>


                                        <div
                                            class="announcement-item read"
                                            data-id="<?= (int) $duyuru["id"] ?>">


                                            <div
                                                class="announcement-icon">


                                                <i
                                                    data-lucide="megaphone">
                                                </i>


                                            </div>


                                            <div
                                                class="announcement-content">


                                                <strong>


                                                    <?= htmlspecialchars(
                                                        $duyuru["baslik"]
                                                    ) ?>


                                                </strong>


                                                <p>


                                                    <?= htmlspecialchars(
                                                        $duyuru["icerik"]
                                                    ) ?>


                                                </p>


                                                <span>


                                                    <?= date(
                                                        "d.m.Y H:i",
                                                        strtotime(
                                                            $duyuru["yayin_tarihi"]
                                                        )
                                                    ) ?>


                                                </span>


                                            </div>


                                        </div>


                                    <?php endforeach; ?>


                                    <button
                                        type="button"
                                        class="announcement-show-all"
                                        id="announcementHidePast">


                                        Güncel Duyurulara Dön ↑


                                    </button>


                                </div>


                            <?php endif; ?>


                        </div>


                    </div>


                </div>


                <!-- ================= TEMA ================= -->

                <div class="theme-switch">


                    <button
                        type="button"
                        class="theme-button active"
                        id="lightTheme"
                        aria-label="Açık tema">

                        <i data-lucide="sun"></i>

                    </button>


                    <button
                        type="button"
                        class="theme-button"
                        id="darkTheme"
                        aria-label="Koyu tema">

                        <i data-lucide="moon"></i>

                    </button>


                </div>


                <div class="topbar-divider"></div>


                <!-- ================= KULLANICI ================= -->
<div class="user-dropdown-wrapper">

    <div
        class="user-area"
        id="userArea">

        <div class="user-avatar">

            <?= htmlspecialchars(
                mb_strtoupper(
                    mb_substr(
                        $giris_yapan_kullanici["ad"]
                        ?: $giris_yapan_kullanici["kullanici_adi"],
                        0,
                        1
                    )
                )
            ) ?>

        </div>

        <div class="user-text">

            <strong>

                <?= htmlspecialchars(
                    $kullanici_ad_soyad
                ) ?>

            </strong>

            <span>

                <?= htmlspecialchars(
                    $rol_etiketi
                ) ?>

            </span>

        </div>

        <i
            data-lucide="chevron-down"
            class="user-arrow">
        </i>

    </div>


            </div>

        </header>


        <!-- ================= SAYFA İÇERİĞİ ================= -->

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
                    Birim takip sisteminize genel bakış
                </p>


            </div>


            <!-- ================= İSTATİSTİKLER ================= -->

            <section class="stats-grid">


                <!-- TOPLAM BİRİM -->

                <div class="stat-card">


                    <div class="stat-icon blue">


                        <i
                            data-lucide="building-2">
                        </i>


                    </div>


                    <div class="stat-info">


                        <span>
                            Toplam Birim
                        </span>


                        <strong>
                            <?= $toplam_birim ?>
                        </strong>


                        <a href="pages/birimler.php">
                            Tüm birimler →
                        </a>


                    </div>


                </div>


                <!-- TOPLAM PERSONEL -->

                <div class="stat-card">


                    <div class="stat-icon purple">


                        <i
                            data-lucide="users">
                        </i>


                    </div>


                    <div class="stat-info">


                        <span>
                            Toplam Personel
                        </span>


                        <strong>
                            <?= $toplam_personel ?>
                        </strong>


                        <a href="pages/personeller.php">
                            Tüm personeller →
                        </a>


                    </div>


                </div>


                <!-- AKTİF GÖREV -->

                <div class="stat-card">


                    <div class="stat-icon green">


                        <i
                            data-lucide="clipboard-list">
                        </i>


                    </div>


                    <div class="stat-info">


                        <span>
                            Aktif Görev
                        </span>


                        <strong>
                            <?= $aktif_gorev ?>
                        </strong>


                        <a href="pages/gorevler.php">
                            Tüm görevler →
                        </a>


                    </div>


                </div>


                <!-- TOPLAM EVRAK -->

                <div class="stat-card">


                    <div class="stat-icon orange">


                        <i
                            data-lucide="file-text">
                        </i>


                    </div>


                    <div class="stat-info">


                        <span>
                            Toplam Evrak
                        </span>


                        <strong>
                            <?= $toplam_evrak ?>
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


                        <?php if (
                            count($son_gorevler) > 0
                        ): ?>


                            <?php foreach (
                                $son_gorevler
                                as $gorev
                            ): ?>


                                <div
                                    class="dashboard-list-item">


                                    <div
                                        class="dashboard-list-icon">


                                        <i
                                            data-lucide="clipboard-list">
                                        </i>


                                    </div>


                                    <div
                                        class="dashboard-list-info">


                                        <strong>


                                            <?= htmlspecialchars(
                                                $gorev["baslik"]
                                            ) ?>


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


                                    <div
                                        class="dashboard-list-status">


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


                        <?php if (
                            count($son_evraklar) > 0
                        ): ?>


                            <?php foreach (
                                $son_evraklar
                                as $evrak
                            ): ?>


                                <div
                                    class="dashboard-list-item">


                                    <div
                                        class="dashboard-list-icon">


                                        <i
                                            data-lucide="file-text">
                                        </i>


                                    </div>


                                    <div
                                        class="dashboard-list-info">


                                        <strong>


                                            <?= htmlspecialchars(
                                                $evrak["evrak_adi"]
                                            ) ?>


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


                                    <div
                                        class="dashboard-list-status">


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


                                <?= $toplam_gorev_durumu ?>


                            </div>


                        </div>


                        <div class="legend">


                            <div class="legend-item">


                                <span
                                    class="legend-dot completed">
                                </span>


                                <span>
                                    Tamamlanan
                                </span>


                                <strong>
                                    <?= $tamamlanan_gorev ?>
                                </strong>


                            </div>


                            <div class="legend-item">


                                <span
                                    class="legend-dot ongoing">
                                </span>


                                <span>
                                    Devam Eden
                                </span>


                                <strong>
                                    <?= $devam_eden_gorev ?>
                                </strong>


                            </div>


                            <div class="legend-item">


                                <span
                                    class="legend-dot waiting">
                                </span>


                                <span>
                                    Bekleyen
                                </span>


                                <strong>
                                    <?= $bekleyen_gorev ?>
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


<!-- ================= JS ================= -->

<script src="js/script.js"></script>


<script>

    lucide.createIcons();

    const userDropdown =
        document.getElementById("userDropdown");


    /* =====================================================
       DUYURU MENÜSÜ
    ===================================================== */

    const announcementButton =
        document.getElementById("announcementButton");

    const announcementDropdown =
        document.getElementById("announcementDropdown");

    const announcementBadge =
        document.getElementById("announcementBadge");

    const announcementUnreadCount =
        document.getElementById("announcementUnreadCount");


    announcementButton.addEventListener(
        "click",
        function (e) {

            e.stopPropagation();

            const isOpen =
                announcementDropdown.classList.toggle("show");


            if (isOpen) {

                userDropdown.classList.remove("show");

            }

        }
    );


    /* =====================================================
       DUYURUYA TIKLAYINCA OKUNDU YAP
    ===================================================== */

    document
        .querySelectorAll(".announcement-item.unread")
        .forEach(function (item) {

            item.addEventListener(
                "click",
                function () {

                    const duyuruId =
                        this.dataset.id;

                    if (!duyuruId) {
                        return;
                    }


                    this.classList.remove("unread");

                    this.classList.add("read");


                    let yeniSayi = 0;


                    document
                        .querySelectorAll(
                            ".announcement-item.unread"
                        )
                        .forEach(function () {

                            yeniSayi++;

                        });


                    if (announcementUnreadCount) {

                        announcementUnreadCount.textContent =
                            yeniSayi + " okunmamış";

                    }


                    if (announcementBadge) {

                        if (yeniSayi > 0) {

                            announcementBadge.textContent =
                                yeniSayi > 99
                                    ? "99+"
                                    : yeniSayi;

                        } else {

                            announcementBadge.remove();

                        }

                    }


                    const formData =
                        new FormData();

                    formData.append(
                        "action",
                        "duyuruyu_oku"
                    );

                    formData.append(
                        "duyuru_id",
                        duyuruId
                    );


                    fetch(
                        window.location.href,
                        {
                            method: "POST",
                            body: formData
                        }
                    ).catch(function (err) {

                        console.error(
                            "Duyuru okundu güncelleme hatası:",
                            err
                        );

                    });

                }
            );

        });


    /* =====================================================
       GEÇMİŞ DUYURULAR
    ===================================================== */

    const announcementShowPast =
        document.getElementById(
            "announcementShowPast"
        );

    const announcementHidePast =
        document.getElementById(
            "announcementHidePast"
        );

    const announcementPastList =
        document.getElementById(
            "announcementPastList"
        );


    if (announcementShowPast) {

        announcementShowPast.addEventListener(
            "click",
            function (e) {

                e.stopPropagation();

                announcementPastList.classList.add(
                    "show"
                );

                announcementShowPast.style.display =
                    "none";

            }
        );

    }


    if (announcementHidePast) {

        announcementHidePast.addEventListener(
            "click",
            function (e) {

                e.stopPropagation();

                announcementPastList.classList.remove(
                    "show"
                );

                announcementShowPast.style.display =
                    "block";

            }
        );

    }


    /* =====================================================
       DUYURU MENÜSÜNE TIKLAYINCA KAPANMASIN
    ===================================================== */

    announcementDropdown.addEventListener(
        "click",
        function (e) {

            e.stopPropagation();

        }
    );


    /* =====================================================
       DIŞARI TIKLANINCA MENÜLERİ KAPAT
    ===================================================== */

    document.addEventListener(
        "click",
        function () {

            userDropdown.classList.remove(
                "show"
            );

            announcementDropdown.classList.remove(
                "show"
            );

        }
    );

</script>


</body>

</html>