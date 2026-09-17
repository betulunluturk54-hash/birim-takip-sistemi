<?php

session_start();

if (!isset($_SESSION["kullanici_id"])) {
    header("Location: ../login.php");
    exit;
}

if ($_SESSION["rol"] != "personel") {
    header("Location: ../dashboard.php");
    exit;
}

require_once __DIR__ . "/../config/database.php";


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
    die("Bu kullanıcıya bağlı personel kaydı bulunamadı.");
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
   BİLDİRİMDEN GELEN GÖREV
========================================== */

$secilen_gorev_id = (int) ($_GET["gorev_id"] ?? 0);


/* ==========================================
   GÖREV DURUMU GÜNCELLEME
========================================== */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["gorev_durum_guncelle"])
) {

    $gorev_id = (int) ($_POST["gorev_id"] ?? 0);

    $yeni_durum = trim(
        $_POST["yeni_durum"] ?? ""
    );

    $izin_verilen_durumlar = [
        "Bekliyor",
        "Devam Ediyor",
        "Tamamlandı"
    ];

    if (
        $gorev_id > 0
        && in_array(
            $yeni_durum,
            $izin_verilen_durumlar,
            true
        )
    ) {

        /*
         * Sadece giriş yapan personele ait görevin
         * durumu güncellenebilir.
         */

        $sql = "
            UPDATE gorevler
            SET durum = ?
            WHERE id = ?
            AND personel_id = ?
        ";

        $stmt = $db->prepare($sql);

        $stmt->execute([
            $yeni_durum,
            $gorev_id,
            $personel_id
        ]);
    }

    if ($secilen_gorev_id > 0) {

        header(
            "Location: personel-gorevlerim.php?gorev_id="
            . $secilen_gorev_id
        );

    } else {

        header(
            "Location: personel-gorevlerim.php"
        );
    }

    exit;
}


/* ==========================================
   PERSONELE AİT GÖREVLERİ GETİR
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
    ORDER BY teslim_tarihi ASC
";

$stmt = $db->prepare($sql);

$stmt->execute([
    $personel_id
]);

$gorevler = $stmt->fetchAll(
    PDO::FETCH_ASSOC
);

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
        Görevlerim - Birim Takip
    </title>

    <script src="https://unpkg.com/lucide@latest"></script>

    <link
        rel="stylesheet"
        href="../css/style.css"
    >

    <style>

        /* ==========================================
           SEÇİLEN GÖREV
        ========================================== */

        .selected-task {
            outline: 2px solid #4d6fd8;
            outline-offset: 2px;
            background: rgba(77, 111, 216, 0.08);
            animation: selectedTaskPulse 1.2s ease;
        }

        @keyframes selectedTaskPulse {
            0% {
                box-shadow: 0 0 0 0 rgba(77, 111, 216, 0.35);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(77, 111, 216, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(77, 111, 216, 0);
            }
        }

        body.dark-mode .selected-task {
            background: rgba(77, 111, 216, 0.14);
            outline-color: #7188e8;
        }


        /* ==========================================
           GÖREV DURUM SEÇİMİ
        ========================================== */

        .task-status-select-form {
            margin: 0;
        }

        .task-status-select {
            height: 34px;
            min-width: 125px;
            padding: 0 28px 0 10px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--white);
            color: var(--text);
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            outline: none;
            transition: border-color .18s ease, box-shadow .18s ease;
        }

        .task-status-select:hover {
            border-color: #b8c3d4;
        }

        .task-status-select:focus {
            border-color: #4d6fd8;
            box-shadow: 0 0 0 3px rgba(77, 111, 216, .08);
        }

        .task-status-select.bekliyor {
            background: #fff6e5;
            color: #a97212;
            border-color: #f0d9a5;
        }

        .task-status-select.devam-ediyor {
            background: #eaf1ff;
            color: #3372f4;
            border-color: #cbd9fa;
        }

        .task-status-select.tamamlandi {
            background: #eaf8f0;
            color: #21804c;
            border-color: #c8ead7;
        }

        body.dark-mode .task-status-select {
            background: #121b2d;
            border-color: #2a394f;
            color: #e5ebf3;
        }

        body.dark-mode .task-status-select.bekliyor {
            background: #382d16;
            color: #e3bf6b;
            border-color: #5a481f;
        }

        body.dark-mode .task-status-select.devam-ediyor {
            background: #172c50;
            color: #9db8ff;
            border-color: #304c80;
        }

        body.dark-mode .task-status-select.tamamlandi {
            background: #183a29;
            color: #7bd9a4;
            border-color: #2c6044;
        }


        /* ==========================================
           GÖREV DETAY LİNKİ
        ========================================== */

        .personel-task-detail-link {
            color: var(--text);
            text-decoration: none;
            font-weight: 700;
            transition: color .18s ease;
        }

        .personel-task-detail-link:hover {
            color: #4d6fd8;
        }

        body.dark-mode .personel-task-detail-link {
            color: #e5ebf3;
        }

        body.dark-mode .personel-task-detail-link:hover {
            color: #8ca7ff;
        }

    </style>

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

                <h2>
                    BİRİM TAKİP
                </h2>

                <span>
                    Personel Paneli
                </span>

            </div>


        </div>


        <nav class="sidebar-menu">


            <a
                href="/BirimTakipSistemi/personel-dashboard.php"
                class="sidebar-link"
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
                class="sidebar-link active"
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
                href="../logout.php"
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
                    Görevlerim
                </span>


            </div>


            <div class="topbar-right">


                <?php
                require_once __DIR__ . "/../includes/duyuru-sistemi.php";
                ?>


                <?php
                require_once __DIR__ . "/../personel-bildirimler.php";
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
                                        $personel["ad"] ?: $_SESSION["kullanici_adi"],
                                        0,
                                        1,
                                        "UTF-8"
                                    )
                                )
                            ) ?>

                        </div>


                        <div class="user-text">

                            <strong>
                                <?= htmlspecialchars($personel_adi) ?>
                            </strong>

                            <span>
                                <?= htmlspecialchars($personel_gorevi) ?>
                            </span>

                        </div>


                        <i
                            data-lucide="chevron-down"
                            class="user-arrow"
                        ></i>


                    </div>


                    <!-- ÇIKIŞ MENÜSÜ -->

                    <div
                        class="user-dropdown"
                        id="userDropdown"
                    >

                        <a
                            href="../logout.php"
                            class="user-dropdown-item"
                        >

                            <i data-lucide="log-out"></i>

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


            <div class="welcome-section">

                <h1>
                    Görevlerim
                </h1>

                <p>
                    Size atanmış görevleri buradan takip edebilirsiniz.
                </p>

            </div>


            <div class="large-card">


                <div class="card-title-area">

                    <div>

                        <h2>
                            Görev Listem
                        </h2>

                        <p>
                            Toplam <?= count($gorevler); ?> görev
                        </p>

                    </div>

                </div>


                <?php if (count($gorevler) > 0): ?>


                    <div class="task-list">


                        <?php foreach ($gorevler as $gorev): ?>

                            <?php
                            $gorevId = (int) $gorev["id"];
                            $durumSinifi = "";

                            if ($gorev["durum"] === "Bekliyor") {
                                $durumSinifi = "bekliyor";
                            } elseif ($gorev["durum"] === "Devam Ediyor") {
                                $durumSinifi = "devam-ediyor";
                            } elseif ($gorev["durum"] === "Tamamlandı") {
                                $durumSinifi = "tamamlandi";
                            }
                            ?>

                            <div
                                class="task-item <?= $secilen_gorev_id === $gorevId ? "selected-task" : ""; ?>"
                                id="gorev-<?= $gorevId; ?>"
                            >

                                <div class="task-item-icon">
                                    <i data-lucide="clipboard-list"></i>
                                </div>

                                <div class="task-item-content">

                                    <strong>
                                        <a
                                            href="personel-gorev-durum.php?id=<?= $gorevId ?>"
                                            class="personel-task-detail-link"
                                        >
                                            <?= htmlspecialchars($gorev["baslik"]) ?>
                                        </a>
                                    </strong>

                                    <span>
                                        Başlangıç:
                                        <?= $gorev["baslangic_tarihi"] ? date("d.m.Y", strtotime($gorev["baslangic_tarihi"])) : "-" ?>
                                        &nbsp;&nbsp;|&nbsp;&nbsp;
                                        Teslim:
                                        <?= $gorev["teslim_tarihi"] ? date("d.m.Y", strtotime($gorev["teslim_tarihi"])) : "-" ?>
                                    </span>

                                </div>

                                <div class="task-item-status">

                                    <form
                                        method="POST"
                                        class="task-status-select-form"
                                    >

                                        <input
                                            type="hidden"
                                            name="gorev_id"
                                            value="<?= $gorevId; ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="gorev_durum_guncelle"
                                            value="1"
                                        >

                                        <select
                                            name="yeni_durum"
                                            class="task-status-select <?= $durumSinifi; ?>"
                                            onchange="this.form.submit();"
                                        >

                                            <option
                                                value="Bekliyor"
                                                <?= $gorev["durum"] === "Bekliyor" ? "selected" : ""; ?>
                                            >
                                                Bekliyor
                                            </option>

                                            <option
                                                value="Devam Ediyor"
                                                <?= $gorev["durum"] === "Devam Ediyor" ? "selected" : ""; ?>
                                            >
                                                Devam Ediyor
                                            </option>

                                            <option
                                                value="Tamamlandı"
                                                <?= $gorev["durum"] === "Tamamlandı" ? "selected" : ""; ?>
                                            >
                                                Tamamlandı
                                            </option>

                                        </select>

                                    </form>

                                </div>

                            </div>

                        <?php endforeach; ?>


                    </div>


                <?php else: ?>


                    <div class="empty-area">

                        <div class="empty-icon">
                            <i data-lucide="clipboard-list"></i>
                        </div>

                        <p>
                            Henüz size atanmış görev bulunmuyor.
                        </p>

                    </div>


                <?php endif; ?>


            </div>


        </section>


    </main>


</div>


<!-- ==========================================
     JAVASCRIPT
========================================== -->

<script src="../js/script.js"></script>

<script>
    if (typeof lucide !== "undefined") {
        lucide.createIcons();
    }

    /* ==========================================
       BİLDİRİMDEN GELEN GÖREVE KAYDIR
    ========================================== */
    const secilenGorevId = <?= $secilen_gorev_id; ?>;

    if (secilenGorevId > 0) {
        const gorev = document.getElementById("gorev-" + secilenGorevId);

        if (gorev) {
            setTimeout(function () {
                gorev.scrollIntoView({
                    behavior: "smooth",
                    block: "center"
                });
            }, 200);
        }
    }
</script>

</body>

</html>