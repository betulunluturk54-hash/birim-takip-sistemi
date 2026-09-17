<?php

session_start();

if (!isset($_SESSION["kullanici_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";


/* =========================================================
   EVRAK ID
========================================================= */

$id = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);

if (!$id) {
    header("Location: evraklar.php");
    exit;
}


/* =========================================================
   YETKİ KONTROLÜ
========================================================= */

$yoneticiMi = in_array(
    $_SESSION["rol"] ?? "",
    ["yonetici", "admin"],
    true
);


/* =========================================================
   EVRAK BİLGİSİ
========================================================= */

if ($yoneticiMi) {

    $stmt = $db->prepare("
        SELECT
            e.id,
            e.evrak_adi,
            e.evrak_no,
            e.aciklama,
            e.tarih,
            e.personel_id,
            e.durum,
            e.red_nedeni,
            CONCAT(
                p.ad,
                ' ',
                p.soyad
            ) AS personel_adi
        FROM evraklar e
        LEFT JOIN personeller p
            ON e.personel_id = p.id
        WHERE e.id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $id
    ]);

} else {

    $stmt = $db->prepare("
        SELECT
            e.id,
            e.evrak_adi,
            e.evrak_no,
            e.aciklama,
            e.tarih,
            e.personel_id,
            e.durum,
            e.red_nedeni,
            CONCAT(
                p.ad,
                ' ',
                p.soyad
            ) AS personel_adi
        FROM evraklar e
        INNER JOIN personeller p
            ON e.personel_id = p.id
        WHERE e.id = ?
          AND p.kullanici_id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $id,
        $_SESSION["kullanici_id"]
    ]);
}


$evrak = $stmt->fetch(
    PDO::FETCH_ASSOC
);


if (!$evrak) {
    header("Location: evraklar.php");
    exit;
}


/* =========================================================
   DOSYALARI GETİR
========================================================= */

$stmt = $db->prepare("
    SELECT
        id,
        dosya_adi,
        dosya_yolu
    FROM evrak_dosyalar
    WHERE evrak_id = ?
    ORDER BY id ASC
");

$stmt->execute([
    $id
]);

$dosyalar = $stmt->fetchAll(
    PDO::FETCH_ASSOC
);


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
   DURUM BİLGİSİ
========================================================= */

$durum = $evrak["durum"] ?? "beklemede";

if ($durum === "onaylandi") {
    $durumSinif = "approved";
    $durumMetni = "ONAYLANDI";
} elseif ($durum === "reddedildi") {
    $durumSinif = "rejected";
    $durumMetni = "REDDEDİLDİ";
} elseif ($durum === "arsivlendi") {
    $durumSinif = "archived";
    $durumMetni = "ARŞİVLENDİ";
} else {
    $durumSinif = "pending";
    $durumMetni = "BEKLEMEDE";
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
        <?= temizle($evrak["evrak_adi"]) ?> - Resmi Evrak Formu
    </title>

    <script src="https://unpkg.com/lucide@latest"></script>

    <link
        rel="stylesheet"
        href="../css/style.css"
    >

    <style>

        /* =====================================================
           SAYFA GENELİ & AKSİYON PANELİ
        ===================================================== */

        .evrak-detail-page {
            width: 100%;
        }

        .evrak-detail-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 25px;
        }

        .evrak-detail-title {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .evrak-detail-icon {
            width: 50px;
            height: 50px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            background: #eaf1ff;
            color: #1a4fc7;
        }

        .evrak-detail-icon svg {
            width: 25px;
            height: 25px;
        }

        .evrak-detail-title h1 {
            margin: 0 0 4px;
            font-size: 24px;
            font-weight: 800;
            color: var(--text);
        }

        .evrak-detail-title p {
            margin: 0;
            color: var(--text-soft);
            font-size: 13px;
        }

        .evrak-detail-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .evrak-detail-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 42px;
            padding: 0 18px;
            border-radius: 9px;
            border: 1px solid var(--border);
            background: var(--white);
            color: var(--text);
            text-decoration: none;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: all .18s ease;
        }

        .evrak-detail-button.print-button {
             background: #0f172a !important;
            color: #ffffff;
            border-color: #1e3a8a;
        }

        .evrak-detail-button.print-button:hover {
            background: #172554;
            transform: translateY(-1px);
        }

        .evrak-detail-button:hover {
            background: #f8fafc;
            transform: translateY(-1px);
        }

        /* =====================================================
           YAZDIRMA SAYFASI İLE BİREBİR RESMİ EVRAK DÜZENİ
        ===================================================== */

        .resmi-belge-karti {
            background: #ffffff !important;
            border: 1px solid #111111;
            border-radius: 2px;
            padding: 50px 60px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
            max-width: 850px;
            margin: 0 auto 40px auto;
            color: #000000 !important;
            font-family: "Times New Roman", Times, Georgia, serif !important;
        }

        /* Antet Bölümü */
        .resmi-antet {
            text-align: center;
            border-bottom: 2.5px solid #000000;
            padding-bottom: 16px;
            margin-bottom: 22px;
        }

        .resmi-antet h3 {
            margin: 0 0 8px 0;
            font-size: 17px;
            letter-spacing: 2px;
            font-weight: 800;
            color: #000000 !important;
            text-transform: uppercase;
        }

        .resmi-antet h2 {
            margin: 0 0 10px 0;
            font-size: 26px;
            font-weight: 900;
            color: #000000 !important;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .resmi-antet-alt {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            font-weight: 700;
            color: #000000 !important;
            margin-top: 14px;
        }

        /* Resmi Tablo */
        .resmi-tablo {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
            background: #ffffff !important;
        }

        .resmi-tablo th,
        .resmi-tablo td {
            border: 1.5px solid #000000 !important;
            padding: 12px 14px;
            text-align: left;
            vertical-align: middle;
            color: #000000 !important;
        }

        .resmi-tablo th {
            background: #ffffff !important;
            width: 32%;
            font-size: 14px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .resmi-tablo td {
            font-size: 15px;
            font-weight: 600;
            background: #ffffff !important;
        }

        .resmi-aciklama-kutusu {
            font-size: 15px;
            line-height: 1.6;
            color: #000000 !important;
            white-space: pre-line;
            min-height: 90px;
            font-weight: 500;
        }

        /* Durum Metni */
        .evrak-detail-status {
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0.5px;
        }

        .evrak-detail-status.approved { color: #15803d; }
        .evrak-detail-status.rejected { color: #b91c1c; }
        .evrak-detail-status.pending { color: #b45309; }
        .evrak-detail-status.archived { color: #4b5563; }

        /* Ekli Dosyalar Başlığı */
        .resmi-dosyalar-baslik {
            font-size: 15px;
            font-weight: 800;
            text-transform: uppercase;
            color: #000000 !important;
            border-bottom: 1.5px solid #000000;
            padding-bottom: 5px;
            margin: 28px 0 14px 0;
            letter-spacing: 0.5px;
        }

        /* Dosya Kutuları */
        .evrak-detail-file {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 12px;
            border: 1.5px solid #000000;
            border-radius: 4px;
            background: #ffffff !important;
            margin-bottom: 8px;
        }

        .evrak-detail-file-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .evrak-detail-file-name {
            font-size: 14px;
            font-weight: 600;
            color: #000000 !important;
            font-family: Arial, Helvetica, sans-serif !important;
        }

        .evrak-detail-file-actions {
            display: flex;
            gap: 8px;
        }

        .evrak-detail-file-button {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            border: 1px solid #000000;
            background: #ffffff;
            color: #000000;
            font-family: Arial, Helvetica, sans-serif !important;
        }

        .evrak-detail-file-button:hover {
            background: #f1f5f9;
        }

        /* İmza Alanı */
        .resmi-imza-alani {
            display: flex;
            justify-content: space-between;
            margin-top: 55px;
            padding-top: 10px;
        }

        .imza-kutu {
            width: 42%;
            text-align: center;
        }

        .imza-unvan {
            font-size: 14px;
            font-weight: 800;
            color: #000000 !important;
            text-transform: uppercase;
            margin-bottom: 8px;
            letter-spacing: 0.5px;
        }

        .imza-isim {
            font-size: 15px;
            font-weight: 700;
            color: #000000 !important;
            margin-bottom: 40px;
        }

        .imza-cizgi {
            border-top: 1.5px solid #000000;
            width: 80%;
            margin: 0 auto 6px auto;
        }

        .imza-not {
            font-size: 12px;
            color: #4b5563;
            font-style: italic;
        }

        .resmi-belge-alt-bilgi {
            margin-top: 40px;
            border-top: 1.5px solid #000000;
            padding-top: 8px;
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #4b5563;
            font-family: Arial, Helvetica, sans-serif !important;
        }

        /* =====================================================
           YAZDIRMA (PRINT) ÖZELİ
        ===================================================== */

        @media print {
            @page {
                size: A4 portrait;
                margin: 15mm 20mm 20mm 20mm;
            }

            * {
                box-sizing: border-box !important;
            }

            html, body {
                width: 100% !important;
                background: #ffffff !important;
                color: #000000 !important;
                font-family: "Times New Roman", Times, serif !important;
            }

            .sidebar,
            .topbar,
            .evrak-detail-header,
            .evrak-detail-actions,
            .evrak-detail-file-actions {
                display: none !important;
            }

            .main-content,
            .page-content,
            .dashboard,
            .evrak-detail-page {
                padding: 0 !important;
                margin: 0 !important;
                max-width: 100% !important;
                width: 100% !important;
                background: #ffffff !important;
            }

            .resmi-belge-karti {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
                margin: 0 !important;
                max-width: 100% !important;
            }

            .resmi-imza-alani {
                page-break-inside: avoid !important;
            }
        }

    </style>

</head>


<body>

<div class="dashboard">

    <!-- SIDEBAR -->
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
            <a href="gorevler.php" class="sidebar-link">
                <i data-lucide="clipboard-list" class="menu-icon"></i>
                <span>Görevler</span>
            </a>
            <a href="evraklar.php" class="sidebar-link active">
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


    <!-- ANA İÇERİK -->
    <main class="main-content">

        <!-- TOPBAR -->
        <header class="topbar">
            <div class="topbar-left">
                <button type="button" class="menu-button">
                    <i data-lucide="menu"></i>
                </button>
                <span class="page-title">Evrak Detayı</span>
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
            </div>
        </header>


        <!-- SAYFA İÇERİĞİ -->
        <section class="page-content evrak-detail-page">

            <!-- ÜST AKSİYON BUTONLARI -->
            <div class="evrak-detail-header">
                <div class="evrak-detail-title">
                    <div class="evrak-detail-icon">
                        <i data-lucide="file-text"></i>
                    </div>
                    <div>
                        <h1><?= temizle($evrak["evrak_adi"]) ?></h1>
                        <p>Evrak kayıt özeti ve resmi çıktı belgesi.</p>
                    </div>
                </div>

                <div class="evrak-detail-actions">
                    <button type="button" class="evrak-detail-button print-button" onclick="window.print()">
                        <i data-lucide="printer"></i>
                        Resmi Yazdır / PDF İndir
                    </button>
                    <a href="evraklar.php" class="evrak-detail-button">
                        <i data-lucide="arrow-left"></i>
                        Geri Dön
                    </a>
                </div>
            </div>


            <!-- RESMİ EVRAK FORMATI -->
            <div class="resmi-belge-karti">

                <!-- Antet / Başlık -->
                <div class="resmi-antet">
                    <h3>T.C. BİRİM TAKİP SİSTEMİ</h3>
                    <h2><?= temizle($evrak["evrak_adi"]) ?></h2>
                    <div class="resmi-antet-alt">
                        <span><strong>EVRAK NO:</strong> <?= !empty($evrak["evrak_no"]) ? temizle($evrak["evrak_no"]) : "BELİRTİLMEDİ" ?></span>
                        <span><strong>TARİH:</strong> <?= !empty($evrak["tarih"]) ? date("d.m.Y", strtotime($evrak["tarih"])) : date("d.m.Y") ?></span>
                    </div>
                </div>

                <!-- Bilgi Tablosu -->
                <table class="resmi-tablo">
                    <tbody>
                        <tr>
                            <th>İLGİLİ PERSONEL</th>
                            <td><?= temizle($evrak["personel_adi"] ?? "-") ?></td>
                        </tr>
                        <tr>
                            <th>EVRAK NUMARASI</th>
                            <td><?= !empty($evrak["evrak_no"]) ? temizle($evrak["evrak_no"]) : "-" ?></td>
                        </tr>
                        <tr>
                            <th>EVRAK DÜZENLEME TARİHİ</th>
                            <td><?= !empty($evrak["tarih"]) ? date("d.m.Y", strtotime($evrak["tarih"])) : "-" ?></td>
                        </tr>
                        <tr>
                            <th>ONAY / GEÇERLİLİK DURUMU</th>
                            <td>
                                <span class="evrak-detail-status <?= $durumSinif ?>">
                                    ● <?= $durumMetni ?>
                                </span>
                            </td>
                        </tr>
                        <?php if ($durum === "reddedildi" && !empty($evrak["red_nedeni"])): ?>
                            <tr>
                                <th style="color: #b91c1c;">REDDETME GEREKÇESİ</th>
                                <td style="color: #b91c1c; font-weight: 700;"><?= temizle($evrak["red_nedeni"]) ?></td>
                            </tr>
                        <?php endif; ?>
                        <tr>
                            <th>AÇIKLAMA / BEYAN</th>
                            <td>
                                <div class="resmi-aciklama-kutusu">
                                    <?= !empty($evrak["aciklama"]) ? nl2br(temizle($evrak["aciklama"])) : "Bu evrak için ek açıklama bulunmamaktadır." ?>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Ekli Dosyalar -->
                <div class="resmi-dosyalar-baslik">EKLİ BELGELER VE DOSYALAR</div>
                <?php if (!empty($dosyalar)): ?>
                    <div class="evrak-detail-files">
                        <?php foreach ($dosyalar as $index => $dosya): ?>
                            <div class="evrak-detail-file">
                                <div class="evrak-detail-file-info">
                                    <strong style="font-size: 14px;"><?= $index + 1 ?>.</strong>
                                    <span class="evrak-detail-file-name"><?= temizle($dosya["dosya_adi"]) ?></span>
                                </div>
                                <div class="evrak-detail-file-actions">
                                    <a href="evrak-goruntule.php?dosya_id=<?= (int)$dosya["id"] ?>" target="_blank" class="evrak-detail-file-button">
                                        <i data-lucide="eye"></i> Görüntüle
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="font-size: 14px; color: #4b5563; font-style: italic; margin: 8px 0 0 0;">Bu evrak için sisteme yüklenmiş fiziki/dijital dosya eki bulunmamaktadır.</p>
                <?php endif; ?>

                <!-- Resmi İmza / Onay Alanı -->
                <div class="resmi-imza-alani">
                    <div class="imza-kutu">
                        <div class="imza-unvan">TALEBİ YAPAN / PERSONEL</div>
                        <div class="imza-isim"><?= temizle($evrak["personel_adi"] ?? "İlgili Personel") ?></div>
                        <div class="imza-cizgi"></div>
                        <div class="imza-not">İmza / Tarih</div>
                    </div>

                    <div class="imza-kutu">
                        <div class="imza-unvan">BİRİM YETKİLİSİ / ONAYLAYAN</div>
                        <div class="imza-isim">Yönetim Onayı</div>
                        <div class="imza-cizgi"></div>
                        <div class="imza-not">İmza / Mühür</div>
                    </div>
                </div>

                <!-- Resmi Belge Alt Bilgi -->
                <div class="resmi-belge-alt-bilgi">
                    <span>Sistem Belge Doğrulama Kodu: BT-<?= str_pad((string)$evrak["id"], 6, "0", STR_PAD_LEFT) ?></span>
                    <span>Yazdırma Tarihi / Saati: <?= date("d.m.Y H:i") ?></span>
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
