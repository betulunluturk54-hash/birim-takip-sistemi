<?php

session_start();

if (!isset($_SESSION["kullanici_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";

$mesaj = "";


/* ==========================================
   AKTİF PERSONELLERİ GETİR
========================================== */

$sql = "SELECT
            id,
            ad,
            soyad
        FROM personeller
        WHERE durum = 'aktif'
        ORDER BY ad ASC, soyad ASC";

$stmt = $db->prepare($sql);
$stmt->execute();

$personeller = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* ==========================================
   GÖREV EKLE
========================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $baslik = trim($_POST["baslik"] ?? "");
    $aciklama = trim($_POST["aciklama"] ?? "");

    $personel_ids = $_POST["personel_id"] ?? [];

    $baslangic_tarihi = $_POST["baslangic_tarihi"] ?? "";
    $teslim_tarihi = $_POST["teslim_tarihi"] ?? "";
    $durum = $_POST["durum"] ?? "Bekliyor";
    $oncelik = $_POST["oncelik"] ?? "Normal";


    /* ==========================================
       PERSONEL ID'LERİNİ TEMİZLE
    ========================================== */

    if (!is_array($personel_ids)) {
        $personel_ids = [$personel_ids];
    }

    $personel_ids = array_map("intval", $personel_ids);
    $personel_ids = array_filter($personel_ids, function ($id) {
        return $id > 0;
    });

    $personel_ids = array_values(array_unique($personel_ids));


    /* ==========================================
       KONTROLLER
    ========================================== */

    if ($baslik === "") {

        $mesaj = "Görev başlığı boş bırakılamaz.";

    } elseif (count($personel_ids) === 0) {

        $mesaj = "Lütfen görev atanacak en az bir personel seçin.";

    } elseif ($baslangic_tarihi === "") {

        $mesaj = "Başlangıç tarihi seçilmelidir.";

    } elseif ($teslim_tarihi === "") {

        $mesaj = "Teslim tarihi seçilmelidir.";

    } elseif ($teslim_tarihi < $baslangic_tarihi) {

        $mesaj = "Teslim tarihi başlangıç tarihinden önce olamaz.";

    } else {

        try {

            $db->beginTransaction();


            /* ==========================================
               SEÇİLEN PERSONELLERİN AKTİF VE HESAPLI
               OLDUĞUNU KONTROL ET
            ========================================== */

            $personelKontrolStmt = $db->prepare("
                SELECT
                    id,
                    kullanici_id,
                    ad,
                    soyad
                FROM personeller
                WHERE id = ?
                AND durum = 'aktif'
                LIMIT 1
            ");

            $kontrol_personeller = [];

            foreach ($personel_ids as $personel_id) {

                $personelKontrolStmt->execute([
                    $personel_id
                ]);

                $personel = $personelKontrolStmt->fetch(PDO::FETCH_ASSOC);

                if (!$personel) {

                    throw new Exception(
                        "Seçilen personellerden biri bulunamadı veya aktif değil."
                    );
                }

                if (empty($personel["kullanici_id"])) {

                    throw new Exception(
                        $personel["ad"] . " " .
                        $personel["soyad"] .
                        " adlı personelin kullanıcı hesabı bulunamadı."
                    );
                }

                $kontrol_personeller[] = $personel;
            }


            /* ==========================================
               GÖREVİ KAYDET
            ========================================== */

            /*
             * Eski sistemle uyumluluk için ilk personeli
             * gorevler.personel_id alanında tutuyoruz.
             *
             * Asıl çoklu personel ilişkileri ise
             * gorev_personeller tablosunda tutulacak.
             */

            $ilk_personel_id = $personel_ids[0];

            $sql = "INSERT INTO gorevler
                    (
                        baslik,
                        aciklama,
                        personel_id,
                        baslangic_tarihi,
                        teslim_tarihi,
                        durum,
                        oncelik
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?)";

            $stmt = $db->prepare($sql);

            $stmt->execute([
                $baslik,
                $aciklama,
                $ilk_personel_id,
                $baslangic_tarihi,
                $teslim_tarihi,
                $durum,
                $oncelik
            ]);


            /* ==========================================
               EKLENEN GÖREVİN ID'SİNİ AL
            ========================================== */

            $gorev_id = (int) $db->lastInsertId();


            /* ==========================================
               PERSONEL - GÖREV İLİŞKİLERİNİ KAYDET
            ========================================== */

            $iliskiStmt = $db->prepare("
                INSERT INTO gorev_personeller
                (
                    gorev_id,
                    personel_id
                )
                VALUES (?, ?)
            ");

            foreach ($personel_ids as $personel_id) {

                $iliskiStmt->execute([
                    $gorev_id,
                    $personel_id
                ]);
            }


            /* ==========================================
               HER PERSONELE AYRI BİLDİRİM OLUŞTUR
            ========================================== */

            $bildirimStmt = $db->prepare("
                INSERT INTO bildirimler
                (
                    kullanici_id,
                    gorev_id,
                    mesaj,
                    tur,
                    okundu
                )
                VALUES (?, ?, ?, ?, 0)
            ");

            $bildirim_mesaji =
                "Size yeni bir görev atandı: " . $baslik;

            foreach ($kontrol_personeller as $personel) {

                $bildirimStmt->execute([
                    $personel["kullanici_id"],
                    $gorev_id,
                    $bildirim_mesaji,
                    "gorev"
                ]);
            }


            /* ==========================================
               İŞLEMLER BAŞARILI
            ========================================== */

            $db->commit();

            header("Location: gorevler.php");
            exit;


        } catch (Exception $e) {

            if ($db->inTransaction()) {
                $db->rollBack();
            }

            $mesaj = $e->getMessage();

        } catch (PDOException $e) {

            if ($db->inTransaction()) {
                $db->rollBack();
            }

            $mesaj = "Görev eklenirken bir veritabanı hatası oluştu.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="tr">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Yeni Görev - Birim Takip</title>

    <script src="https://unpkg.com/lucide@latest"></script>

    <link rel="stylesheet" href="../css/style.css">
    <style>

.personel-secim-listesi {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 8px;
    padding: 10px;
    border: 1px solid var(--border);
    border-radius: 12px;
    background: var(--white);
    max-height: 220px;
    overflow-y: auto;
}

.personel-secim-item {
    position: relative;
    display: flex;
    align-items: center;
    gap: 10px;
    min-height: 44px;
    padding: 10px 12px;
    border: 1px solid transparent;
    border-radius: 9px;
    background: transparent;
    cursor: pointer;
    transition: background-color .18s ease,
                border-color .18s ease;
}

.personel-secim-item:hover {
    background: #f7f9fc;
    border-color: var(--border);
}

.personel-secim-item input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.personel-check-box {
    width: 18px;
    height: 18px;
    min-width: 18px;
    border: 1.5px solid #b8c3d4;
    border-radius: 5px;
    background: #ffffff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all .18s ease;
}

.personel-secim-item input:checked + .personel-check-box {
    background: #4d6fd8;
    border-color: #4d6fd8;
}

.personel-secim-item input:checked + .personel-check-box::after {
    content: "✓";
    color: #ffffff;
    font-size: 12px;
    font-weight: 700;
}

.personel-secim-adi {
    color: var(--text);
    font-size: 13px;
    font-weight: 600;
}

.personel-secim-aciklama {
    display: block;
    margin-top: 7px;
    color: var(--muted);
    font-size: 11px;
}


/* DARK MODE */

body.dark-mode .personel-secim-listesi {
    background: #121b2d;
    border-color: #26344a;
}

body.dark-mode .personel-secim-item:hover {
    background: #182338;
    border-color: #344761;
}

body.dark-mode .personel-check-box {
    background: #0f1826;
    border-color: #405069;
}

body.dark-mode .personel-secim-item input:checked + .personel-check-box {
    background: #4d6fd8;
    border-color: #4d6fd8;
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

                <h2>BİRİM TAKİP</h2>

                <span>
                    Yönetim Sistemi
                </span>

            </div>

        </div>


        <nav class="sidebar-menu">


            <!-- ANA SAYFA -->

            <a
                href="../dashboard.php"
                class="sidebar-link">

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
                href="birimler.php"
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
                href="personeller.php"
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
                href="gorevler.php"
                class="sidebar-link active">

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
                href="evraklar.php"
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


    <!-- ================= ANA İÇERİK ================= -->

    <main class="main-content">


        <!-- ÜST BAR -->

        <header class="topbar">


            <div class="topbar-left">

                <button
                    type="button"
                    class="menu-button">

                    <i data-lucide="menu"></i>

                </button>

                <span class="page-title">
                    Yeni Görev
                </span>

            </div>


            <div class="topbar-right">

                <?php require_once __DIR__ . "/../includes/duyuru-sistemi.php"; ?>


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

                <div class="user-area">

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

            </div>

        </header>


        <!-- ================= SAYFA ================= -->

        <section class="page-content">


            <div class="welcome-section">

                <h1>
                    Yeni Görev Ekle
                </h1>

                <p>
                    Personelinize yeni bir görev atayın.
                </p>

            </div>


            <!-- FORM KARTI -->

            <div class="large-card add-form-card">


                <div class="card-title-area">

                    <div>

                        <h2>
                            Görev Bilgileri
                        </h2>

                        <p>
                            Görev detaylarını ve atanacak personeli belirleyin.
                        </p>

                    </div>


                    <a
                        href="gorevler.php"
                        class="view-button">

                        <i data-lucide="arrow-left"></i>

                        Görevlere Dön

                    </a>

                </div>


                <!-- HATA MESAJI -->

                <?php if ($mesaj != ""): ?>

                    <div class="form-message error">

                        <i data-lucide="circle-alert"></i>

                        <span>
                            <?php echo htmlspecialchars($mesaj); ?>
                        </span>

                    </div>

                <?php endif; ?>


                <!-- FORM -->

                <form
                    method="POST"
                    class="data-form">


                    <!-- BAŞLIK -->

                    <div class="form-group">

                        <label for="baslik">
                            Görev Başlığı
                        </label>

                        <div class="form-input-wrapper">

                            <i
                                data-lucide="clipboard-list"
                                class="form-input-icon">
                            </i>

                            <input
                                type="text"
                                id="baslik"
                                name="baslik"
                                placeholder="Örneğin: Aylık raporu hazırlama"
                                required>

                        </div>

                    </div>


                    <!-- AÇIKLAMA -->

                    <div class="form-group">

                        <label for="aciklama">
                            Açıklama
                        </label>

                        <div class="form-input-wrapper">

                            <i
                                data-lucide="align-left"
                                class="form-input-icon">
                            </i>

                            <textarea
                                id="aciklama"
                                name="aciklama"
                                rows="5"
                                placeholder="Görev hakkında açıklama girin"></textarea>

                        </div>

                    </div>
<!-- PERSONELLER -->

<div class="form-group">

    <label>
        Görev Atanacak Personeller
    </label>

    <div class="personel-secim-listesi">

        <?php foreach ($personeller as $personel): ?>

            <label class="personel-secim-item">

                <input
                    type="checkbox"
                    name="personel_id[]"
                    value="<?php echo (int) $personel["id"]; ?>"
                    <?php
                    if (
                        isset($_POST["personel_id"]) &&
                        is_array($_POST["personel_id"]) &&
                        in_array(
                            (int) $personel["id"],
                            array_map("intval", $_POST["personel_id"]),
                            true
                        )
                    ) {
                        echo "checked";
                    }
                    ?>
                >

                <span class="personel-check-box"></span>

                <span class="personel-secim-adi">
                    <?php
                    echo htmlspecialchars(
                        $personel["ad"] . " " . $personel["soyad"]
                    );
                    ?>
                </span>

            </label>

        <?php endforeach; ?>

    </div>

    <small class="personel-secim-aciklama">
        Görevi vermek istediğiniz personelleri seçin.
    </small>

</div>

                    <!-- BAŞLANGIÇ TARİHİ -->

                    <div class="form-group">

                        <label for="baslangic_tarihi">
                            Başlangıç Tarihi
                        </label>

                        <div class="form-input-wrapper">

                            <i
                                data-lucide="calendar-days"
                                class="form-input-icon">
                            </i>

                            <input
                                type="date"
                                id="baslangic_tarihi"
                                name="baslangic_tarihi"
                                required>

                        </div>

                    </div>


                    <!-- TESLİM TARİHİ -->

                    <div class="form-group">

                        <label for="teslim_tarihi">
                            Teslim Tarihi
                        </label>

                        <div class="form-input-wrapper">

                            <i
                                data-lucide="calendar-check"
                                class="form-input-icon">
                            </i>

                            <input
                                type="date"
                                id="teslim_tarihi"
                                name="teslim_tarihi"
                                required>

                        </div>

                    </div>


                    <!-- DURUM -->

                    <div class="form-group">

                        <label for="durum">
                            Durum
                        </label>

                        <div class="form-input-wrapper">

                            <i
                                data-lucide="circle-dot"
                                class="form-input-icon">
                            </i>

                            <select
                                id="durum"
                                name="durum">

                                <option value="Bekliyor">
                                    Bekliyor
                                </option>

                                <option value="Devam Ediyor">
                                    Devam Ediyor
                                </option>

                                <option value="Tamamlandı">
                                    Tamamlandı
                                </option>

                            </select>

                        </div>

                    </div>


                    <!-- ÖNCELİK -->

                    <div class="form-group">

                        <label for="oncelik">
                            Öncelik
                        </label>

                        <div class="form-input-wrapper">

                            <i
                                data-lucide="flag"
                                class="form-input-icon">
                            </i>

                            <select
                                id="oncelik"
                                name="oncelik">

                                <option value="Normal">
                                    Normal
                                </option>

                                <option value="Düşük">
                                    Düşük
                                </option>

                                <option value="Yüksek">
                                    Yüksek
                                </option>

                            </select>

                        </div>

                    </div>


                    <!-- BUTONLAR -->

                    <div class="form-actions">


                        <a
                            href="gorevler.php"
                            class="cancel-button">

                            İptal

                        </a>


                        <button
                            type="submit"
                            class="save-button">

                            <i data-lucide="save"></i>

                            Görevi Kaydet

                        </button>


                    </div>


                </form>


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
