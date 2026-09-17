<?php

session_start();

if (!isset($_SESSION["kullanici_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";

$mesaj = "";


/* ==========================================
   BİRİMLERİ GETİR
========================================== */

$sql = "
    SELECT
        id,
        birim_adi
    FROM birimler
    ORDER BY birim_adi ASC
";

$stmt = $db->prepare($sql);
$stmt->execute();

$birimler = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* ==========================================
   FORM DEĞERLERİ
========================================== */

$ad = "";
$soyad = "";
$telefon = "";
$email = "";
$birim_id = "";
$gorev = "";
$kullanici_adi = "";


/* ==========================================
   PERSONEL + KULLANICI HESABI OLUŞTUR
========================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $ad = trim($_POST["ad"] ?? "");
    $soyad = trim($_POST["soyad"] ?? "");
    $telefon = trim($_POST["telefon"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $birim_id = (int) ($_POST["birim_id"] ?? 0);
    $gorev = trim($_POST["gorev"] ?? "");

    $kullanici_adi = trim($_POST["kullanici_adi"] ?? "");
    $sifre = $_POST["sifre"] ?? "";
    $sifre_tekrar = $_POST["sifre_tekrar"] ?? "";


    /* ==========================================
       ZORUNLU ALAN KONTROLLERİ
    ========================================== */

    if ($ad === "" || $soyad === "") {

        $mesaj = "Ad ve soyad alanları zorunludur.";

    } elseif ($birim_id <= 0) {

        $mesaj = "Lütfen birim seçin.";

    } elseif ($kullanici_adi === "") {

        $mesaj = "Kullanıcı adı zorunludur.";

    } elseif ($sifre === "") {

        $mesaj = "Şifre zorunludur.";

    } elseif (strlen($sifre) < 6) {

        $mesaj = "Şifre en az 6 karakter olmalıdır.";

    } elseif ($sifre !== $sifre_tekrar) {

        $mesaj = "Şifreler birbiriyle eşleşmiyor.";

    } else {

        try {

            /* ==========================================
               TRANSACTION BAŞLAT
            ========================================== */

            $db->beginTransaction();


            /* ==========================================
               KULLANICI ADI KONTROLÜ
            ========================================== */

            $kullaniciKontrol = $db->prepare("
                SELECT id
                FROM kullanicilar
                WHERE kullanici_adi = ?
                LIMIT 1
            ");

            $kullaniciKontrol->execute([
                $kullanici_adi
            ]);

            if ($kullaniciKontrol->fetch()) {

                throw new Exception(
                    "Bu kullanıcı adı zaten kullanılıyor."
                );
            }


            /* ==========================================
               PERSONEL KAYDI OLUŞTUR
            ========================================== */

            $personelStmt = $db->prepare("
                INSERT INTO personeller
                (
                    ad,
                    soyad,
                    telefon,
                    email,
                    birim_id,
                    gorev
                )
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $personelStmt->execute([
                $ad,
                $soyad,
                $telefon !== "" ? $telefon : null,
                $email !== "" ? $email : null,
                $birim_id,
                $gorev !== "" ? $gorev : null
            ]);


            /* ==========================================
               OLUŞAN PERSONEL ID
            ========================================== */

            $personel_id = (int) $db->lastInsertId();


            /* ==========================================
               ŞİFREYİ HASHLE
            ========================================== */

            $hashli_sifre = password_hash(
                $sifre,
                PASSWORD_DEFAULT
            );


            /* ==========================================
               KULLANICI HESABI OLUŞTUR
            ========================================== */

            $kullaniciStmt = $db->prepare("
                INSERT INTO kullanicilar
                (
                    kullanici_adi,
                    sifre,
                    rol
                )
                VALUES (?, ?, ?)
            ");

            $kullaniciStmt->execute([
                $kullanici_adi,
                $hashli_sifre,
                "personel"
            ]);


            /* ==========================================
               OLUŞAN KULLANICI ID
            ========================================== */

            $kullanici_id = (int) $db->lastInsertId();


            /* ==========================================
               PERSONELİ KULLANICIYA BAĞLA
            ========================================== */

            $baglaStmt = $db->prepare("
                UPDATE personeller
                SET kullanici_id = ?
                WHERE id = ?
            ");

            $baglaStmt->execute([
                $kullanici_id,
                $personel_id
            ]);


            /* ==========================================
               TRANSACTION TAMAMLANDI
            ========================================== */

            $db->commit();


            header("Location: personeller.php");
            exit;


        } catch (Exception $e) {

            /* ==========================================
               HATA OLURSA GERİ AL
            ========================================== */

            if ($db->inTransaction()) {
                $db->rollBack();
            }

            $mesaj = $e->getMessage();

        } catch (PDOException $e) {

            if ($db->inTransaction()) {
                $db->rollBack();
            }

            $mesaj = "Personel hesabı oluşturulurken bir hata oluştu.";
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

    <title>Yeni Personel - Birim Takip</title>

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
                class="sidebar-link active">

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
                class="sidebar-link">

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


        <!-- SIDEBAR ALT -->

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


        <!-- ================= TOPBAR ================= -->

        <header class="topbar">

            <div class="topbar-left">

                <button
                    type="button"
                    class="menu-button">

                    <i data-lucide="menu"></i>

                </button>

                <span class="page-title">
                    Yeni Personel
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


            <!-- SAYFA BAŞLIĞI -->

            <div class="welcome-section">

                <h1>
                    Yeni Personel Ekle
                </h1>

                <p>
                    Sisteme yeni bir personel ve kullanıcı hesabı oluşturun.
                </p>

            </div>


            <!-- FORM KARTI -->

            <div class="large-card add-form-card">


                <!-- KART BAŞLIĞI -->

                <div class="card-title-area">

                    <div>

                        <h2>
                            Personel Bilgileri
                        </h2>

                        <p>
                            Personelin temel bilgilerini ve giriş bilgilerini girin.
                        </p>

                    </div>


                    <a
                        href="personeller.php"
                        class="view-button">

                        <i data-lucide="arrow-left"></i>

                        Personellere Dön

                    </a>

                </div>


                <!-- HATA -->

                <?php if ($mesaj !== ""): ?>

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


                    <!-- ================= PERSONEL BİLGİLERİ ================= -->


                    <!-- AD -->

                    <div class="form-group">

                        <label for="ad">
                            Ad
                        </label>

                        <div class="form-input-wrapper">

                            <i
                                data-lucide="user"
                                class="form-input-icon">
                            </i>

                            <input
                                type="text"
                                id="ad"
                                name="ad"
                                value="<?= htmlspecialchars($ad) ?>"
                                placeholder="Personelin adını girin"
                                required>

                        </div>

                    </div>


                    <!-- SOYAD -->

                    <div class="form-group">

                        <label for="soyad">
                            Soyad
                        </label>

                        <div class="form-input-wrapper">

                            <i
                                data-lucide="user"
                                class="form-input-icon">
                            </i>

                            <input
                                type="text"
                                id="soyad"
                                name="soyad"
                                value="<?= htmlspecialchars($soyad) ?>"
                                placeholder="Personelin soyadını girin"
                                required>

                        </div>

                    </div>


                    <!-- TELEFON -->

                    <div class="form-group">

                        <label for="telefon">
                            Telefon
                        </label>

                        <div class="form-input-wrapper">

                            <i
                                data-lucide="phone"
                                class="form-input-icon">
                            </i>

                            <input
                                type="text"
                                id="telefon"
                                name="telefon"
                                value="<?= htmlspecialchars($telefon) ?>"
                                placeholder="05xx xxx xx xx">

                        </div>

                    </div>


                    <!-- E-POSTA -->

                    <div class="form-group">

                        <label for="email">
                            E-posta
                        </label>

                        <div class="form-input-wrapper">

                            <i
                                data-lucide="mail"
                                class="form-input-icon">
                            </i>

                            <input
                                type="email"
                                id="email"
                                name="email"
                                value="<?= htmlspecialchars($email) ?>"
                                placeholder="ornek@kurum.com">

                        </div>

                    </div>


                    <!-- BİRİM -->

                    <div class="form-group">

                        <label for="birim_id">
                            Birim
                        </label>

                        <div class="form-input-wrapper">

                            <i
                                data-lucide="building-2"
                                class="form-input-icon">
                            </i>

                            <select
                                id="birim_id"
                                name="birim_id"
                                required>

                                <option value="">
                                    Birim seçin
                                </option>

                                <?php foreach ($birimler as $birim): ?>

                                    <option
                                        value="<?= (int) $birim["id"] ?>"
                                        <?= $birim_id === (int) $birim["id"]
                                            ? "selected"
                                            : "" ?>>

                                        <?= htmlspecialchars(
                                            $birim["birim_adi"]
                                        ) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                    </div>


                    <!-- GÖREV -->

                    <div class="form-group">

                        <label for="gorev">
                            Görev
                        </label>

                        <div class="form-input-wrapper">

                            <i
                                data-lucide="briefcase"
                                class="form-input-icon">
                            </i>

                            <input
                                type="text"
                                id="gorev"
                                name="gorev"
                                value="<?= htmlspecialchars($gorev) ?>"
                                placeholder="Örneğin: Yazılım Uzmanı">

                        </div>

                    </div>


                    <!-- ================= HESAP BİLGİLERİ ================= -->


                    <!-- KULLANICI ADI -->

                    <div class="form-group">

                        <label for="kullanici_adi">
                            Kullanıcı Adı
                        </label>

                        <div class="form-input-wrapper">

                            <i
                                data-lucide="circle-user"
                                class="form-input-icon">
                            </i>

                            <input
                                type="text"
                                id="kullanici_adi"
                                name="kullanici_adi"
                                value="<?= htmlspecialchars($kullanici_adi) ?>"
                                placeholder="Personelin giriş kullanıcı adını girin"
                                autocomplete="off"
                                required>

                        </div>

                    </div>


                    <!-- ŞİFRE -->

                    <div class="form-group">

                        <label for="sifre">
                            Şifre
                        </label>

                        <div class="form-input-wrapper">

                            <i
                                data-lucide="lock"
                                class="form-input-icon">
                            </i>

                            <input
                                type="password"
                                id="sifre"
                                name="sifre"
                                placeholder="En az 6 karakter"
                                autocomplete="new-password"
                                required>

                        </div>

                    </div>


                    <!-- ŞİFRE TEKRAR -->

                    <div class="form-group">

                        <label for="sifre_tekrar">
                            Şifre Tekrar
                        </label>

                        <div class="form-input-wrapper">

                            <i
                                data-lucide="lock-keyhole"
                                class="form-input-icon">
                            </i>

                            <input
                                type="password"
                                id="sifre_tekrar"
                                name="sifre_tekrar"
                                placeholder="Şifreyi tekrar girin"
                                autocomplete="new-password"
                                required>

                        </div>

                    </div>


                    <!-- BİLGİ -->

                    <div class="form-message">

                        <i data-lucide="info"></i>

                        <span>
                            Oluşturulan hesap personel rolüyle sisteme giriş yapacaktır.
                            Şifre veritabanında güvenli şekilde şifrelenerek saklanır.
                        </span>

                    </div>


                    <!-- BUTONLAR -->

                    <div class="form-actions">


                        <a
                            href="personeller.php"
                            class="cancel-button">

                            İptal

                        </a>


                        <button
                            type="submit"
                            class="save-button">

                            <i data-lucide="save"></i>

                            Personeli ve Hesabı Kaydet

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
