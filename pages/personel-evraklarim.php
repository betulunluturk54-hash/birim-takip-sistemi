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

require_once "../config/database.php";


/* =========================================================
   GİRİŞ YAPAN PERSONELİ BUL
========================================================= */
$sql = "
    SELECT
        id,
        ad,
        soyad,
        gorev
    FROM personeller
    WHERE kullanici_id = ?
";

$stmt = $db->prepare($sql);

$stmt->execute([
    $_SESSION["kullanici_id"]
]);

$personel = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$personel) {
    die("Bu kullanıcıya bağlı personel kaydı bulunamadı.");
}

$personel_id = (int) $personel["id"];
$personel_adi = trim(
    ($personel["ad"] ?? "") . " " . ($personel["soyad"] ?? "")
);

if ($personel_adi === "") {
    $personel_adi = $_SESSION["kullanici_adi"];
}

$personel_gorevi = trim(
    $personel["gorev"] ?? ""
);

if ($personel_gorevi === "") {
    $personel_gorevi = "Görev bilgisi bulunmuyor";
}


/* =========================================================
   YARDIMCI FONKSİYON
========================================================= */

function temizle(string $veri): string
{
    return htmlspecialchars(
        $veri,
        ENT_QUOTES,
        "UTF-8"
    );
}


/* =========================================================
   PERSONELE AİT EVRAKLARI GETİR
========================================================= */

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
    ORDER BY tarih DESC, id DESC
";

$stmt = $db->prepare($sql);

$stmt->execute([
    $personel_id
]);

$evraklar = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        Evraklarım - Birim Takip
    </title>

    <script src="https://unpkg.com/lucide@latest"></script>

    <link
        rel="stylesheet"
        href="../css/style.css"
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
        }


        /* =========================================================
           TABLO ALANI (KAYDIRMA ÇUBUĞU KALDIRILMIŞ ŞIK DÜZEN)
        ========================================================= */

        .table-container {
            width: 100%;
            overflow-x: hidden;
            border: 1px solid var(--border);
            border-radius: 12px;
            margin-top: 18px;
            background: #ffffff;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            min-width: 0 !important;
        }

        .data-table thead {
            background: #f8fafc;
        }

        .data-table th {
            padding: 13px 14px;
            text-align: left;
            color: var(--text-soft);
            font-size: 11px;
            font-weight: 750;
            letter-spacing: .25px;
            text-transform: uppercase;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }

        .data-table td {
            padding: 14px;
            color: var(--text);
            font-size: 13px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }

        .data-table tbody tr:hover {
            background: #f8faff;
        }

        .data-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Sütun Oranları */
        .data-table th:nth-child(1),
        .data-table td:nth-child(1) {
            width: 45px;
            text-align: center;
        }

        .data-table th:nth-child(2),
        .data-table td:nth-child(2) {
            width: 22%;
        }

        .data-table th:nth-child(3),
        .data-table td:nth-child(3) {
            width: 14%;
        }

        .data-table th:nth-child(4),
        .data-table td:nth-child(4) {
            width: 20%;
        }

        .data-table th:nth-child(5),
        .data-table td:nth-child(5) {
            width: 12%;
        }

        .data-table th:nth-child(6),
        .data-table td:nth-child(6) {
            width: 14%;
        }

        .data-table th:nth-child(7),
        .data-table td:nth-child(7) {
            width: 18%;
        }


        /* =========================================================
           EVRAK DURUMLARI
        ========================================================= */

        .personel-evrak-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;

            padding: 5px 9px;

            border-radius: 999px;

            font-size: 10px;
            font-weight: 750;

            white-space: nowrap;

            border: 1px solid transparent;
        }

        .personel-evrak-status svg {
            width: 13px;
            height: 13px;
        }

        .personel-evrak-status.pending {
            background: #fff6e5;
            color: #a97212;
            border-color: #f5e3bc;
        }

        .personel-evrak-status.approved {
            background: #eaf8f0;
            color: #21804c;
            border-color: #d2efdf;
        }

        .personel-evrak-status.rejected {
            background: #fff0f0;
            color: #b64545;
            border-color: #f1d0d0;
        }


        /* =========================================================
           RED NEDENİ
        ========================================================= */

        .personel-red-reason {
            margin-top: 5px;
            max-width: 160px;
            font-size: 10px;
            line-height: 1.3;
            color: #b64545;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }


        /* =========================================================
           EVRAK ADI (MAVİ VE ALTI ÇİZİLİ LİNK)
        ========================================================= */

        .evrak-name {
            display: flex;
            flex-direction: column;
            gap: 4px;
            min-width: 0;
        }

        .evrak-title-link {
            text-decoration: underline !important;
            text-underline-offset: 4px;
            text-decoration-thickness: 1.5px;
            color: #2563eb !important;
            display: inline-block;
            max-width: 100%;
            transition: all .2s ease;
        }

        .evrak-title-link strong {
            display: block;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
            font-size: 13px;
            font-weight: 700;
            color: #2563eb !important;
            cursor: pointer;
            transition: color .2s ease, transform .2s ease;
        }

        .evrak-title-link:hover {
            text-decoration-thickness: 2.5px;
            text-decoration-color: #1e40af !important;
        }

        .evrak-title-link:hover strong {
            color: #1e40af !important;
            transform: translateX(1px);
        }

        body.dark-mode .evrak-title-link,
        body.dark-mode .evrak-title-link strong {
            color: #60a5fa !important;
        }

        body.dark-mode .evrak-title-link:hover {
            text-decoration-color: #93c5fd !important;
        }

        body.dark-mode .evrak-title-link:hover strong {
            color: #93c5fd !important;
        }

        .evrak-description {
            display: block;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
            color: var(--text-soft);
            font-size: 11px;
        }


        /* =========================================================
           İŞLEM ALANI & DOSYALAR
        ========================================================= */

        .evrak-actions {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: nowrap;
        }

        .evrak-action {
            position: relative;
            width: 32px;
            height: 32px;
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            border: 1px solid transparent;
            border-radius: 8px;
            text-decoration: none;
            cursor: pointer;
            transition: all .18s ease;
        }

        .evrak-action svg {
            width: 14px;
            height: 14px;
            stroke-width: 2.2;
        }

        .evrak-action:hover {
            transform: translateY(-2px);
        }

        .evrak-action.view {
            background: #eef4ff;
            color: #3975e8;
            border-color: #d8e5ff;
            box-shadow: 0 2px 6px rgba(57, 117, 232, .08);
        }

        .evrak-action.view:hover {
            background: #e2ecff;
            border-color: #c4d7ff;
            color: #2865d9;
        }

        .evrak-files-vertical {
            display: flex;
            flex-direction: column;
            gap: 5px;
            align-items: flex-start;
            flex-shrink: 0;
            max-width: 100%;
        }

        .evrak-file-row {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #f4f7fb;
            padding: 3px 7px;
            border-radius: 7px;
            border: 1px solid #e3e8ef;
            flex-shrink: 0;
            max-width: 100%;
        }

        .evrak-file-name-label {
            display: inline-block;
            max-width: 120px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 11px;
            font-weight: 600;
            color: #4a5568;
            text-decoration: none;
            line-height: 1.2;
        }

        .evrak-file-name-label:hover {
            color: #3975e8;
        }

        .evrak-no-file {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            min-height: 30px;
            padding: 0 9px;
            border-radius: 8px;
            background: #f5f7fa;
            color: #7b8798;
            border: 1px solid #e3e8ef;
            font-size: 10px;
            font-weight: 650;
            flex-shrink: 0;
            white-space: nowrap;
        }

        .evrak-no-file svg {
            width: 13px;
            height: 13px;
            stroke-width: 2;
        }


        /* =========================================================
           DARK MODE
        ========================================================= */

        body.dark-mode .table-container {
            background: #0f1826;
            border-color: #2a394f;
        }

        body.dark-mode .data-table thead {
            background: #151f2f;
        }

        body.dark-mode .data-table th {
            background: #151f2f;
            color: #8f9eb2;
            border-color: #2a394f;
        }

        body.dark-mode .data-table td {
            color: #e5ebf3;
            border-color: #243247;
        }

        body.dark-mode .data-table tbody tr:hover {
            background: #162235;
        }

        body.dark-mode .user-dropdown {
            background: #121b2d;
            border-color: #26344a;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.28);
        }

        body.dark-mode .user-dropdown-item {
            color: #f0a2a2;
        }

        body.dark-mode .user-dropdown-item:hover {
            background: #321d20;
            color: #ffb0b0;
        }

        body.dark-mode .personel-evrak-status.pending {
            background: #382d16;
            color: #e3bf6b;
            border-color: #5a481f;
        }

        body.dark-mode .personel-evrak-status.approved {
            background: #143022;
            color: #7bd5a2;
            border-color: #23523b;
        }

        body.dark-mode .personel-evrak-status.rejected {
            background: #321d20;
            color: #f0a2a2;
            border-color: #563034;
        }

        body.dark-mode .personel-red-reason {
            color: #f0a2a2;
        }

        body.dark-mode .evrak-action.view {
            background: #172b4d;
            color: #82aaff;
            border-color: #29446e;
        }

        body.dark-mode .evrak-action.view:hover {
            background: #1c355c;
            color: #9abbff;
            border-color: #365883;
        }

        body.dark-mode .evrak-file-row {
            background: #152033;
            border-color: #24354d;
        }

        body.dark-mode .evrak-file-name-label {
            color: #cbd5e1;
        }

        body.dark-mode .evrak-file-name-label:hover {
            color: #82aaff;
        }

        body.dark-mode .evrak-no-file {
            background: #1a2434;
            color: #8997aa;
            border-color: #2d3a4d;
        }

    </style>

</head>


<body>

<div class="dashboard">


    <!-- =====================================================
         SIDEBAR
    ===================================================== -->

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
                class="sidebar-link active"
            >

                <i
                    data-lucide="file-text"
                    class="menu-icon"
                ></i>

                <span>
                    Evraklarım
                </span>

            </a>


            <!-- PROFİL -->

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


    <!-- =====================================================
         ANA İÇERİK
    ===================================================== -->

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
                    Evraklarım
                </span>

            </div>


            <div class="topbar-right">

                <?php require_once __DIR__ . "/../includes/duyuru-sistemi.php"; ?>

   <?php require_once __DIR__ . "/../personel-bildirimler.php"; ?>
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


        <!-- =====================================================
             SAYFA
        ===================================================== -->

        <section class="page-content">


            <div class="welcome-section">

                <h1>
                    Evraklarım
                </h1>

                <p>
                    Size ait kayıtlı evrakları buradan görüntüleyebilir, resmi çıktılarını alabilir veya PDF olarak indirebilirsiniz.
                </p>

            </div>


            <div class="large-card">


                <div class="card-title-area">

                    <div>

                        <h2>
                            Evrak Listem
                        </h2>

                        <p>

                            Toplam
                            <?= count($evraklar) ?>
                            evrak

                        </p>

                    </div>


                    <a
                        href="/BirimTakipSistemi/pages/personel-evrak-ekle.php"
                        class="save-button"
                    >

                        <i data-lucide="plus"></i>

                        Evrak Ekle

                    </a>

                </div>


                <?php if (count($evraklar) > 0): ?>


                    <div class="table-container">

                        <table class="data-table">


                            <thead>

                                <tr>

                                    <th>
                                        #
                                    </th>

                                    <th>
                                        Evrak
                                    </th>

                                    <th>
                                        Evrak No
                                    </th>

                                    <th>
                                        Açıklama
                                    </th>

                                    <th>
                                        Tarih
                                    </th>

                                    <th>
                                        Durum
                                    </th>

                                    <th>
                                        İşlemler
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                                <?php $sira = 1; ?>


                                <?php foreach (
                                    $evraklar as $evrak
                                ): ?>

                                    <tr>


                                        <!-- SIRA -->

                                        <td>

                                            <?= $sira ?>

                                        </td>


                                        <!-- EVRAK ADI (TIKLANABİLİR MAVİ LİNK) -->

                                        <td>

                                            <div class="evrak-name">

                                                <a 
                                                    href="evrak-detay.php?id=<?= (int)$evrak['id'] ?>" 
                                                    class="evrak-title-link"
                                                    title="Resmi Evrak Formunu Görüntüle ve Yazdır / PDF İndir"
                                                >
                                                    <strong>
                                                        <?= temizle($evrak["evrak_adi"]) ?>
                                                    </strong>
                                                </a>

                                                <?php if (!empty($evrak["aciklama"])): ?>

                                                    <span class="evrak-description">
                                                        <?= temizle($evrak["aciklama"]) ?>
                                                    </span>

                                                <?php endif; ?>

                                            </div>

                                        </td>


                                        <!-- EVRAK NO -->

                                        <td>

                                            <?php

                                            if (
                                                !empty(
                                                    $evrak["evrak_no"]
                                                )
                                            ) {

                                                echo htmlspecialchars(
                                                    $evrak["evrak_no"],
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                );

                                            } else {

                                                echo "-";

                                            }

                                            ?>

                                        </td>


                                        <!-- AÇIKLAMA -->

                                        <td>

                                            <?php

                                            if (
                                                !empty(
                                                    $evrak["aciklama"]
                                                )
                                            ) {

                                                echo htmlspecialchars(
                                                    $evrak["aciklama"],
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                );

                                            } else {

                                                echo "-";

                                            }

                                            ?>

                                        </td>


                                        <!-- TARİH -->

                                        <td>

                                            <?php

                                            if (
                                                !empty(
                                                    $evrak["tarih"]
                                                )
                                            ) {

                                                echo date(
                                                    "d.m.Y",
                                                    strtotime(
                                                        $evrak["tarih"]
                                                    )
                                                );

                                            } else {

                                                echo "-";

                                            }

                                            ?>

                                        </td>


                                        <!-- DURUM -->

                                        <td>

                                            <?php

                                            $durum =
                                                $evrak["durum"]
                                                ?? "beklemede";

                                            ?>


                                            <?php if (
                                                $durum === "onaylandi"
                                            ): ?>

                                                <span
                                                    class="
                                                        personel-evrak-status
                                                        approved
                                                    "
                                                >

                                                    <i
                                                        data-lucide="circle-check"
                                                    ></i>

                                                    Onaylandı

                                                </span>


                                            <?php elseif (
                                                $durum === "reddedildi"
                                            ): ?>

                                                <span
                                                    class="
                                                        personel-evrak-status
                                                        rejected
                                                    "
                                                >

                                                    <i
                                                        data-lucide="circle-x"
                                                    ></i>

                                                    Reddedildi

                                                </span>


                                                <?php if (
                                                    !empty(
                                                        $evrak["red_nedeni"]
                                                    )
                                                ): ?>

                                                    <div
                                                        class="personel-red-reason"
                                                        title="<?= htmlspecialchars($evrak['red_nedeni'], ENT_QUOTES, 'UTF-8') ?>"
                                                    >

                                                        <strong>
                                                            Neden:
                                                        </strong>

                                                        <?= htmlspecialchars(
                                                            $evrak["red_nedeni"],
                                                            ENT_QUOTES,
                                                            "UTF-8"
                                                        ) ?>

                                                    </div>

                                                <?php endif; ?>


                                            <?php else: ?>

                                                <span
                                                    class="
                                                        personel-evrak-status
                                                        pending
                                                    "
                                                >

                                                    <i
                                                        data-lucide="clock-3"
                                                    ></i>

                                                    Beklemede

                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <!-- İŞLEMLER (DOSYA GÖRÜNTÜLEME) -->

                                        <td>

                                            <div class="evrak-actions">

                                                <?php

                                                $dosyaStmt = $db->prepare("
                                                    SELECT
                                                        id,
                                                        dosya_adi,
                                                        dosya_yolu
                                                    FROM evrak_dosyalar
                                                    WHERE evrak_id = ?
                                                    ORDER BY id ASC
                                                ");

                                                $dosyaStmt->execute([
                                                    (int) $evrak["id"]
                                                ]);

                                                $evrakDosyalari =
                                                    $dosyaStmt->fetchAll(
                                                        PDO::FETCH_ASSOC
                                                    );

                                                ?>

                                                <?php if (!empty($evrakDosyalari)): ?>

                                                    <div class="evrak-files-vertical">

                                                        <?php foreach ($evrakDosyalari as $dosya): ?>

                                                            <div class="evrak-file-row">

                                                                <a
                                                                    href="evrak-goruntule.php?dosya_id=<?= (int) $dosya["id"] ?>"
                                                                    target="_blank"
                                                                    class="evrak-action view"
                                                                    title="<?= temizle($dosya["dosya_adi"]) ?>"
                                                                    aria-label="Dosyayı görüntüle"
                                                                    style="width: 26px; height: 26px;"
                                                                >
                                                                    <i data-lucide="eye" style="width: 13px; height: 13px;"></i>
                                                                </a>

                                                                <a
                                                                    href="evrak-goruntule.php?dosya_id=<?= (int) $dosya["id"] ?>"
                                                                    target="_blank"
                                                                    class="evrak-file-name-label"
                                                                    title="<?= temizle($dosya["dosya_adi"]) ?>"
                                                                >
                                                                    <?= temizle($dosya["dosya_adi"]) ?>
                                                                </a>

                                                            </div>

                                                        <?php endforeach; ?>

                                                    </div>

                                                <?php elseif (!empty($evrak["dosya_yolu"])): ?>

                                                    <!-- ESKİ TEKLİ DOSYA DESTEĞİ -->
                                                    <div class="evrak-files-vertical">
                                                        <div class="evrak-file-row">
                                                            <a
                                                                href="evrak-goruntule.php?id=<?= (int) $evrak["id"] ?>"
                                                                target="_blank"
                                                                class="evrak-action view"
                                                                title="Dosyayı Görüntüle"
                                                                aria-label="Dosyayı Görüntüle"
                                                                style="width: 26px; height: 26px;"
                                                            >
                                                                <i data-lucide="eye" style="width: 13px; height: 13px;"></i>
                                                            </a>

                                                            <a
                                                                href="evrak-goruntule.php?id=<?= (int) $evrak["id"] ?>"
                                                                target="_blank"
                                                                class="evrak-file-name-label"
                                                                title="<?= temizle(basename($evrak["dosya_yolu"])) ?>"
                                                            >
                                                                <?= temizle(basename($evrak["dosya_yolu"])) ?>
                                                            </a>
                                                        </div>
                                                    </div>

                                                <?php else: ?>

                                                    <span
                                                        class="evrak-no-file"
                                                        title="Bu evrak için dosya yüklenmemiş"
                                                    >
                                                        <i data-lucide="file-x"></i>
                                                        Dosya yok
                                                    </span>

                                                <?php endif; ?>

                                            </div>

                                        </td>

                                    </tr>


                                    <?php $sira++; ?>


                                <?php endforeach; ?>


                            </tbody>

                        </table>

                    </div>


                <?php else: ?>


                    <div class="empty-area">

                        <div class="empty-icon">

                            <i
                                data-lucide="file-text"
                            ></i>

                        </div>

                        <p>
                            Henüz size ait evrak bulunmuyor.
                        </p>

                    </div>


                <?php endif; ?>


            </div>


        </section>

    </main>

</div>


<!-- =====================================================
     JAVASCRIPT
===================================================== -->

<script src="../js/script.js"></script>

<script>
    lucide.createIcons();
</script>

</body>

</html>
