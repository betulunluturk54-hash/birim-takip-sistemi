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

$mesaj = "";
$mesaj_turu = "";


/* ==========================================
   GİRİŞ YAPAN PERSONELİ BUL
========================================== */

$sql = "SELECT
            id,
            ad,
            soyad
        FROM personeller
        WHERE kullanici_id = ?";

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
   EVRAK EKLE
========================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $evrak_adi = trim(
        $_POST["evrak_adi"] ?? ""
    );

    $evrak_no = trim(
        $_POST["evrak_no"] ?? ""
    );

    $aciklama = trim(
        $_POST["aciklama"] ?? ""
    );

    $tarih = $_POST["tarih"] ?? "";


    /* ==========================================
       DOSYA YOLU
       DOSYA ARTIK ZORUNLU DEĞİL
    ========================================== */

    $dosya_yolu = null;


    /* ==========================================
       ZORUNLU ALAN KONTROLÜ
    ========================================== */

    if (
        $evrak_adi === "" ||
        $tarih === ""
    ) {

        $mesaj =
            "Evrak adı ve tarih alanları zorunludur.";

        $mesaj_turu =
            "error";

    } else {


        /* ==========================================
           DOSYA KONTROLÜ
           DOSYA SEÇİLMEDİYSE HATA VERME
        ========================================== */

        $dosya_secildi = false;

        if (
            isset($_FILES["dosya"]) &&
            $_FILES["dosya"]["error"] !== UPLOAD_ERR_NO_FILE
        ) {

            $dosya_secildi = true;

        }


        /* ==========================================
           DOSYA SEÇİLDİYSE DOSYA İŞLEMLERİ
        ========================================== */

        if ($dosya_secildi) {

            $dosya =
                $_FILES["dosya"];


            /* Dosya yükleme hatası */

            if (
                $dosya["error"] !== UPLOAD_ERR_OK
            ) {

                $mesaj =
                    "Dosya yüklenirken bir hata oluştu.";

                $mesaj_turu =
                    "error";

            } else {


                /* ==========================================
                   DOSYA BOYUTU
                   MAKSİMUM 5 MB
                ========================================== */

                $maksimum_boyut =
                    5 * 1024 * 1024;


                if (
                    $dosya["size"] > $maksimum_boyut
                ) {

                    $mesaj =
                        "Dosya boyutu en fazla 5 MB olabilir.";

                    $mesaj_turu =
                        "error";

                } else {


                    /* ==========================================
                       İZİN VERİLEN UZANTILAR
                    ========================================== */

                    $izinli_uzantilar = [
                        "jpg",
                        "jpeg",
                        "png",
                        "pdf",
                        "doc",
                        "docx"
                    ];


                    $uzanti =
                        strtolower(
                            pathinfo(
                                $dosya["name"],
                                PATHINFO_EXTENSION
                            )
                        );


                    if (
                        !in_array(
                            $uzanti,
                            $izinli_uzantilar,
                            true
                        )
                    ) {

                        $mesaj =
                            "Bu dosya türüne izin verilmiyor.";

                        $mesaj_turu =
                            "error";

                    } else {


                        /* ==========================================
                           UPLOADS KLASÖRÜ
                        ========================================== */

                        $upload_klasoru =
                            "../uploads/";


                        if (
                            !is_dir(
                                $upload_klasoru
                            )
                        ) {

                            mkdir(
                                $upload_klasoru,
                                0777,
                                true
                            );

                        }


                        /* ==========================================
                           BENZERSİZ DOSYA ADI
                        ========================================== */

                        $yeni_dosya_adi =
                            "evrak_"
                            . bin2hex(
                                random_bytes(8)
                            )
                            . "."
                            . $uzanti;


                        $hedef =
                            $upload_klasoru
                            . $yeni_dosya_adi;


                        /* ==========================================
                           DOSYAYI TAŞI
                        ========================================== */

                        if (
                            move_uploaded_file(
                                $dosya["tmp_name"],
                                $hedef
                            )
                        ) {

                            $dosya_yolu =
                                "uploads/"
                                . $yeni_dosya_adi;

                        } else {

                            $mesaj =
                                "Dosya yüklenirken bir hata oluştu.";

                            $mesaj_turu =
                                "error";
                        }
                    }
                }
            }
        }


        /* ==========================================
           VERİTABANINA KAYDET
           DOSYA OLSA DA OLMASA DA KAYIT YAPILIR
        ========================================== */

        if ($mesaj_turu === "") {

            $sql = "INSERT INTO evraklar (
                        evrak_adi,
                        evrak_no,
                        aciklama,
                        tarih,
                        personel_id,
                        dosya_yolu,
                        durum
                    )
                    VALUES (
                        ?, ?, ?, ?, ?, ?, 'beklemede'
                    )";


            $stmt =
                $db->prepare($sql);


            $basarili =
                $stmt->execute([
                    $evrak_adi,

                    $evrak_no !== ""
                        ? $evrak_no
                        : null,

                    $aciklama !== ""
                        ? $aciklama
                        : null,

                    $tarih,

                    $personel_id,

                    $dosya_yolu
                ]);


            if ($basarili) {

                $mesaj =
                    "Evrakınız başarıyla gönderildi. Yönetici onayı bekleniyor.";

                $mesaj_turu =
                    "success";


                /* Formu temizle */

                $_POST = [];


            } else {


                /* Veritabanı kaydı başarısızsa
                   yüklenmiş dosya varsa sil */

                if (
                    $dosya_yolu !== null
                ) {

                    $gercek_dosya =
                        __DIR__ .
                        "/../" .
                        $dosya_yolu;


                    if (
                        file_exists(
                            $gercek_dosya
                        )
                    ) {

                        unlink(
                            $gercek_dosya
                        );

                    }
                }


                $mesaj =
                    "Evrak kaydedilirken bir hata oluştu.";

                $mesaj_turu =
                    "error";
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

    <title>
        Evrak Ekle - Birim Takip
    </title>

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
                class="sidebar-link">

                <i
                    data-lucide="house"
                    class="menu-icon">
                </i>

                <span>
                    Ana Sayfa
                </span>

            </a>


            <!-- GÖREVLERİM -->

            <a
                href="/BirimTakipSistemi/pages/personel-gorevlerim.php"
                class="sidebar-link">

                <i
                    data-lucide="clipboard-list"
                    class="menu-icon">
                </i>

                <span>
                    Görevlerim
                </span>

            </a>


            <!-- EVRAKLARIM -->

            <a
                href="/BirimTakipSistemi/pages/personel-evraklarim.php"
                class="sidebar-link active">

                <i
                    data-lucide="file-text"
                    class="menu-icon">
                </i>

                <span>
                    Evraklarım
                </span>

            </a>


            <!-- PROFİL -->

            <a
                href="/BirimTakipSistemi/pages/personel-profil.php"
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


        <header class="topbar">

            <div class="topbar-left">

                <button
                    type="button"
                    class="menu-button">

                    <i data-lucide="menu"></i>

                </button>

                <span class="page-title">
                    Evrak Ekle
                </span>

            </div>


            <div class="topbar-right">

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
                            Personel
                        </span>

                    </div>

                </div>

            </div>

        </header>


        <!-- ================= SAYFA ================= -->

        <section class="page-content">


            <div class="welcome-section">

                <h1>
                    Evrak Ekle
                </h1>

                <p>
                    Evrakınızı yöneticinin onayına gönderebilirsiniz.
                </p>

            </div>


            <div class="large-card add-form-card">


                <div class="card-title-area">

                    <div>

                        <h2>
                            Yeni Evrak
                        </h2>

                        <p>
                            Gönderdiğiniz evrak yönetici onayından geçecektir.
                        </p>

                    </div>

                </div>


                <!-- MESAJ -->

                <?php if ($mesaj !== ""): ?>

                    <div
                        class="form-message
                        <?php echo $mesaj_turu; ?>"
                    >

                        <?php if (
                            $mesaj_turu === "success"
                        ): ?>

                            <i
                                data-lucide="circle-check">
                            </i>

                        <?php else: ?>

                            <i
                                data-lucide="circle-alert">
                            </i>

                        <?php endif; ?>


                        <span>

                            <?php

                            echo htmlspecialchars(
                                $mesaj
                            );

                            ?>

                        </span>

                    </div>

                <?php endif; ?>


                <form
                    method="POST"
                    enctype="multipart/form-data"
                    class="data-form">


                    <!-- EVRAK ADI -->

                    <div class="form-group">

                        <label for="evrak_adi">
                            Evrak Adı *
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
                                value="<?php

                                echo htmlspecialchars(
                                    $_POST["evrak_adi"] ?? ""
                                );

                                ?>"
                                placeholder="Örn: İzin Formu"
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
                                value="<?php

                                echo htmlspecialchars(
                                    $_POST["evrak_no"] ?? ""
                                );

                                ?>"
                                placeholder="Örn: PİF-08-2026">

                        </div>

                    </div>


                    <!-- TARİH -->

                    <div class="form-group">

                        <label for="tarih">
                            Tarih *
                        </label>


                        <div class="form-input-wrapper">

                            <i
                                data-lucide="calendar"
                                class="form-input-icon">
                            </i>


                            <input
                                type="date"
                                id="tarih"
                                name="tarih"
                                value="<?php

                                echo htmlspecialchars(
                                    $_POST["tarih"] ??
                                    date("Y-m-d")
                                );

                                ?>"
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
                                rows="4"
                                placeholder="Evrak hakkında açıklama yazabilirsiniz..."
                            ><?php

                            echo htmlspecialchars(
                                $_POST["aciklama"] ?? ""
                            );

                            ?></textarea>

                        </div>

                    </div>


                    <!-- DOSYA -->

                    <div class="form-group">

                        <label for="dosya">
                            Dosya
                        </label>


                        <div class="file-upload-wrapper">

                            <label
                                for="dosya"
                                class="file-upload-box"
                            >

                                <div class="file-upload-icon">

                                    <i
                                        data-lucide="upload">
                                    </i>

                                </div>


                                <div class="file-upload-text">

                                    <span
                                        class="file-upload-title"
                                    >

                                        Dosya seçmek için tıklayın

                                    </span>


                                    <span
                                        class="file-upload-subtitle"
                                    >

                                        JPG, JPEG, PNG, PDF, DOC veya DOCX
                                        • Maksimum 5 MB
                                        • İsteğe bağlı

                                    </span>

                                </div>

                            </label>


                            <input
                                type="file"
                                id="dosya"
                                name="dosya"
                                class="file-upload-input"
                                accept=".jpg,.jpeg,.png,.pdf,.doc,.docx"
                            >


                            <div
                                class="file-name"
                                id="fileName"
                            >

                                Henüz dosya seçilmedi.

                            </div>

                        </div>

                    </div>


                    <!-- BUTONLAR -->

                    <div class="form-actions">


                        <a
                            href="personel-evraklarim.php"
                            class="cancel-button"
                        >

                            İptal

                        </a>


                        <button
                            type="submit"
                            class="save-button"
                        >

                            <i data-lucide="send"></i>

                            Onaya Gönder

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


    /* ==========================================
       DOSYA ADI
    ========================================== */

    const fileInput =
        document.getElementById(
            "dosya"
        );

    const fileName =
        document.getElementById(
            "fileName"
        );


    if (
        fileInput &&
        fileName
    ) {

        fileInput.addEventListener(
            "change",
            function () {

                if (
                    this.files &&
                    this.files.length > 0
                ) {

                    fileName.textContent =
                        this.files[0].name;

                } else {

                    fileName.textContent =
                        "Henüz dosya seçilmedi.";

                }

            }
        );

    }

</script>


</body>

</html>