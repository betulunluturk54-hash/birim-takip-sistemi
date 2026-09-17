<?php

session_start();

if (!isset($_SESSION["kullanici_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";


/* ==========================================
   GİRİŞ YAPAN KULLANICI
========================================== */

$kullaniciStmt = $db->prepare("
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

$kullaniciStmt->execute([
    $_SESSION["kullanici_id"]
]);

$giris_yapan_kullanici = $kullaniciStmt->fetch(PDO::FETCH_ASSOC);

if (!$giris_yapan_kullanici) {
    session_destroy();
    header("Location: ../login.php");
    exit;
}


/* ==========================================
   AD SOYAD
========================================== */

$kullanici_ad_soyad = trim(
    ($giris_yapan_kullanici["ad"] ?? "") .
    " " .
    ($giris_yapan_kullanici["soyad"] ?? "")
);


/* Ad soyad boşsa kullanıcı adını göster */

if ($kullanici_ad_soyad === "") {

    $kullanici_ad_soyad =
        $giris_yapan_kullanici["kullanici_adi"];

}


/* ==========================================
   ROL ETİKETİ
========================================== */

if (
    in_array(
        $giris_yapan_kullanici["rol"],
        ["yonetici", "admin"],
        true
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
   GÖREV DURUMU GÜNCELLEME
========================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["durum_guncelle"])) {

    $gorev_id = (int) ($_POST["gorev_id"] ?? 0);
    $yeni_durum = trim($_POST["yeni_durum"] ?? "");

    $gecerli_durumlar = [
        "Bekliyor",
        "Devam Ediyor",
        "Tamamlandı"
    ];

    if (
        $gorev_id > 0 &&
        in_array($yeni_durum, $gecerli_durumlar, true)
    ) {
        $durumStmt = $db->prepare("
            UPDATE gorevler
            SET durum = ?
            WHERE id = ?
        ");

        $durumStmt->execute([
            $yeni_durum,
            $gorev_id
        ]);
    }

    $geriUrl = "gorevler.php";

    if (!empty($_GET)) {
        $geriUrl .= "?" . http_build_query($_GET);
    }

    header("Location: " . $geriUrl);
    exit;
}


/* ==========================================
   GELİŞMİŞ GÖREV FİLTRELEME
========================================== */

$arama = trim($_GET["arama"] ?? "");

$personel_filtre = (int) ($_GET["personel_id"] ?? 0);

$durum_filtre = trim($_GET["durum"] ?? "");

$oncelik_filtre = trim($_GET["oncelik"] ?? "");

$baslangic_filtre = trim($_GET["baslangic"] ?? "");

$teslim_filtre = trim($_GET["teslim"] ?? "");


/* ==========================================
   PERSONELLERİ GETİR
========================================== */

$personelStmt = $db->prepare("
    SELECT
        id,
        ad,
        soyad
    FROM personeller
    ORDER BY ad ASC, soyad ASC
");

$personelStmt->execute();

$filtre_personeller = $personelStmt->fetchAll(PDO::FETCH_ASSOC);


/* ==========================================
   GÖREV SORGUSU
========================================== */

$sql = "
    SELECT
        g.id,
        g.baslik,
        g.aciklama,
        g.personel_id,
        g.baslangic_tarihi,
        g.teslim_tarihi,
        g.durum,
        g.oncelik,

        COALESCE(
            GROUP_CONCAT(
                DISTINCT CONCAT(p.ad, ' ', p.soyad)
                ORDER BY p.ad ASC, p.soyad ASC
                SEPARATOR '|'
            ),
            CONCAT(p_eski.ad, ' ', p_eski.soyad)
        ) AS personel_adlari

    FROM gorevler g

    LEFT JOIN gorev_personeller gp
        ON gp.gorev_id = g.id

    LEFT JOIN personeller p
        ON p.id = gp.personel_id

    LEFT JOIN personeller p_eski
        ON p_eski.id = g.personel_id
";

$where = [];
$params = [];


/* GÖREV ARAMA */

if ($arama !== "") {

    $where[] = "
        (
            /* GÖREV BAŞLIĞI */
            g.baslik LIKE ?

            /* AÇIKLAMA */
            OR g.aciklama LIKE ?

            /* PERSONEL ADI / SOYADI */
            OR CONCAT(
                COALESCE(p.ad, ''),
                ' ',
                COALESCE(p.soyad, '')
            ) LIKE ?

            /* ESKİ PERSONEL ATAMASI */
            OR CONCAT(
                COALESCE(p_eski.ad, ''),
                ' ',
                COALESCE(p_eski.soyad, '')
            ) LIKE ?

            /* BAŞLANGIÇ TARİHİ */
            OR DATE_FORMAT(
                g.baslangic_tarihi,
                '%Y-%m-%d'
            ) LIKE ?

            OR DATE_FORMAT(
                g.baslangic_tarihi,
                '%d.%m.%Y'
            ) LIKE ?

            /* TESLİM TARİHİ */
            OR DATE_FORMAT(
                g.teslim_tarihi,
                '%Y-%m-%d'
            ) LIKE ?

            OR DATE_FORMAT(
                g.teslim_tarihi,
                '%d.%m.%Y'
            ) LIKE ?

            /* ÖNCELİK */
            OR g.oncelik LIKE ?
        )
    ";

    $arama_like = "%" . $arama . "%";

    $params[] = $arama_like;
    $params[] = $arama_like;
    $params[] = $arama_like;
    $params[] = $arama_like;
    $params[] = $arama_like;
    $params[] = $arama_like;
    $params[] = $arama_like;
    $params[] = $arama_like;
    $params[] = $arama_like;
}


/* PERSONEL */

if ($personel_filtre > 0) {

    $where[] = "
        (
            g.personel_id = ?
            OR EXISTS (
                SELECT 1
                FROM gorev_personeller gp_filtre
                WHERE gp_filtre.gorev_id = g.id
                AND gp_filtre.personel_id = ?
            )
        )
    ";

    $params[] = $personel_filtre;
    $params[] = $personel_filtre;
}


/* DURUM */

if ($durum_filtre !== "") {

    $where[] = "g.durum = ?";

    $params[] = $durum_filtre;
}


/* ÖNCELİK */

if ($oncelik_filtre !== "") {

    $where[] = "g.oncelik = ?";

    $params[] = $oncelik_filtre;
}


/* BAŞLANGIÇ TARİHİ */

if ($baslangic_filtre !== "") {

    $where[] = "g.baslangic_tarihi >= ?";

    $params[] = $baslangic_filtre;
}


/* TESLİM TARİHİ */

if ($teslim_filtre !== "") {

    $where[] = "g.teslim_tarihi <= ?";

    $params[] = $teslim_filtre;
}


/* WHERE */

if (!empty($where)) {

    $sql .= " WHERE " . implode(" AND ", $where);

}


$sql .= "
    GROUP BY
        g.id,
        g.baslik,
        g.aciklama,
        g.personel_id,
        g.baslangic_tarihi,
        g.teslim_tarihi,
        g.durum,
        g.oncelik,
        p_eski.ad,
        p_eski.soyad
";


/* SIRALAMA */

$sql .= " ORDER BY g.id DESC";


/* ==========================================
   SORGUYU ÇALIŞTIR
========================================== */

try {

    $stmt = $db->prepare($sql);

    $stmt->execute($params);

    $gorevler = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    die(
        "<h2>Veritabanı Hatası</h2>" .
        "<p>" .
        htmlspecialchars($e->getMessage()) .
        "</p>"
    );

}


/* ==========================================
   AKTİF FİLTRE VAR MI?
========================================== */

$aktif_filtre_sayisi = 0;

if ($personel_filtre > 0) {
    $aktif_filtre_sayisi++;
}

if ($durum_filtre !== "") {
    $aktif_filtre_sayisi++;
}

if ($oncelik_filtre !== "") {
    $aktif_filtre_sayisi++;
}

if ($baslangic_filtre !== "") {
    $aktif_filtre_sayisi++;
}

if ($teslim_filtre !== "") {
    $aktif_filtre_sayisi++;
}

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
        Görevler - Birim Takip
    </title>

    <script src="https://unpkg.com/lucide@latest"></script>

    <link
        rel="stylesheet"
        href="../css/style.css"
    >

    <style>

        /* =====================================================
           TESLİM TARİHİ DURUMU
        ===================================================== */

        .delivery-date {
            display: flex;
            flex-direction: column;
            gap: 4px;
            line-height: 1.2;
        }

        .delivery-status {
            font-size: 10px;
            font-weight: 700;
        }

        .delivery-status.overdue {
            color: #dc2626;
        }

        .delivery-status.warning {
            color: #d97706;
        }

        .delivery-status.normal {
            color: #16a34a;
        }

        body.dark-mode .delivery-status.overdue {
            color: #f87171;
        }

        body.dark-mode .delivery-status.warning {
            color: #fbbf24;
        }

        body.dark-mode .delivery-status.normal {
            color: #4ade80;
        }


        /* =====================================================
           GÖREV BAŞLIĞI LİNK STİLİ
        ===================================================== */

        .task-title-link {
            color: #3b82f6;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.18s ease;
        }

        .task-title-link:hover {
            color: #1d4ed8;
            text-decoration: underline;
        }

        body.dark-mode .task-title-link {
            color: #60a5fa;
        }

        body.dark-mode .task-title-link:hover {
            color: #93c5fd;
        }


        /* =====================================================
           GÖREV ARAMA / FİLTRELEME ALANI
        ===================================================== */

        .task-tools {
            margin-bottom: 22px;
        }

        .task-search-bar {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            padding: 7px;
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 13px;
            box-shadow: 0 3px 12px rgba(25, 45, 80, 0.05);
        }

        .task-search-wrapper {
            position: relative;
            flex: 1;
            min-width: 0;
        }

        .task-search-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            color: var(--muted);
            pointer-events: none;
        }

        .task-search-input {
            width: 100%;
            height: 44px;
            padding: 0 16px 0 43px;
            border: none;
            outline: none;
            background: transparent;
            color: var(--text);
            font-size: 13px;
        }

        .task-search-input::placeholder {
            color: var(--muted);
        }

        .task-search-input:focus {
            outline: none;
        }

        .task-search-button {
            min-width: 104px;
            height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            border: none;
            border-radius: 9px;
            background: #4d6fd8;
            color: #ffffff;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: background-color .18s ease, transform .18s ease;
        }

        .task-search-button:hover {
            background: #3f60c6;
            transform: translateY(-1px);
        }

        .task-filter-toggle {
            height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 0 14px;
            border: 1px solid var(--border);
            border-radius: 9px;
            background: var(--white);
            color: var(--text);
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: background-color .18s ease, border-color .18s ease;
        }

        .task-filter-toggle:hover {
            background: #f7f9fc;
            border-color: #cfd7e3;
        }

        .task-filter-toggle svg {
            width: 16px;
            height: 16px;
        }

        .task-filter-badge {
            min-width: 18px;
            height: 18px;
            padding: 0 5px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 20px;
            background: #4d6fd8;
            color: #ffffff;
            font-size: 10px;
            font-weight: 700;
        }


        /* =====================================================
           FİLTRE PANELİ
        ===================================================== */

        .task-filter-panel {
            display: none;
            margin-top: 10px;
            padding: 18px;
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 13px;
            box-shadow: 0 3px 12px rgba(25, 45, 80, 0.04);
        }

        .task-filter-panel.show {
            display: block;
        }

        .task-filter-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
        }

        .task-filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .task-filter-group label {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-soft);
        }

        .task-filter-group input,
        .task-filter-group select {
            width: 100%;
            height: 40px;
            padding: 0 11px;
            border: 1px solid var(--border);
            border-radius: 9px;
            background: var(--white);
            color: var(--text);
            font-size: 12px;
            outline: none;
        }

        .task-filter-group input:focus,
        .task-filter-group select:focus {
            border-color: #4d6fd8;
            box-shadow: 0 0 0 3px rgba(77, 111, 216, .08);
        }

        .task-filter-bottom {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--border);
        }

        .task-filter-reset {
            height: 38px;
            padding: 0 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--white);
            color: var(--text-soft);
            text-decoration: none;
            font-size: 12px;
            font-weight: 650;
        }

        .task-filter-reset:hover {
            background: #f7f9fc;
        }

        .task-filter-submit {
            height: 38px;
            padding: 0 15px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            border: none;
            border-radius: 8px;
            background: #4d6fd8;
            color: #ffffff;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }

        .task-filter-submit:hover {
            background: #3f60c6;
        }


        /* =====================================================
           SONUÇ BİLGİSİ
        ===================================================== */

        .task-result-info {
            margin-top: 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            font-size: 11px;
            color: var(--muted);
        }

        .task-result-info strong {
            color: var(--text);
            font-weight: 700;
        }


        /* =====================================================
           DARK MODE
        ===================================================== */

        body.dark-mode .task-search-bar {
            background: #121b2d;
            border-color: #26344a;
        }

        body.dark-mode .task-search-input {
            color: #e5ebf3;
        }

        body.dark-mode .task-filter-toggle {
            background: #121b2d;
            border-color: #26344a;
            color: #e5ebf3;
        }

        body.dark-mode .task-filter-toggle:hover {
            background: #182338;
            border-color: #344761;
        }

        body.dark-mode .task-filter-panel {
            background: #121b2d;
            border-color: #26344a;
        }

        body.dark-mode .task-filter-group input,
        body.dark-mode .task-filter-group select {
            background: #0f1826;
            border-color: #2a394f;
            color: #e5ebf3;
        }

        body.dark-mode .task-filter-bottom {
            border-color: #26344a;
        }

        body.dark-mode .task-filter-reset {
            background: #121b2d;
            border-color: #26344a;
            color: #cbd5e1;
        }

        body.dark-mode .task-filter-reset:hover {
            background: #182338;
        }


        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 1000px) {

            .task-filter-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

        }


        @media (max-width: 650px) {

            .task-search-bar {
                flex-wrap: wrap;
                padding: 8px;
            }

            .task-search-wrapper {
                flex-basis: 100%;
            }

            .task-search-button,
            .task-filter-toggle {
                flex: 1;
            }

            .task-filter-grid {
                grid-template-columns: 1fr;
            }

            .task-filter-bottom {
                flex-direction: column;
                align-items: stretch;
            }

            .task-filter-reset,
            .task-filter-submit {
                width: 100%;
            }

        }


        /* =====================================================
           GÖREV TABLOSU İKON & HİZALAMA DÜZELTMESİ
        ===================================================== */

        .table-name {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .table-icon {
            width: 36px;
            height: 36px;
            min-width: 36px;
            min-height: 36px;
            flex-shrink: 0;
            border-radius: 10px;
            background: #eef2ff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .table-icon svg,
        .table-icon i {
            width: 18px;
            height: 18px;
            color: #4d6fd8;
        }

        body.dark-mode .table-icon {
            background: #1e293b;
        }

        body.dark-mode .table-icon svg,
        body.dark-mode .table-icon i {
            color: #60a5fa;
        }


        /* =====================================================
           GÖREV DURUM SEÇİMİ
        ===================================================== */

        .task-status-form {
            margin: 0;
        }

        .task-status-select {
            min-width: 125px;
            height: 34px;
            padding: 0 28px 0 10px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--white);
            color: var(--text);
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            outline: none;
            transition: border-color .18s ease,
                        box-shadow .18s ease;
        }

        .task-status-select:focus {
            border-color: #4d6fd8;
            box-shadow: 0 0 0 3px rgba(77, 111, 216, .08);
        }

        .task-status-select:hover {
            border-color: #b8c3d4;
        }

        body.dark-mode .task-status-select {
            background: #121b2d;
            border-color: #2a394f;
            color: #e5ebf3;
        }

        body.dark-mode .task-status-select:focus {
            border-color: #4d6fd8;
            box-shadow: 0 0 0 3px rgba(77, 111, 216, .12);
        }


        /* =====================================================
           GÖREVE ATANAN PERSONELLER
        ===================================================== */

        .task-assignees {
            display: flex;
            flex-direction: column;
            gap: 5px;
            line-height: 1.3;
        }

        .task-assignee {
            display: block;
            color: var(--text);
            font-size: 12px;
            font-weight: 600;
        }

        body.dark-mode .task-assignee {
            color: #e5ebf3;
        }

    </style>

</head>


<body>

<div class="dashboard">


    <!-- ================= SIDEBAR ================= -->

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
                    Yönetim Sistemi
                </span>

            </div>

        </div>


        <nav class="sidebar-menu">


            <a
                href="../dashboard.php"
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
                href="birimler.php"
                class="sidebar-link"
            >

                <i
                    data-lucide="building-2"
                    class="menu-icon"
                ></i>

                <span>
                    Birimler
                </span>

            </a>


            <a
                href="personeller.php"
                class="sidebar-link"
            >

                <i
                    data-lucide="users"
                    class="menu-icon"
                ></i>

                <span>
                    Personeller
                </span>

            </a>


            <a
                href="gorevler.php"
                class="sidebar-link active"
            >

                <i
                    data-lucide="clipboard-list"
                    class="menu-icon"
                ></i>

                <span>
                    Görevler
                </span>

            </a>


            <a
                href="evraklar.php"
                class="sidebar-link"
            >

                <i
                    data-lucide="file-text"
                    class="menu-icon"
                ></i>

                <span>
                    Evraklar
                </span>

            </a>
<!-- PROFİL -->

<a
    href="../profil.php"
    class="sidebar-link"
>

    <i
        data-lucide="user-round"
        class="menu-icon"
    ></i>

    <span>
        Profilim
    </span>

</a>

        </nav>


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


    <!-- ================= ANA İÇERİK ================= -->

    <main class="main-content">


        <!-- ================= ÜST BAR ================= -->

        <header class="topbar">

            <div class="topbar-left">

                <button
                    type="button"
                    class="menu-button"
                >

                    <i data-lucide="menu"></i>

                </button>

                <span class="page-title">
                    Görevler
                </span>

            </div>


            <div class="topbar-right">


                <?php require_once __DIR__ . "/../includes/duyuru-sistemi.php"; ?>


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

                <div class="user-area">

                    <div class="user-avatar">

                        <?php

                        $ilk_harf =
                            mb_substr(
                                $giris_yapan_kullanici["ad"]
                                ?: $giris_yapan_kullanici["kullanici_adi"],
                                0,
                                1,
                                "UTF-8"
                            );

                        echo htmlspecialchars(
                            mb_strtoupper(
                                $ilk_harf,
                                "UTF-8"
                            )
                        );

                        ?>

                    </div>


                    <div class="user-text">

                        <strong>

                            <?= htmlspecialchars(
                                $kullanici_ad_soyad,
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>

                        </strong>


                        <span>

                            <?= htmlspecialchars(
                                $rol_etiketi,
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>

                        </span>

                    </div>


                    <i
                        data-lucide="chevron-down"
                        class="user-arrow"
                    ></i>

                </div>


            </div>

        </header>


        <!-- ================= SAYFA ================= -->

        <section class="page-content">


            <!-- BAŞLIK -->

            <div class="welcome-section">

                <h1>
                    Görevler
                </h1>

                <p>
                    Sistemdeki görevleri ve personel atamalarını yönetin.
                </p>

            </div>


            <!-- =================================================
                 ARAMA + FİLTRELEME
            ================================================= -->

            <div class="task-tools">

                <form
                    method="GET"
                    class="task-search-bar"
                >


                    <!-- ARAMA -->

                    <div class="task-search-wrapper">

                        <i
                            data-lucide="search"
                            class="task-search-icon"
                        ></i>

                        <input
                            type="text"
                            name="arama"
                            class="task-search-input"
                            value="<?= htmlspecialchars($arama) ?>"
                            placeholder="Görev başlığı veya açıklama ara..."
                        >

                    </div>


                    <!-- ARAMA BUTONU -->

                    <button
                        type="submit"
                        class="task-search-button"
                    >
                        Ara
                    </button>


                    <!-- FİLTRELER -->

                    <button
                        type="button"
                        class="task-filter-toggle"
                        id="taskFilterToggle"
                    >

                        <i data-lucide="sliders-horizontal"></i>

                        Filtreler

                        <?php if ($aktif_filtre_sayisi > 0): ?>

                            <span class="task-filter-badge">
                                <?= $aktif_filtre_sayisi ?>
                            </span>

                        <?php endif; ?>

                    </button>


                </form>


                <!-- =================================================
                     GELİŞMİŞ FİLTRE PANELİ
                ================================================= -->

                <form
                    method="GET"
                    class="task-filter-panel <?= $aktif_filtre_sayisi > 0 ? "show" : "" ?>"
                    id="taskFilterPanel"
                >

                    <input
                        type="hidden"
                        name="arama"
                        value="<?= htmlspecialchars($arama) ?>"
                    >


                    <div class="task-filter-grid">


                        <!-- PERSONEL -->

                        <div class="task-filter-group">

                            <label for="personel_id">
                                Personel
                            </label>

                            <select
                                id="personel_id"
                                name="personel_id"
                            >

                                <option value="">
                                    Tüm Personeller
                                </option>

                                <?php foreach ($filtre_personeller as $filtre_personel): ?>

                                    <option
                                        value="<?= (int) $filtre_personel["id"] ?>"
                                        <?= $personel_filtre === (int) $filtre_personel["id"] ? "selected" : "" ?>
                                    >

                                        <?= htmlspecialchars(
                                            $filtre_personel["ad"] .
                                            " " .
                                            $filtre_personel["soyad"]
                                        ) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <!-- DURUM -->

                        <div class="task-filter-group">

                            <label for="durum">
                                Durum
                            </label>

                            <select
                                id="durum"
                                name="durum"
                            >

                                <option value="">
                                    Tüm Durumlar
                                </option>

                                <option
                                    value="Bekliyor"
                                    <?= $durum_filtre === "Bekliyor" ? "selected" : "" ?>
                                >
                                    Bekliyor
                                </option>

                                <option
                                    value="Devam Ediyor"
                                    <?= $durum_filtre === "Devam Ediyor" ? "selected" : "" ?>
                                >
                                    Devam Ediyor
                                </option>

                                <option
                                    value="Tamamlandı"
                                    <?= $durum_filtre === "Tamamlandı" ? "selected" : "" ?>
                                >
                                    Tamamlandı
                                </option>

                            </select>

                        </div>


                        <!-- ÖNCELİK -->

                        <div class="task-filter-group">

                            <label for="oncelik">
                                Öncelik
                            </label>

                            <select
                                id="oncelik"
                                name="oncelik"
                            >

                                <option value="">
                                    Tüm Öncelikler
                                </option>

                                <option
                                    value="Düşük"
                                    <?= $oncelik_filtre === "Düşük" ? "selected" : "" ?>
                                >
                                    Düşük
                                </option>

                                <option
                                    value="Normal"
                                    <?= $oncelik_filtre === "Normal" ? "selected" : "" ?>
                                >
                                    Normal
                                </option>

                                <option
                                    value="Yüksek"
                                    <?= $oncelik_filtre === "Yüksek" ? "selected" : "" ?>
                                >
                                    Yüksek
                                </option>

                            </select>

                        </div>


                        <!-- BAŞLANGIÇ -->

                        <div class="task-filter-group">

                            <label for="baslangic">
                                Başlangıç Tarihi
                            </label>

                            <input
                                type="date"
                                id="baslangic"
                                name="baslangic"
                                value="<?= htmlspecialchars($baslangic_filtre) ?>"
                            >

                        </div>


                        <!-- TESLİM -->

                        <div class="task-filter-group">

                            <label for="teslim">
                                Teslim Tarihi
                            </label>

                            <input
                                type="date"
                                id="teslim"
                                name="teslim"
                                value="<?= htmlspecialchars($teslim_filtre) ?>"
                            >

                        </div>


                    </div>


                    <div class="task-filter-bottom">

                        <a
                            href="gorevler.php"
                            class="task-filter-reset"
                        >
                            Temizle
                        </a>


                        <button
                            type="submit"
                            class="task-filter-submit"
                        >

                            <i data-lucide="filter"></i>

                            Filtreleri Uygula

                        </button>

                    </div>


                </form>


                <!-- SONUÇ BİLGİSİ -->

                <div class="task-result-info">

                    <span>

                        <strong>
                            <?= count($gorevler) ?>
                        </strong>

                        görev listeleniyor

                    </span>


                    <?php if ($arama !== "" || $aktif_filtre_sayisi > 0): ?>

                        <span>
                            Filtrelenmiş sonuçlar
                        </span>

                    <?php else: ?>

                        <span>
                            Tüm görevler
                        </span>

                    <?php endif; ?>


                </div>


            </div>


            <!-- =================================================
                 GÖREV LİSTESİ
            ================================================= -->

            <div class="large-card">


                <div class="card-title-area">

                    <div>

                        <h2>
                            Görev Listesi
                        </h2>

                        <p>
                            Sistemde kayıtlı görevler
                        </p>

                    </div>


                    <a
                        href="gorev-ekle.php"
                        class="view-button"
                    >

                        <i data-lucide="plus"></i>

                        Yeni Görev

                    </a>

                </div>


                <div class="table-container">

                    <table class="data-table">


                        <thead>

                            <tr>

                                <th>#</th>

                                <th>Görev</th>

                                <th>Personel</th>

                                <th>Başlangıç</th>

                                <th>Teslim Tarihi</th>

                                <th>Durum</th>

                                <th>Öncelik</th>

                                <th>İşlemler</th>

                            </tr>

                        </thead>


                        <tbody>


                            <?php if (count($gorevler) > 0): ?>

                                <?php $sira = 1; ?>


                                <?php foreach ($gorevler as $gorev): ?>


                                    <tr>


                                        <!-- SIRA -->

                                        <td>
                                            <?= $sira ?>
                                        </td>


                                        <!-- GÖREV -->

                                        <td>

                                            <div class="table-name">

                                                <div class="table-icon">

                                                    <i data-lucide="clipboard-list"></i>

                                                </div>


                                                <a
                                                    href="gorev-detay.php?id=<?= (int) $gorev["id"] ?>"
                                                    class="task-title-link"
                                                >

                                                    <?= htmlspecialchars(
                                                        $gorev["baslik"]
                                                    ) ?>

                                                </a>

                                            </div>

                                        </td>


                                        <!-- PERSONELLER -->

                                        <td>

                                            <?php if (!empty($gorev["personel_adlari"])): ?>

                                                <?php

                                                $atananPersoneller =
                                                    explode(
                                                        "|",
                                                        $gorev["personel_adlari"]
                                                    );

                                                ?>


                                                <div class="task-assignees">


                                                    <?php foreach (
                                                        $atananPersoneller
                                                        as $atananPersonel
                                                    ): ?>

                                                        <span class="task-assignee">

                                                            <?= htmlspecialchars(
                                                                trim(
                                                                    $atananPersonel
                                                                )
                                                            ) ?>

                                                        </span>

                                                    <?php endforeach; ?>


                                                </div>


                                            <?php else: ?>

                                                <span>
                                                    Atanmamış
                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <!-- BAŞLANGIÇ -->

                                        <td>

                                            <?= $gorev["baslangic_tarihi"]
                                                ? date(
                                                    "d.m.Y",
                                                    strtotime(
                                                        $gorev["baslangic_tarihi"]
                                                    )
                                                )
                                                : "-" ?>

                                        </td>


                                        <!-- TESLİM -->

                                        <td>

                                            <?php if (!empty($gorev["teslim_tarihi"])): ?>


                                                <?php

                                                $teslimTarihi =
                                                    new DateTime(
                                                        $gorev["teslim_tarihi"]
                                                    );

                                                ?>


                                                <div class="delivery-date">

                                                    <span>

                                                        <?= $teslimTarihi->format(
                                                            "d.m.Y"
                                                        ) ?>

                                                    </span>


                                                    <?php if (
                                                        $gorev["durum"] !==
                                                        "Tamamlandı"
                                                    ): ?>


                                                        <?php

                                                        $bugun =
                                                            new DateTime();

                                                        $bugun->setTime(
                                                            0,
                                                            0,
                                                            0
                                                        );

                                                        $teslimTarihi->setTime(
                                                            0,
                                                            0,
                                                            0
                                                        );

                                                        $gunFarki =
                                                            (int)
                                                            $bugun
                                                                ->diff(
                                                                    $teslimTarihi
                                                                )
                                                                ->format(
                                                                    "%r%a"
                                                                );


                                                        if ($gunFarki < 0) {

                                                            $teslimDurumu =
                                                                "Gecikmiş";

                                                            $teslimClass =
                                                                "overdue";

                                                        } elseif (
                                                            $gunFarki <= 3
                                                        ) {

                                                            $teslimDurumu =
                                                                "Yaklaşıyor";

                                                            $teslimClass =
                                                                "warning";

                                                        } else {

                                                            $teslimDurumu =
                                                                "Normal";

                                                            $teslimClass =
                                                                "normal";

                                                        }

                                                        ?>


                                                        <span
                                                            class="delivery-status <?= $teslimClass ?>"
                                                        >

                                                            (<?= $teslimDurumu ?>)

                                                        </span>


                                                    <?php endif; ?>


                                                </div>


                                            <?php else: ?>

                                                <span>
                                                    -
                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <!-- DURUM -->

                                        <td>

                                            <form
                                                method="POST"
                                                class="task-status-form"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="gorev_id"
                                                    value="<?= (int) $gorev["id"] ?>"
                                                >


                                                <input
                                                    type="hidden"
                                                    name="durum_guncelle"
                                                    value="1"
                                                >


                                                <select
                                                    name="yeni_durum"
                                                    class="task-status-select"
                                                    onchange="this.form.submit()"
                                                >

                                                    <option
                                                        value="Bekliyor"
                                                        <?= $gorev["durum"] === "Bekliyor"
                                                            ? "selected"
                                                            : "" ?>
                                                    >
                                                        Bekliyor
                                                    </option>


                                                    <option
                                                        value="Devam Ediyor"
                                                        <?= $gorev["durum"] === "Devam Ediyor"
                                                            ? "selected"
                                                            : "" ?>
                                                    >
                                                        Devam Ediyor
                                                    </option>


                                                    <option
                                                        value="Tamamlandı"
                                                        <?= $gorev["durum"] === "Tamamlandı"
                                                            ? "selected"
                                                            : "" ?>
                                                    >
                                                        Tamamlandı
                                                    </option>

                                                </select>

                                            </form>

                                        </td>


                                        <!-- ÖNCELİK -->

                                        <td>

                                            <span class="task-priority">

                                                <?= htmlspecialchars(
                                                    $gorev["oncelik"]
                                                ) ?>

                                            </span>

                                        </td>


                                        <!-- İŞLEMLER -->

                                        <td>

                                            <div class="table-actions">


                                                <a
                                                    href="gorev-duzenle.php?id=<?= (int) $gorev["id"] ?>"
                                                    class="action-button edit"
                                                >

                                                    <i data-lucide="pencil"></i>

                                                    Düzenle

                                                </a>


                                                <a
                                                    href="gorev-sil.php?id=<?= (int) $gorev["id"] ?>"
                                                    class="action-button delete"
                                                    onclick="return confirm('Bu görevi silmek istediğinize emin misiniz?');"
                                                >

                                                    <i data-lucide="trash-2"></i>

                                                    Sil

                                                </a>


                                            </div>

                                        </td>


                                    </tr>


                                    <?php $sira++; ?>


                                <?php endforeach; ?>


                            <?php else: ?>


                                <tr>

                                    <td
                                        colspan="8"
                                        class="table-empty"
                                    >

                                        <i data-lucide="search-x"></i>

                                        <span>
                                            Arama veya filtreleme kriterlerine uygun görev bulunamadı.
                                        </span>

                                    </td>

                                </tr>


                            <?php endif; ?>


                        </tbody>

                    </table>

                </div>


            </div>


        </section>


    </main>


</div>


<script src="../js/script.js"></script>


<script>

    lucide.createIcons();


    /* GELİŞMİŞ FİLTRELERİ AÇ / KAPAT */

    const taskFilterToggle =
        document.getElementById(
            "taskFilterToggle"
        );

    const taskFilterPanel =
        document.getElementById(
            "taskFilterPanel"
        );


    if (
        taskFilterToggle &&
        taskFilterPanel
    ) {

        taskFilterToggle.addEventListener(
            "click",
            function () {

                taskFilterPanel.classList.toggle(
                    "show"
                );

            }
        );

    }

</script>


</body>

</html>