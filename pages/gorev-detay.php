<?php

session_start();

if (!isset($_SESSION["kullanici_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";


/* ==========================================
   GÖREV ID
========================================== */

$id = (int) ($_GET["id"] ?? 0);

if ($id <= 0) {
    header("Location: gorevler.php");
    exit;
}


/* ==========================================
   GÖREVİ GETİR
========================================== */

$stmt = $db->prepare("
    SELECT
        g.id,
        g.baslik,
        g.aciklama,
        g.personel_id,
        g.baslangic_tarihi,
        g.teslim_tarihi,
        g.durum,
        g.oncelik,
        p.ad,
        p.soyad
    FROM gorevler g
    LEFT JOIN personeller p
        ON g.personel_id = p.id
    WHERE g.id = ?
    LIMIT 1
");

$stmt->execute([$id]);

$gorev = $stmt->fetch(PDO::FETCH_ASSOC);


/* ==========================================
   GÖREV BULUNAMADI
========================================== */

if (!$gorev) {
    header("Location: gorevler.php");
    exit;
}


/* ==========================================
   DURUM SINIFI
========================================== */

$durumSiniflari = [
    "Bekliyor" => "bekliyor",
    "Devam Ediyor" => "devam-ediyor",
    "Tamamlandı" => "tamamlandi"
];

$durumSinif = $durumSiniflari[$gorev["durum"]] ?? "";


/* ==========================================
   ÖNCELİK SINIFI
========================================== */

$oncelikSiniflari = [
    "Düşük" => "dusuk",
    "Normal" => "normal",
    "Yüksek" => "yuksek"
];

$oncelikSinif = $oncelikSiniflari[$gorev["oncelik"]] ?? "";

/* ==========================================
   TESLİM TARİHİ DURUMU
========================================== */

$teslimDurumu = "";
$teslimSinif = "";

if (!empty($gorev["teslim_tarihi"]) && $gorev["durum"] !== "Tamamlandı") {
    $bugun = new DateTime();
    $teslimTarihi = new DateTime($gorev["teslim_tarihi"]);

    $bugun->setTime(0, 0, 0);
    $teslimTarihi->setTime(0, 0, 0);

    $gunFarki = (int) $bugun->diff($teslimTarihi)->format("%r%a");

    if ($gunFarki < 0) {
        $teslimDurumu = "Gecikmiş";
        $teslimSinif = "overdue";
    } elseif ($gunFarki <= 3) {
        $teslimDurumu = "Yaklaşıyor";
        $teslimSinif = "warning";
    } else {
        $teslimDurumu = "Normal";
        $teslimSinif = "normal";
    }
}
?>

<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Görev Detayı - Birim Takip</title>

    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="../css/style.css">

    <style>
        /* =====================================================
           GÖREV DETAY KARTI
        ===================================================== */

        .task-detail-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: 0 4px 18px rgba(25, 45, 80, 0.06);
            overflow: hidden;
        }

        /* =====================================================
           BAŞLIK ALANI & ÜST BUTONLAR
        ===================================================== */

        .task-detail-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 24px;
            border-bottom: 1px solid var(--border);
        }

        .task-detail-title {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .task-detail-icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            background: #eef2ff;
            color: #4d6fd8;
            flex-shrink: 0;
        }

        .task-detail-icon svg {
            width: 24px;
            height: 24px;
        }

        .task-detail-title h2 {
            margin: 0 0 5px;
            color: var(--text);
            font-size: 20px;
        }

        .task-detail-title p {
            margin: 0;
            color: var(--muted);
            font-size: 12px;
        }

   /* =====================================================
   EVRAK DETAY İLE BİREBİR YAZDIR BUTONU (KOD & RENK)
===================================================== */

.task-detail-print {
    height: 42px;
    padding: 0 18px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border: none;
    border-radius: 9px;
    background: #0f172a !important;
    color: #ffffff !important;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.15);
    transition: background-color 0.18s ease, transform 0.18s ease;
}

.task-detail-print:hover {
    background: #1e293b !important;
    transform: translateY(-1px);
}

.task-detail-print svg {
    width: 17px;
    height: 17px;
    stroke-width: 2.2;
}

body.dark-mode .task-detail-print {
    background: #1e293b !important;
    border: 1px solid #334155;
}

body.dark-mode .task-detail-print:hover {
    background: #334155 !important;
}

        /* =====================================================
           GÖVDE
        ===================================================== */

        .task-detail-body {
            padding: 24px;
        }

        .task-detail-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .task-detail-item {
            padding: 17px;
            background: var(--background);
            border: 1px solid var(--border);
            border-radius: 11px;
        }

        .task-detail-item.full {
            grid-column: 1 / -1;
        }

        .task-detail-label {
            margin-bottom: 7px;
            color: var(--muted);
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .task-detail-value {
            color: var(--text);
            font-size: 14px;
            font-weight: 600;
            line-height: 1.6;
        }

        .task-detail-description {
            min-height: 80px;
            white-space: pre-wrap;
            font-weight: 400;
        }

        /* =====================================================
           DURUM ROZETLERİ
        ===================================================== */

        .task-detail-status {
            display: inline-flex;
            align-items: center;
            padding: 7px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            border: 1px solid transparent;
        }

        .task-detail-status.bekliyor {
            background: #fff4d6 !important;
            color: #a96f00 !important;
            border-color: #f3d58a !important;
        }

        .task-detail-status.devam-ediyor {
            background: #e7efff !important;
            color: #315fbd !important;
            border-color: #b8ccfa !important;
        }

        .task-detail-status.tamamlandi {
            background: #e3f7eb !important;
            color: #21834b !important;
            border-color: #a9dfbf !important;
        }

        /* =====================================================
           ÖNCELİK ROZETLERİ
        ===================================================== */

        .task-detail-priority {
            display: inline-flex;
            align-items: center;
            padding: 7px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            border: 1px solid transparent;
        }

        .task-detail-priority.dusuk {
            background: #f1f3f5 !important;
            color: #667085 !important;
            border-color: #d9dee5 !important;
        }

        .task-detail-priority.normal {
            background: #e8edff !important;
            color: #4d6fd8 !important;
            border-color: #c4cef5 !important;
        }

        .task-detail-priority.yuksek {
            background: #ffe8e8 !important;
            color: #c63d3d !important;
            border-color: #f2b5b5 !important;
        }

        /* =====================================================
           ALT BUTONLAR
        ===================================================== */

        .task-detail-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 20px 24px;
            border-top: 1px solid var(--border);
        }

        .task-detail-back,
        .task-detail-edit {
            height: 40px;
            padding: 0 15px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 700;
        }

        .task-detail-back {
            border: 1px solid var(--border);
            background: var(--white);
            color: var(--text);
        }

        .task-detail-edit {
            background: #4d6fd8;
            color: #ffffff;
        }

        .task-detail-back svg,
        .task-detail-edit svg {
            width: 16px;
            height: 16px;
        }

        /* =====================================================
           TESLİM TARİHİ DURUMU
        ===================================================== */

        .task-detail-delivery {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .task-detail-delivery-status {
            font-size: 11px;
            font-weight: 700;
        }

        .task-detail-delivery-status.overdue {
            color: #dc2626;
        }

        .task-detail-delivery-status.warning {
            color: #d97706;
        }

        .task-detail-delivery-status.normal {
            color: #16a34a;
        }

        /* =====================================================
           DARK MODE
        ===================================================== */

        body.dark-mode .task-detail-card {
            background: #121b2d;
            border-color: #26344a;
        }

        body.dark-mode .task-detail-icon {
            background: #1b2940;
            color: #8ca7ff;
        }

        body.dark-mode .task-detail-item {
            background: #0f1826;
            border-color: #2a394f;
        }

        body.dark-mode .task-detail-back {
            background: #121b2d;
            border-color: #26344a;
            color: #e5ebf3;
        }

        body.dark-mode .task-detail-status.bekliyor {
            background: #3d3018 !important;
            color: #f3c969 !important;
            border-color: #665124 !important;
        }

        body.dark-mode .task-detail-status.devam-ediyor {
            background: #1d3158 !important;
            color: #9db8ff !important;
            border-color: #304c80 !important;
        }

        body.dark-mode .task-detail-status.tamamlandi {
            background: #183a29 !important;
            color: #7bd9a4 !important;
            border-color: #2c6044 !important;
        }

        body.dark-mode .task-detail-priority.dusuk {
            background: #202936 !important;
            color: #aeb8c6 !important;
            border-color: #344050 !important;
        }

        body.dark-mode .task-detail-priority.normal {
            background: #1d3158 !important;
            color: #9db8ff !important;
            border-color: #304c80 !important;
        }

        body.dark-mode .task-detail-priority.yuksek {
            background: #43262a !important;
            color: #ff9b9b !important;
            border-color: #6d383d !important;
        }

        body.dark-mode .task-detail-delivery-status.overdue {
            color: #f87171;
        }

        body.dark-mode .task-detail-delivery-status.warning {
            color: #fbbf24;
        }

        body.dark-mode .task-detail-delivery-status.normal {
            color: #4ade80;
        }

        /* =====================================================
           YAZDIRMA
        ===================================================== */

        @media print {
            body {
                background: #ffffff !important;
            }

            .sidebar,
            .topbar,
            .welcome-section,
            .task-detail-actions,
            .task-detail-print {
                display: none !important;
            }

            .main-content {
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .page-content {
                padding: 0 !important;
            }

            .task-detail-card {
                border: 1px solid #ddd !important;
                box-shadow: none !important;
            }

            .task-detail-item {
                break-inside: avoid;
            }
        }

        /* =====================================================
           MOBİL
        ===================================================== */

        @media (max-width: 700px) {
            .task-detail-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .task-detail-print {
                width: 100%;
            }

            .task-detail-grid {
                grid-template-columns: 1fr;
            }

            .task-detail-item.full {
                grid-column: auto;
            }

            .task-detail-actions {
                flex-direction: column;
            }

            .task-detail-back,
            .task-detail-edit {
                width: 100%;
            }
        }
        
    </style>
</head>

<body>

<div class="dashboard">

    <!-- ================= SIDEBAR ================= -->
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-logo">BT</div>
            <div>
                <h2>BİRİM TAKİP</h2>
                <span>Yönetim Sistemi</span>
            </div>
        </div>

        <nav class="sidebar-menu">
            <a href="../dashboard.php" class="sidebar-link">
                <i data-lucide="house" class="menu-icon"></i>
                <span>Ana Sayfa</span>
            </a>

            <a href="birimler.php" class="sidebar-link">
                <i data-lucide="building-2" class="menu-icon"></i>
                <span>Birimler</span>
            </a>

            <a href="personeller.php" class="sidebar-link">
                <i data-lucide="users" class="menu-icon"></i>
                <span>Personeller</span>
            </a>

            <a href="gorevler.php" class="sidebar-link active">
                <i data-lucide="clipboard-list" class="menu-icon"></i>
                <span>Görevler</span>
            </a>

            <a href="evraklar.php" class="sidebar-link">
                <i data-lucide="file-text" class="menu-icon"></i>
                <span>Evraklar</span>
            </a>
        </nav>

        <div class="sidebar-bottom">
            <a href="../logout.php" class="sidebar-link logout">
                <i data-lucide="log-out" class="menu-icon"></i>
                <span>Çıkış Yap</span>
            </a>
        </div>
    </aside>

    <!-- ================= ANA İÇERİK ================= -->
    <main class="main-content">

        <!-- ================= TOPBAR ================= -->
        <header class="topbar">
            <div class="topbar-left">
                <button type="button" class="menu-button">
                    <i data-lucide="menu"></i>
                </button>
                <span class="page-title">Görev Detayı</span>
            </div>

            <div class="topbar-right">

                <?php require_once __DIR__ . "/../includes/duyuru-sistemi.php"; ?>
                <div class="theme-switch">
                    <button type="button" class="theme-button active" id="lightTheme">
                        <i data-lucide="sun"></i>
                    </button>
                    <button type="button" class="theme-button" id="darkTheme">
                        <i data-lucide="moon"></i>
                    </button>
                </div>

                <div class="topbar-divider"></div>

                <div class="user-area">
                    <div class="user-avatar">
                        <?= strtoupper(substr($_SESSION["kullanici_adi"], 0, 1)) ?>
                    </div>
                    <div class="user-text">
                        <strong><?= htmlspecialchars($_SESSION["kullanici_adi"]) ?></strong>
                        <span><?= htmlspecialchars($_SESSION["rol"]) ?></span>
                    </div>
                    <i data-lucide="chevron-down" class="user-arrow"></i>
                </div>
            </div>
        </header>

        <!-- ================= SAYFA ================= -->
        <section class="page-content">

            <div class="welcome-section">
                <h1>Görev Detayı</h1>
                <p>Göreve ait tüm bilgileri görüntüleyin.</p>
            </div>

            <!-- GÖREV DETAY KARTI -->
            <div class="task-detail-card">

                <!-- BAŞLIK & YAZDIR BUTONU (EVRAKLAR DÜZENİ) -->
                <div class="task-detail-header">
                    <div class="task-detail-title">
                        <div class="task-detail-icon">
                            <i data-lucide="clipboard-list"></i>
                        </div>
                        <div>
                            <h2><?= htmlspecialchars($gorev["baslik"]) ?></h2>
                            <p>Görev #<?= (int) $gorev["id"] ?></p>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="task-detail-print"
                        onclick="window.print()"
                    >
                        <i data-lucide="printer"></i>
                        Resmi Yazdır / PDF İndir
                    </button>
                </div>

                <!-- GÖVDE -->
                <div class="task-detail-body">
                    <div class="task-detail-grid">

                        <!-- AÇIKLAMA -->
                        <div class="task-detail-item full">
                            <div class="task-detail-label">Açıklama</div>
                            <div class="task-detail-value task-detail-description">
                                <?= !empty($gorev["aciklama"]) ? nl2br(htmlspecialchars($gorev["aciklama"])) : "Açıklama girilmemiş." ?>
                            </div>
                        </div>

                        <!-- PERSONEL -->
                        <div class="task-detail-item">
                            <div class="task-detail-label">Personel</div>
                            <div class="task-detail-value">
                                <?php if (!empty($gorev["ad"]) && !empty($gorev["soyad"])): ?>
                                    <?= htmlspecialchars($gorev["ad"] . " " . $gorev["soyad"]) ?>
                                <?php else: ?>
                                    Atanmamış
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- DURUM -->
                        <div class="task-detail-item">
                            <div class="task-detail-label">Durum</div>
                            <div class="task-detail-value">
                                <span class="task-detail-status <?= $durumSinif ?>">
                                    <?= htmlspecialchars($gorev["durum"]) ?>
                                </span>
                            </div>
                        </div>

                        <!-- ÖNCELİK -->
                        <div class="task-detail-item">
                            <div class="task-detail-label">Öncelik</div>
                            <div class="task-detail-value">
                                <span class="task-detail-priority <?= $oncelikSinif ?>">
                                    <?= htmlspecialchars($gorev["oncelik"]) ?>
                                </span>
                            </div>
                        </div>

                        <!-- BAŞLANGIÇ TARİHİ -->
                        <div class="task-detail-item">
                            <div class="task-detail-label">Başlangıç Tarihi</div>
                            <div class="task-detail-value">
                                <?= !empty($gorev["baslangic_tarihi"]) ? date("d.m.Y", strtotime($gorev["baslangic_tarihi"])) : "-" ?>
                            </div>
                        </div>

                        <!-- TESLİM TARİHİ -->
                        <div class="task-detail-item">
                            <div class="task-detail-label">Teslim Tarihi</div>
                            <div class="task-detail-value">
                                <?php if (!empty($gorev["teslim_tarihi"])): ?>
                                    <div class="task-detail-delivery">
                                        <span>
                                            <?= date("d.m.Y", strtotime($gorev["teslim_tarihi"])) ?>
                                        </span>
                                        <?php if ($gorev["durum"] !== "Tamamlandı" && $teslimDurumu !== ""): ?>
                                            <span class="task-detail-delivery-status <?= $teslimSinif ?>">
                                                (<?= $teslimDurumu ?>)
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- ALT BUTONLAR -->
                <div class="task-detail-actions">
                    <a href="gorevler.php" class="task-detail-back">
                        <i data-lucide="arrow-left"></i>
                        Görevlere Dön
                    </a>

                    <a href="gorev-duzenle.php?id=<?= (int) $gorev["id"] ?>" class="task-detail-edit">
                        <i data-lucide="pencil"></i>
                        Görevi Düzenle
                    </a>
                </div>

            </div>

        </section>

    </main>

</div>

<script src="../js/script.js"></script>

<script>
    lucide.createIcons();
</script>

</body>

</html>
