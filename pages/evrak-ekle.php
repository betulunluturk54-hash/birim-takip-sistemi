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
   EVRAK EKLE
========================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $evrak_adi = trim($_POST["evrak_adi"] ?? "");
    $evrak_no = trim($_POST["evrak_no"] ?? "");
    $aciklama = trim($_POST["aciklama"] ?? "");
    $tarih = $_POST["tarih"] ?? "";
    $personel_id = $_POST["personel_id"] ?? "";

    /* ==========================================
       TEMEL KONTROLLER
    ========================================== */

    if ($evrak_adi === "") {

        $mesaj = "Evrak adı boş bırakılamaz.";

    } elseif ($tarih === "") {

        $mesaj = "Evrak tarihi seçilmelidir.";

    } elseif ($personel_id === "") {

        $mesaj = "Lütfen ilgili personeli seçin.";

    } else {

        $dosya_yolu = null;
        $hedef_dosya = null;


        /* ==========================================
           DOSYA SEÇİLDİYSE YÜKLE
           DOSYA SEÇİLMEZSE DEVAM ET
        ========================================== */

        if (
            isset($_FILES["dosya"]) &&
            $_FILES["dosya"]["error"] !== UPLOAD_ERR_NO_FILE
        ) {

            if ($_FILES["dosya"]["error"] !== UPLOAD_ERR_OK) {

                $mesaj = "Dosya yüklenirken bir hata oluştu.";

            } else {

                $dosya = $_FILES["dosya"];

                /* Maksimum dosya boyutu: 10 MB */

                $maksimum_boyut = 10 * 1024 * 1024;

                if ($dosya["size"] > $maksimum_boyut) {

                    $mesaj = "Dosya boyutu en fazla 10 MB olabilir.";

                } else {

                    /* İzin verilen dosya türleri */

                    $izin_verilen_uzantilar = [
                        "pdf",
                        "doc",
                        "docx",
                        "xls",
                        "xlsx",
                        "jpg",
                        "jpeg",
                        "png"
                    ];

                    $uzanti = strtolower(
                        pathinfo(
                            $dosya["name"],
                            PATHINFO_EXTENSION
                        )
                    );


                    if (
                        !in_array(
                            $uzanti,
                            $izin_verilen_uzantilar,
                            true
                        )
                    ) {

                        $mesaj =
                            "Bu dosya türüne izin verilmiyor. "
                            . "PDF, Word, Excel, JPG, JPEG ve PNG yükleyebilirsiniz.";

                    } else {

                        /* ==========================================
                           UPLOADS KLASÖRÜ
                        ========================================== */

                        $upload_klasoru =
                            __DIR__ . "/../uploads/";


                        if (!is_dir($upload_klasoru)) {

                            mkdir(
                                $upload_klasoru,
                                0755,
                                true
                            );
                        }


                        /* ==========================================
                           BENZERSİZ DOSYA ADI
                        ========================================== */

                        $yeni_dosya_adi =
                            uniqid("evrak_", true)
                            . "."
                            . $uzanti;


                        $hedef_dosya =
                            $upload_klasoru
                            . $yeni_dosya_adi;


                        /* ==========================================
                           DOSYAYI UPLOADS KLASÖRÜNE TAŞI
                        ========================================== */

                        if (
                            move_uploaded_file(
                                $dosya["tmp_name"],
                                $hedef_dosya
                            )
                        ) {

                            $dosya_yolu =
                                "uploads/"
                                . $yeni_dosya_adi;

                        } else {

                            $mesaj =
                                "Dosya yüklenirken bir hata oluştu.";
                        }
                    }
                }
            }
        }


        /* ==========================================
           VERİTABANINA KAYDET
        ========================================== */

        if ($mesaj === "") {

            $sql = "INSERT INTO evraklar
                    (
                        evrak_adi,
                        evrak_no,
                        aciklama,
                        tarih,
                        personel_id,
                        dosya_yolu
                    )
                    VALUES (?, ?, ?, ?, ?, ?)";

            $stmt = $db->prepare($sql);


            try {

                $stmt->execute([
                    $evrak_adi,
                    $evrak_no,
                    $aciklama,
                    $tarih,
                    $personel_id,
                    $dosya_yolu
                ]);


                header("Location: evraklar.php");
                exit;


            } catch (PDOException $e) {

                /* Veritabanı kaydı başarısızsa
                   yüklenen dosyayı sil */

                if (
                    $hedef_dosya !== null &&
                    file_exists($hedef_dosya)
                ) {

                    unlink($hedef_dosya);
                }


                $mesaj =
                    "Evrak kaydedilirken bir hata oluştu.";
            }
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

    <title>Yeni Evrak - Birim Takip</title>

    <script src="https://unpkg.com/lucide@latest"></script>

    <link
        rel="stylesheet"
        href="../css/style.css">

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


            <a
                href="gorevler.php"
                class="sidebar-link">

                <i
                    data-lucide="clipboard-list"
                    class="menu-icon">
                </i>

                <span>
                    Görevler
                </span>

            </a>


            <a
                href="evraklar.php"
                class="sidebar-link active">

                <i
                    data-lucide="file-text"
                    class="menu-icon">
                </i>

                <span>
                    Evraklar
                </span>

            </a>


        </nav>


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


        <!-- TOPBAR -->

        <header class="topbar">

            <div class="topbar-left">

                <button
                    type="button"
                    class="menu-button">

                    <i data-lucide="menu"></i>

                </button>

                <span class="page-title">
                    Yeni Evrak
                </span>

            </div>


            <div class="topbar-right">

                <?php require_once __DIR__ . "/../includes/duyuru-sistemi.php"; ?>


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
                    Yeni Evrak Ekle
                </h1>

                <p>
                    Sisteme yeni bir evrak kaydı ve dosya ekleyin.
                </p>

            </div>


            <div class="large-card add-form-card">


                <div class="card-title-area">


                    <div>

                        <h2>
                            Evrak Bilgileri
                        </h2>

                        <p>
                            Evrak bilgilerini ve ilgili personeli belirleyin.
                        </p>

                    </div>


                    <a
                        href="evraklar.php"
                        class="view-button">

                        <i data-lucide="arrow-left"></i>

                        Evraklara Dön

                    </a>

                </div>


                <!-- HATA MESAJI -->

                <?php if ($mesaj !== ""): ?>

                    <div class="form-message error">

                        <i data-lucide="circle-alert"></i>

                        <span>

                            <?php
                            echo htmlspecialchars($mesaj);
                            ?>

                        </span>

                    </div>

                <?php endif; ?>


                <!-- FORM -->

                <form
                    method="POST"
                    enctype="multipart/form-data"
                    class="data-form">


                    <!-- EVRAK ADI -->

                    <div class="form-group">

                        <label for="evrak_adi">
                            Evrak Adı
                        </label>

                        <div class="form-input-wrapper">

                            <i
                                data-lucide="file-text"
                                class="form-input-icon">
                            </i>

                            <input
                                type="text"
                                id="evrak_adi"
                                name="evrak_adi"
                                placeholder="Örneğin: Personel İzin Formu"
                                required>

                        </div>

                    </div>


                    <!-- EVRAK NO -->

                    <div class="form-group">

                        <label for="evrak_no">
                            Evrak No
                        </label>

                        <div class="form-input-wrapper">

                            <i
                                data-lucide="hash"
                                class="form-input-icon">
                            </i>

                            <input
                                type="text"
                                id="evrak_no"
                                name="evrak_no"
                                placeholder="Örneğin: EV-2026-001">

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
                                placeholder="Evrak hakkında açıklama girin"></textarea>

                        </div>

                    </div>


                    <!-- TARİH -->

                    <div class="form-group">

                        <label for="tarih">
                            Evrak Tarihi
                        </label>

                        <div class="form-input-wrapper">

                            <i
                                data-lucide="calendar-days"
                                class="form-input-icon">
                            </i>

                            <input
                                type="date"
                                id="tarih"
                                name="tarih"
                                required>

                        </div>

                    </div>


                    <!-- PERSONEL -->

                    <div class="form-group">

                        <label for="personel_id">
                            İlgili Personel
                        </label>

                        <div class="form-input-wrapper">

                            <i
                                data-lucide="user"
                                class="form-input-icon">
                            </i>

                            <select
                                id="personel_id"
                                name="personel_id"
                                required>

                                <option value="">
                                    Personel seçin
                                </option>


                                <?php foreach ($personeller as $personel): ?>

                                    <option
                                        value="<?php echo $personel["id"]; ?>">

                                        <?php

                                        echo htmlspecialchars(
                                            $personel["ad"]
                                            . " "
                                            . $personel["soyad"]
                                        );

                                        ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                    </div>


                    <!-- DOSYA -->

                    <div class="form-group">

                        <label for="dosya">
                            Dosya
                        </label>


                        <div class="file-input-wrapper">

                            <input
                                type="file"
                                id="dosya"
                                name="dosya"
                                accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">


                            <div class="file-input-display">

                                <i data-lucide="upload"></i>

                                <span>
                                    Dosya seçin
                                </span>

                            </div>

                        </div>


                        <small class="form-help">

                            Dosya yüklemek isteğe bağlıdır.
                            İzin verilen dosyalar:
                            PDF, Word, Excel, JPG, JPEG ve PNG.
                            Maksimum 10 MB.

                        </small>

                    </div>


                    <!-- BUTONLAR -->

                    <div class="form-actions">


                        <a
                            href="evraklar.php"
                            class="cancel-button">

                            İptal

                        </a>


                        <button
                            type="submit"
                            class="save-button">

                            <i data-lucide="upload"></i>

                            Evrakı Kaydet

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
