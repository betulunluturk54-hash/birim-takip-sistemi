<?php

session_start();

if (!isset($_SESSION["kullanici_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";

$mesaj = "";
$basarili = "";


/* ==========================================
   BİRİMLERİ GETİR
========================================== */

$sql = "SELECT id, birim_adi FROM birimler ORDER BY birim_adi ASC";

$stmt = $db->prepare($sql);
$stmt->execute();

$birimler = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* ==========================================
   PERSONEL EKLE
========================================== */

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $ad = trim($_POST["ad"]);
    $soyad = trim($_POST["soyad"]);
    $telefon = trim($_POST["telefon"]);
    $email = trim($_POST["email"]);
    $birim_id = $_POST["birim_id"];
    $gorev = trim($_POST["gorev"]);


    if ($ad == "" || $soyad == "") {

        $mesaj = "Ad ve soyad alanları zorunludur.";

    } elseif ($birim_id == "") {

        $mesaj = "Lütfen birim seçin.";

    } else {

        $sql = "INSERT INTO personeller
                (ad, soyad, telefon, email, birim_id, gorev)
                VALUES (?, ?, ?, ?, ?, ?)";

        $stmt = $db->prepare($sql);

        if (
            $stmt->execute([
                $ad,
                $soyad,
                $telefon,
                $email,
                $birim_id,
                $gorev
            ])
        ) {

            header("Location: personeller.php");
            exit;

        } else {

            $mesaj = "Personel eklenirken bir hata oluştu.";

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

    <link rel="stylesheet" href="../css/style.css">

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


            <a href="../dashboard.php"
               class="sidebar-link">

                <i data-lucide="house"
                   class="menu-icon"></i>

                <span>
                    Ana Sayfa
                </span>

            </a>


            <a href="birimler.php"
               class="sidebar-link">

                <i data-lucide="building-2"
                   class="menu-icon"></i>

                <span>
                    Birimler
                </span>

            </a>


            <a href="personeller.php"
               class="sidebar-link active">

                <i data-lucide="users"
                   class="menu-icon"></i>

                <span>
                    Personeller
                </span>

            </a>


            <a href="gorevler.php"
               class="sidebar-link">

                <i data-lucide="clipboard-list"
                   class="menu-icon"></i>

                <span>
                    Görevler
                </span>

            </a>


            <a href="evraklar.php"
               class="sidebar-link">

                <i data-lucide="file-text"
                   class="menu-icon"></i>

                <span>
                    Evraklar
                </span>

            </a>

        </nav>


        <div class="sidebar-bottom">

            <a href="../logout.php"
               class="sidebar-link logout">

                <i data-lucide="log-out"
                   class="menu-icon"></i>

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
                    Yeni Personel
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
                    Yeni Personel Ekle
                </h1>

                <p>
                    Sisteme yeni bir personel kaydı oluşturun.
                </p>

            </div>


            <!-- FORM KARTI -->

            <div class="large-card add-form-card">


                <div class="card-title-area">

                    <div>

                        <h2>
                            Personel Bilgileri
                        </h2>

                        <p>
                            Personelin temel bilgilerini girin.
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

                <?php if ($mesaj != ""): ?>

                    <div class="form-message error">

                        <i data-lucide="circle-alert"></i>

                        <span>
                            <?php echo htmlspecialchars($mesaj); ?>
                        </span>

                    </div>

                <?php endif; ?>


                <!-- FORM -->

                <form method="POST" class="data-form">


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
                                placeholder="05xx xxx xx xx">

                        </div>

                    </div>


                    <!-- EMAIL -->

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
                                        value="<?php echo $birim["id"]; ?>">

                                        <?php
                                        echo htmlspecialchars(
                                            $birim["birim_adi"]
                                        );
                                        ?>

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
                                placeholder="Örneğin: Yazılım Uzmanı">

                        </div>

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

                            Personeli Kaydet

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