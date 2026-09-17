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

$gorev_sutunu = null;

try {

    $sutun_sorgusu = $db->query("
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'personeller'
        AND COLUMN_NAME IN (
            'gorev',
            'unvan',
            'pozisyon',
            'gorevi',
            'gorev_adi'
        )
    ");

    $bulunan_sutunlar = $sutun_sorgusu->fetchAll(PDO::FETCH_COLUMN);

    if (in_array("gorev", $bulunan_sutunlar)) {

        $gorev_sutunu = "gorev";

    } elseif (in_array("unvan", $bulunan_sutunlar)) {

        $gorev_sutunu = "unvan";

    } elseif (in_array("pozisyon", $bulunan_sutunlar)) {

        $gorev_sutunu = "pozisyon";

    } elseif (in_array("gorevi", $bulunan_sutunlar)) {

        $gorev_sutunu = "gorevi";

    } elseif (in_array("gorev_adi", $bulunan_sutunlar)) {

        $gorev_sutunu = "gorev_adi";
    }

} catch (PDOException $e) {

    $gorev_sutunu = null;
}


/* ==========================================
   PERSONEL BİLGİLERİ
========================================== */

if ($gorev_sutunu !== null) {

    $sql = "
        SELECT
            p.id,
            p.ad,
            p.soyad,
            p.`$gorev_sutunu` AS personel_gorevi
        FROM personeller p
        WHERE p.kullanici_id = ?
    ";

} else {

    $sql = "
        SELECT
            p.id,
            p.ad,
            p.soyad,
            NULL AS personel_gorevi
        FROM personeller p
        WHERE p.kullanici_id = ?
    ";
}

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
   PERSONEL ADI
========================================== */

$personel_adi = trim(
    ($personel["ad"] ?? "") . " " . ($personel["soyad"] ?? "")
);

if ($personel_adi === "") {

    $personel_adi = $_SESSION["kullanici_adi"];
}


/* ==========================================
   PERSONEL GÖREVİ
========================================== */

$personel_gorevi = trim(
    $personel["personel_gorevi"] ?? ""
);

if ($personel_gorevi === "") {

    $personel_gorevi = "Görev bilgisi bulunmuyor";
}


/* ==========================================
   AKTİF EVRAK SAYISI
========================================== */

$sql = "
    SELECT COUNT(*)
    FROM evraklar
    WHERE personel_id = ?
    AND durum <> 'arsivlendi'
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

$tamamlanan_gorev_sayisi = (int) $stmt->fetchColumn();

$toplam_gorev_sayisi =
    $aktif_gorev_sayisi + $tamamlanan_gorev_sayisi;


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

    <title>Personel Paneli - Birim Takip</title>

    <script src="https://unpkg.com/lucide@latest"></script>

    <link
        rel="stylesheet"
        href="css/style.css"
    >

</head>


<body>

<div class="dashboard">


    <!-- ==========================================
         SIDEBAR
    ========================================== -->

    <aside class="sidebar">

        <div class="brand">

            <div class="brand-logo">
                BT
            </div>

            <div>

                <h2>BİRİM TAKİP</h2>

                <span>
                    Personel Paneli
                </span>

            </div>

        </div>


        <nav class="sidebar-menu">


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
    ========================================== -->

    <main class="main-content">


        <!-- ==========================================
             TOPBAR
        ========================================== -->

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


                <?php
                require_once __DIR__ . "/includes/duyuru-sistemi.php";
                ?>


                <?php
                require_once __DIR__ . "/personel-bildirimler.php";
                ?>


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


                <!-- ==========================================
                     KULLANICI
                =========================================== -->

                <div class="user-dropdown-wrapper">


                    <div
                        class="user-area"
                        id="userArea"
                    >


                        <div class="user-avatar">

                            <?= htmlspecialchars(
                                strtoupper(
                                    mb_substr(
                                        $personel["ad"],
                                        0,
                                        1,
                                        "UTF-8"
                                    )
                                )
                            ) ?>

                        </div>


                        <div class="user-text">


                            <strong>

                                <?= htmlspecialchars(
                                    $personel_adi
                                ) ?>

                            </strong>


                            <span>

                                <?= htmlspecialchars(
                                    $personel_gorevi
                                ) ?>

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
                            href="/BirimTakipSistemi/logout.php"
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
        ========================================== -->

        <section class="page-content">


            <!-- HOŞ GELDİNİZ -->

            <div class="welcome-section">

                <h1>

                    Hoş Geldiniz,

                    <?= htmlspecialchars(
                        $personel_adi
                    ) ?>

                </h1>


                <p>
                    Personel panelinize genel bakış
                </p>

            </div>


            <!-- ==========================================
                 İSTATİSTİKLER
            ========================================== -->

            <section
                class="stats-grid"
                style="grid-template-columns: repeat(3, minmax(0, 1fr));"
            >


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
            ========================================== -->

            <section class="two-column">


                <!-- GÖREVLER -->

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


                            <?php foreach ($gorevler as $gorev): ?>


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

                                            <?= $gorev["teslim_tarihi"]
                                                ? date(
                                                    "d.m.Y",
                                                    strtotime(
                                                        $gorev["teslim_tarihi"]
                                                    )
                                                )
                                                : "-"
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


                <!-- EVRAKLAR -->

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


                            <?php foreach ($son_evraklar as $evrak): ?>


                                <div
                                    class="dashboard-list-item"
                                >


                                    <div
                                        class="dashboard-list-icon"
                                    >

                                        <i
                                            data-lucide="file-text"
                                        ></i>

                                    </div>


                                    <div
                                        class="dashboard-list-info"
                                    >


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
                                                    $evrak["evrak_no"]
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
                                                        $evrak["tarih"]
                                                    )
                                                )
                                                : "-"
                                            ?>

                                        </span>


                                    </div>


                                    <div
                                        class="dashboard-list-status"
                                    >


                                        <?php if (
                                            $evrak["durum"]
                                            === "onaylandi"
                                        ): ?>


                                            <span
                                                class="document-status approved"
                                            >
                                                Onaylandı
                                            </span>


                                        <?php elseif (
                                            $evrak["durum"]
                                            === "reddedildi"
                                        ): ?>


                                            <span
                                                class="document-status rejected"
                                            >
                                                Reddedildi
                                            </span>


                                        <?php else: ?>


                                            <span
                                                class="document-status pending"
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


<!-- ==========================================
     JAVASCRIPT
========================================== -->

<script src="js/script.js"></script>

<script>
    if (typeof lucide !== "undefined") {
        lucide.createIcons();
    }
</script>

</body>

</html>