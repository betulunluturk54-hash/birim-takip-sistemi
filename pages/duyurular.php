<?php

session_start();

if (!isset($_SESSION["kullanici_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";


/* ==========================================
   YÖNETİCİ KONTROLÜ
========================================== */

$duyuru_yonetebilir = in_array(
    $_SESSION["rol"],
    ["yonetici", "admin"]
);

// EKLENEN KOD: URL'de ?gorunum=liste varsa formu gizler
$gorunum_liste_mi = (isset($_GET["gorunum"]) && $_GET["gorunum"] === "liste");
$form_goster = $duyuru_yonetebilir && !$gorunum_liste_mi;


/* ==========================================
   MESAJLAR
========================================== */

$mesaj = "";
$mesaj_turu = "";


/* ==========================================
   DUYURU EKLE
========================================== */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    $duyuru_yonetebilir &&
    isset($_POST["duyuru_ekle"])
) {

    $baslik = trim($_POST["baslik"] ?? "");
    $icerik = trim($_POST["icerik"] ?? "");
    $oncelik = $_POST["oncelik"] ?? "normal";

    $son_tarihi = !empty($_POST["son_tarihi"])
        ? $_POST["son_tarihi"]
        : null;


    if ($baslik === "" || $icerik === "") {

        $mesaj = "Duyuru başlığı ve içeriği boş bırakılamaz.";
        $mesaj_turu = "error";

    } else {

        try {

            $sql = "
                INSERT INTO duyurular
                (
                    baslik,
                    icerik,
                    son_tarihi,
                    oncelik,
                    aktif
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    1
                )
            ";

            $stmt = $db->prepare($sql);

            $stmt->execute([
                $baslik,
                $icerik,
                $son_tarihi,
                $oncelik
            ]);

            $mesaj = "Duyuru başarıyla yayınlandı.";
            $mesaj_turu = "success";

        } catch (PDOException $e) {

            $mesaj = "Duyuru oluşturulurken bir hata oluştu.";
            $mesaj_turu = "error";
        }
    }
}


/* ==========================================
   DUYURULARI GETİR
========================================== */

$sql = "
    SELECT
        id,
        baslik,
        icerik,
        yayin_tarihi,
        son_tarihi,
        oncelik,
        aktif
    FROM duyurular
    ORDER BY id DESC
";

$stmt = $db->query($sql);

$duyurular = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="tr">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        <?= $form_goster ? "Duyuru Ekle" : "Duyurular" ?> - Birim Takip Sistemi
    </title>

    <script src="https://unpkg.com/lucide@latest"></script>

    <link
        rel="stylesheet"
        href="../css/style.css">


    <style>

        /* =====================================================
            DUYURU SAYFASI
        ===================================================== */

        .announcement-page {
            width: 100%;
            max-width: 1100px;
            margin: 0 auto;
        }


        /* =====================================================
            SAYFA BAŞLIĞI
        ===================================================== */

        .announcement-page-header {

            display: flex;
            align-items: center;
            justify-content: space-between;

            margin-bottom: 24px;
        }

        .announcement-page-header h1 {

            margin: 0;

            font-size: 26px;
            font-weight: 700;

            color: var(--text);
        }

        .announcement-page-header p {

            margin: 6px 0 0;

            font-size: 13px;

            color: var(--muted);
        }


        /* =====================================================
            MESAJ
        ===================================================== */

        .announcement-message {

            margin-bottom: 20px;

            padding: 12px 14px;

            border-radius: 9px;

            font-size: 13px;
            font-weight: 600;
        }

        .announcement-message.success {

            background: #edf8f0;

            border: 1px solid #c9e7d0;

            color: #28733a;
        }

        .announcement-message.error {

            background: #fff1f1;

            border: 1px solid #efcccc;

            color: #a73535;
        }


        /* =====================================================
            FORM KARTI
        ===================================================== */

        .announcement-form-card {

            background: var(--white);

            border: 1px solid var(--border);

            border-radius: 14px;

            padding: 22px;

            margin-bottom: 24px;

            box-shadow:
                0 6px 18px rgba(25, 45, 80, 0.06);
        }

        .announcement-form-title {

            margin-bottom: 20px;
        }

        .announcement-form-title h2 {

            margin: 0;

            font-size: 17px;

            color: var(--text);
        }

        .announcement-form-title p {

            margin: 5px 0 0;

            font-size: 12px;

            color: var(--muted);
        }


        /* =====================================================
            FORM
        ===================================================== */

        .announcement-form {

            display: flex;

            flex-direction: column;

            gap: 17px;
        }


        .announcement-form-row {

            display: grid;

            grid-template-columns:
                minmax(0, 1fr)
                240px;

            gap: 16px;
        }


        .announcement-form-group {

            display: flex;

            flex-direction: column;

            gap: 7px;
        }


        .announcement-form-group label {

            font-size: 12px;

            font-weight: 700;

            color: var(--text);
        }


        .announcement-form-group input,
        .announcement-form-group textarea,
        .announcement-form-group select {

            width: 100%;

            box-sizing: border-box;

            padding: 11px 12px;

            border: 1px solid var(--border);

            border-radius: 9px;

            background: var(--white);

            color: var(--text);

            font-family: inherit;

            font-size: 13px;

            outline: none;

            transition:
                border-color 0.2s ease,
                box-shadow 0.2s ease;
        }


        .announcement-form-group textarea {

            min-height: 140px;

            resize: vertical;

            line-height: 1.5;
        }


        .announcement-form-group input:focus,
        .announcement-form-group textarea:focus,
        .announcement-form-group select:focus {

            border-color: #647bd3;

            box-shadow:
                0 0 0 3px rgba(100, 123, 211, 0.10);
        }


        /* =====================================================
            BUTON
        ===================================================== */

        .announcement-form-actions {

            display: flex;

            justify-content: flex-end;

            margin-top: 2px;
        }


        .announcement-submit {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 8px;

            border: none;

            border-radius: 9px;

            padding: 11px 17px;

            background: #4d6fd8;

            color: #ffffff;

            font-size: 13px;

            font-weight: 700;

            cursor: pointer;

            transition:
                background-color 0.2s ease,
                transform 0.15s ease;
        }


        .announcement-submit:hover {

            background: #405fbe;
        }


        .announcement-submit:active {

            transform: translateY(1px);
        }


        .announcement-submit svg {

            width: 17px;
            height: 17px;
        }


        /* =====================================================
            DUYURU LİSTESİ
        ===================================================== */

        .announcement-list-card {

            background: var(--white);

            border: 1px solid var(--border);

            border-radius: 14px;

            overflow: hidden;

            box-shadow:
                0 6px 18px rgba(25, 45, 80, 0.06);
        }


        .announcement-list-header {

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding: 17px 20px;

            border-bottom: 1px solid var(--border);
        }


        .announcement-list-header h2 {

            margin: 0;

            font-size: 16px;

            color: var(--text);
        }


        .announcement-list-count {

            font-size: 11px;

            color: var(--muted);
        }


        .announcement-item {

            padding: 19px 20px;

            border-bottom: 1px solid var(--border);
        }


        .announcement-item:last-child {

            border-bottom: none;
        }


        .announcement-item-top {

            display: flex;

            align-items: flex-start;

            justify-content: space-between;

            gap: 20px;
        }


        .announcement-item-title {

            display: flex;

            align-items: center;

            gap: 9px;

            min-width: 0;
        }


        .announcement-item-title svg {

            width: 18px;
            height: 18px;

            flex-shrink: 0;

            color: #4d6fd8;
        }


        .announcement-item-title h3 {

            margin: 0;

            font-size: 14px;

            font-weight: 700;

            color: var(--text);
        }


        .announcement-priority {

            flex-shrink: 0;

            padding: 4px 8px;

            border-radius: 20px;

            font-size: 10px;

            font-weight: 700;
        }


        .announcement-priority.normal {

            background: #edf2ff;

            color: #4d6fd8;
        }


        .announcement-priority.onemli {

            background: #fff4dd;

            color: #9b690b;
        }


        .announcement-priority.acil {

            background: #fff0f0;

            color: #b33f3f;
        }


        .announcement-item-content {

            margin-top: 10px;

            font-size: 12px;

            line-height: 1.55;

            white-space: pre-line;

            color: var(--text);
        }


        .announcement-item-meta {

            display: flex;

            align-items: center;

            flex-wrap: wrap;

            gap: 12px;

            margin-top: 10px;

            font-size: 10px;

            color: var(--muted);
        }


        .announcement-status {

            padding: 4px 8px;

            border-radius: 20px;

            font-size: 10px;

            font-weight: 700;
        }


        .announcement-status.active {

            background: #edf8f0;

            color: #28733a;
        }


        .announcement-status.passive {

            background: #f1f2f4;

            color: #707782;
        }


        /* =====================================================
            BOŞ DURUM
        ===================================================== */

        .announcement-empty {

            padding: 45px 20px;

            text-align: center;

            color: var(--muted);

            font-size: 12px;
        }


        .announcement-empty svg {

            display: block;

            margin: 0 auto 9px;
        }


        /* =====================================================
            DARK MODE
        ===================================================== */

        body.dark-mode .announcement-form-card,
        body.dark-mode .announcement-list-card {

            background: #121b2d;

            border-color: #26344a;

            box-shadow:
                0 8px 24px rgba(0, 0, 0, 0.20);
        }


        body.dark-mode
        .announcement-form-group input,
        body.dark-mode
        .announcement-form-group textarea,
        body.dark-mode
        .announcement-form-group select {

            background: #121b2d;

            border-color: #26344a;

            color: #ffffff;
        }


        body.dark-mode .announcement-item,
        body.dark-mode .announcement-list-header {

            border-color: #26344a;
        }


        body.dark-mode .announcement-message.success {

            background: #172c20;

            border-color: #285d3b;

            color: #a7dfb3;
        }


        body.dark-mode .announcement-message.error {

            background: #321d20;

            border-color: #633337;

            color: #ffb0b0;
        }


        /* =====================================================
            MOBİL
        ===================================================== */

        @media (max-width: 700px) {

            .announcement-form-row {

                grid-template-columns: 1fr;
            }


            .announcement-item-top {

                flex-direction: column;
            }


            .announcement-form-actions {

                justify-content: stretch;
            }


            .announcement-submit {

                width: 100%;
            }

        }
/* =====================================================
   GERİ BUTONU
===================================================== */

.announcement-page-title-area {
    display: flex;
    align-items: center;
    gap: 14px;
}

.announcement-back-button {
    display: inline-flex;

    align-items: center;
    justify-content: center;
    gap: 7px;

    padding: 10px 13px;

    border: 1px solid var(--border);
    border-radius: 9px;

    background: var(--white);
    color: var(--text);

    text-decoration: none;

    font-size: 13px;
    font-weight: 600;

    transition:
        background-color 0.2s ease,
        border-color 0.2s ease,
        color 0.2s ease;
}

.announcement-back-button:hover {
    background: #f6f8fb;
    border-color: #cfd7e5;
}

.announcement-back-button svg {
    width: 17px;
    height: 17px;
}

body.dark-mode .announcement-back-button {
    background: #121b2d;
    border-color: #26344a;
    color: #ffffff;
}

body.dark-mode .announcement-back-button:hover {
    background: #182338;
    border-color: #344765;
}
    </style>

</head>


<body>

<div class="dashboard">


    <!-- ==================================================
         SIDEBAR (DİNAMİK MENÜ)
    ================================================== -->

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

            <?php if ($duyuru_yonetebilir): ?>
                <!-- YÖNETİCİ MENÜSÜ -->
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
                <a href="evraklar.php" class="sidebar-link">
                    <i data-lucide="file-text" class="menu-icon"></i>
                    <span>Evraklar</span>
                </a>
            <?php else: ?>
                <!-- PERSONEL MENÜSÜ -->
                <a href="../personel-dashboard.php" class="sidebar-link">
                    <i data-lucide="house" class="menu-icon"></i>
                    <span>Ana Sayfa</span>
                </a>
                <a href="personel-gorevler.php" class="sidebar-link">
                    <i data-lucide="clipboard-list" class="menu-icon"></i>
                    <span>Görevlerim</span>
                </a>
                <a href="personel-evraklar.php" class="sidebar-link">
                    <i data-lucide="file-text" class="menu-icon"></i>
                    <span>Evraklarım</span>
                </a>
                <a href="personel-profil.php" class="sidebar-link">
                    <i data-lucide="user" class="menu-icon"></i>
                    <span>Profilim</span>
                </a>
            <?php endif; ?>

        </nav>


        <!-- ÇIKIŞ -->

        <div class="sidebar-bottom">

            <a
                href="../logout.php"
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


    <!-- ==================================================
         ANA İÇERİK
    ================================================== -->

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
                    <?= $form_goster ? "Duyuru Ekle" : "Duyurular" ?>
                </span>


            </div>


            <div class="topbar-right">

                <!-- TEMA -->

                <div class="theme-switch">

                    <button
                        type="button"
                        class="theme-button active"
                        id="lightTheme">

                        <i data-lucide="sun"></i>

                    </button>


                    <button
                        type="button"
                        class="theme-button"
                        id="darkTheme">

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

                            <?= strtoupper(
                                mb_substr(
                                    $_SESSION["kullanici_adi"],
                                    0,
                                    1,
                                    "UTF-8"
                                )
                            ) ?>

                        </div>


                        <div class="user-text">

                            <strong>

                                <?= htmlspecialchars(
                                    $_SESSION["kullanici_adi"]
                                ) ?>

                            </strong>


                            <span>

                                <?= htmlspecialchars(
                                    $_SESSION["rol"]
                                ) ?>

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
                            href="../logout.php"
                            class="user-dropdown-item">

                            <i data-lucide="log-out"></i>

                            <span>
                                Çıkış Yap
                            </span>

                        </a>


                    </div>


                </div>

            </div>

        </header>


        <!-- ==================================================
             SAYFA İÇERİĞİ
        ================================================== -->

        <section class="page-content">


            <div class="announcement-page">


                <!-- SAYFA BAŞLIĞI -->

                <div class="announcement-page-header">

                    <div class="announcement-page-title-area">

                        <a
                            href="<?= $duyuru_yonetebilir ? '../dashboard.php' : '../personel-dashboard.php' ?>"
                            class="announcement-back-button">

                            <i data-lucide="arrow-left"></i>

                            Geri

                        </a>


                        <div>

                            <h1>
                                <?= $form_goster
                                    ? "Duyuru Oluştur"
                                    : "Duyurular" ?>
                            </h1>

                            <p>
                                <?= $form_goster
                                    ? "Kurum genelinde yayınlanacak yeni duyuruyu oluşturun."
                                    : "Güncel ve arşivdeki tüm duyuruları görüntüleyin." ?>
                            </p>

                        </div>

                    </div>

                </div>


                <!-- MESAJ -->

                <?php if ($mesaj !== ""): ?>

                    <div
                        class="announcement-message <?= $mesaj_turu ?>">

                        <?= htmlspecialchars($mesaj) ?>

                    </div>

                <?php endif; ?>


                <!-- ==================================================
                     YENİ DUYURU
                ================================================== -->

                <?php if ($form_goster): ?>

                    <div class="announcement-form-card">


                    <div class="announcement-form-title">

                        <h2>
                            Yeni Duyuru
                        </h2>

                        <p>
                            Yayınladığınız duyuru tüm personel ve yöneticilerin ekranında görüntülenecektir.
                        </p>

                    </div>


                    <form
                        method="POST"
                        class="announcement-form">


                        <!-- BAŞLIK -->

                        <div class="announcement-form-group">

                            <label for="baslik">
                                Duyuru Başlığı
                            </label>

                            <input
                                type="text"
                                id="baslik"
                                name="baslik"
                                maxlength="255"
                                placeholder="Örneğin: Sistem Bakımı Hakkında"
                                required>

                        </div>


                        <!-- İÇERİK -->

                        <div class="announcement-form-group">

                            <label for="icerik">
                                Duyuru İçeriği
                            </label>

                            <textarea
                                id="icerik"
                                name="icerik"
                                placeholder="Duyuru içeriğini buraya yazın..."
                                required></textarea>

                        </div>


                        <!-- TARİH + ÖNCELİK -->

                        <div class="announcement-form-row">


                            <div class="announcement-form-group">

                                <label for="son_tarihi">
                                    Son Geçerlilik Tarihi
                                </label>

                                <input
                                    type="datetime-local"
                                    id="son_tarihi"
                                    name="son_tarihi">

                            </div>


                            <div class="announcement-form-group">

                                <label for="oncelik">
                                    Öncelik
                                </label>

                                <select
                                    id="oncelik"
                                    name="oncelik">

                                    <option value="normal">
                                        Normal
                                    </option>

                                    <option value="onemli">
                                        Önemli
                                    </option>

                                    <option value="acil">
                                        Acil
                                    </option>

                                </select>

                            </div>


                        </div>


                        <!-- BUTON -->

                        <div class="announcement-form-actions">

                            <button
                                type="submit"
                                name="duyuru_ekle"
                                class="announcement-submit">

                                <i data-lucide="megaphone"></i>

                                Duyuruyu Yayınla

                            </button>

                        </div>


                    </form>

                    </div>

                <?php endif; ?>


                <!-- ==================================================
                     YAYINLANAN DUYURULAR
                ================================================== -->

                <div class="announcement-list-card">


                    <div class="announcement-list-header">

                        <h2>
                            Tüm Duyurular ve Arşiv
                        </h2>

                        <span class="announcement-list-count">

                            <?= count($duyurular) ?> duyuru (eski duyurular dâhil)

                        </span>

                    </div>


                    <?php if (count($duyurular) > 0): ?>


                        <?php foreach ($duyurular as $duyuru): ?>


                            <div class="announcement-item">


                                <div class="announcement-item-top">


                                    <div class="announcement-item-title">

                                        <i data-lucide="megaphone"></i>

                                        <h3>

                                            <?= htmlspecialchars(
                                                $duyuru["baslik"]
                                            ) ?>

                                        </h3>

                                    </div>


                                    <?php

                                    $oncelik_metni = "Normal";

                                    if (
                                        $duyuru["oncelik"] === "onemli"
                                    ) {

                                        $oncelik_metni = "Önemli";

                                    } elseif (
                                        $duyuru["oncelik"] === "acil"
                                    ) {

                                        $oncelik_metni = "Acil";
                                    }

                                    ?>


                                    <span
                                        class="announcement-priority <?= htmlspecialchars(
                                            $duyuru["oncelik"]
                                        ) ?>">

                                        <?= $oncelik_metni ?>

                                    </span>


                                </div>


                                <div class="announcement-item-content">

                                    <?= htmlspecialchars(
                                        $duyuru["icerik"]
                                    ) ?>

                                </div>


                                <div class="announcement-item-meta">


                                    <span>

                                        Yayın:
                                        <?= date(
                                            "d.m.Y H:i",
                                            strtotime(
                                                $duyuru["yayin_tarihi"]
                                            )
                                        ) ?>

                                    </span>


                                    <span>

                                        <?php if (
                                            !empty($duyuru["son_tarihi"])
                                        ): ?>

                                            Son tarih:
                                            <?= date(
                                                "d.m.Y H:i",
                                                strtotime(
                                                    $duyuru["son_tarihi"]
                                                )
                                            ) ?>

                                        <?php else: ?>

                                            Süresiz

                                        <?php endif; ?>

                                    </span>


                                    <?php

                                    $duyuru_eski =
                                        !empty($duyuru["son_tarihi"]) &&
                                        strtotime($duyuru["son_tarihi"]) < time();

                                    ?>

                                    <?php if ((int) $duyuru["aktif"] !== 1): ?>

                                        <span
                                            class="announcement-status passive">

                                            Pasif

                                        </span>

                                    <?php elseif ($duyuru_eski): ?>

                                        <span
                                            class="announcement-status passive">

                                            Arşivde

                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="announcement-status active">

                                            Aktif

                                        </span>

                                    <?php endif; ?>


                                </div>


                            </div>


                        <?php endforeach; ?>


                    <?php else: ?>


                        <div class="announcement-empty">

                            <i
                                data-lucide="megaphone-off"
                                width="28"
                                height="28">
                            </i>

                            Henüz yayınlanmış bir duyuru bulunmuyor.

                        </div>


                    <?php endif; ?>


                </div>


            </div>


        </section>


    </main>

</div>


<!-- ==================================================
     JAVASCRIPT
================================================== -->

<script src="../js/script.js"></script>


<script>

    lucide.createIcons();


    /* =====================================================
        KULLANICI MENÜSÜ
    ===================================================== */

    const userArea =
        document.getElementById("userArea");

    const userDropdown =
        document.getElementById("userDropdown");


    userArea.addEventListener(
        "click",
        function (e) {

            e.stopPropagation();

            userDropdown.classList.toggle("show");

        }
    );


    userDropdown.addEventListener(
        "click",
        function (e) {

            e.stopPropagation();

        }
    );


    document.addEventListener(
        "click",
        function () {

            userDropdown.classList.remove("show");

        }
    );

</script>


</body>
</html>