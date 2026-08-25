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
   GİRİŞ YAPAN PERSONELİ GETİR
========================================== */

$sql = "SELECT
            p.id,
            p.ad,
            p.soyad,
            p.telefon,
            p.email,
            p.gorev,
            p.birim_id,
            p.durum,
            b.birim_adi
        FROM personeller p
        LEFT JOIN birimler b
            ON p.birim_id = b.id
        WHERE p.kullanici_id = ?";

$stmt = $db->prepare($sql);
$stmt->execute([$_SESSION["kullanici_id"]]);

$personel = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$personel) {
    die("Bu kullanıcıya bağlı personel kaydı bulunamadı.");
}


/* ==========================================
   PROFİL GÜNCELLE
========================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $telefon = trim($_POST["telefon"] ?? "");
    $email = trim($_POST["email"] ?? "");


    /* E-posta doluysa kontrol et */

    if (
        $email !== "" &&
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {

        $mesaj = "Lütfen geçerli bir e-posta adresi girin.";
        $mesaj_turu = "error";

    } else {

        $sql = "UPDATE personeller
                SET telefon = ?,
                    email = ?
                WHERE kullanici_id = ?";

        $stmt = $db->prepare($sql);

        if (
            $stmt->execute([
                $telefon !== "" ? $telefon : null,
                $email !== "" ? $email : null,
                $_SESSION["kullanici_id"]
            ])
        ) {

            $mesaj = "Profil bilgileriniz başarıyla güncellendi.";
            $mesaj_turu = "success";


            /* Güncel bilgileri tekrar çek */

            $sql = "SELECT
                        p.id,
                        p.ad,
                        p.soyad,
                        p.telefon,
                        p.email,
                        p.gorev,
                        p.birim_id,
                        p.durum,
                        b.birim_adi
                    FROM personeller p
                    LEFT JOIN birimler b
                        ON p.birim_id = b.id
                    WHERE p.kullanici_id = ?";

            $stmt = $db->prepare($sql);
            $stmt->execute([$_SESSION["kullanici_id"]]);

            $personel = $stmt->fetch(PDO::FETCH_ASSOC);

        } else {

            $mesaj = "Profil güncellenirken bir hata oluştu.";
            $mesaj_turu = "error";
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

    <title>Profilim - Birim Takip</title>

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
                    Personel Paneli
                </span>

            </div>

        </div>


        <nav class="sidebar-menu">


            <!-- ANA SAYFA -->

            <a
                href="../personel-dashboard.php"
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
                href="personel-gorevlerim.php"
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
                href="personel-evraklarim.php"
                class="sidebar-link">

                <i
                    data-lucide="file-text"
                    class="menu-icon">
                </i>

                <span>
                    Evraklarım
                </span>

            </a>


            <!-- PROFİLİM -->

            <a
                href="personel-profil.php"
                class="sidebar-link active">

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


        <!-- TOPBAR -->

        <header class="topbar">

            <div class="topbar-left">

                <button
                    type="button"
                    class="menu-button">

                    <i data-lucide="menu"></i>

                </button>

                <span class="page-title">
                    Profilim
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
                    Profilim
                </h1>

                <p>
                    Kişisel bilgilerinizi görüntüleyin ve iletişim bilgilerinizi güncelleyin.
                </p>

            </div>


            <div class="large-card add-form-card">


                <div class="card-title-area">

                    <div>

                        <h2>
                            Personel Bilgileri
                        </h2>

                        <p>
                            Kurum sistemindeki mevcut personel bilgileriniz.
                        </p>

                    </div>

                </div>


                <!-- MESAJ -->

                <?php if ($mesaj !== ""): ?>

                    <div class="form-message <?php echo $mesaj_turu; ?>">

                        <?php if ($mesaj_turu === "success"): ?>

                            <i data-lucide="circle-check"></i>

                        <?php else: ?>

                            <i data-lucide="circle-alert"></i>

                        <?php endif; ?>


                        <span>

                            <?php
                            echo htmlspecialchars($mesaj);
                            ?>

                        </span>

                    </div>

                <?php endif; ?>


                <form
                    method="POST"
                    class="data-form">


                    <!-- AD -->

                    <div class="form-group">

                        <label>
                            Ad
                        </label>

                        <div class="form-input-wrapper">

                            <i
                                data-lucide="user"
                                class="form-input-icon">
                            </i>

                            <input
                                type="text"
                                value="<?php
                                echo htmlspecialchars(
                                    $personel["ad"]
                                );
                                ?>"
                                readonly>

                        </div>

                    </div>


                    <!-- SOYAD -->

                    <div class="form-group">

                        <label>
                            Soyad
                        </label>

                        <div class="form-input-wrapper">

                            <i
                                data-lucide="user"
                                class="form-input-icon">
                            </i>

                            <input
                                type="text"
                                value="<?php
                                echo htmlspecialchars(
                                    $personel["soyad"]
                                );
                                ?>"
                                readonly>

                        </div>

                    </div>


                    <!-- BİRİM -->

                    <div class="form-group">

                        <label>
                            Birim
                        </label>

                        <div class="form-input-wrapper">

                            <i
                                data-lucide="building-2"
                                class="form-input-icon">
                            </i>

                            <input
                                type="text"
                                value="<?php
                                echo htmlspecialchars(
                                    $personel["birim_adi"] ?? "-"
                                );
                                ?>"
                                readonly>

                        </div>

                    </div>


                    <!-- GÖREV -->

                    <div class="form-group">

                        <label>
                            Görev
                        </label>

                        <div class="form-input-wrapper">

                            <i
                                data-lucide="briefcase"
                                class="form-input-icon">
                            </i>

                            <input
                                type="text"
                                value="<?php
                                echo htmlspecialchars(
                                    $personel["gorev"] ?? "-"
                                );
                                ?>"
                                readonly>

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
                                value="<?php
                                echo htmlspecialchars(
                                    $personel["telefon"] ?? ""
                                );
                                ?>"
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
                                value="<?php
                                echo htmlspecialchars(
                                    $personel["email"] ?? ""
                                );
                                ?>"
                                placeholder="ornek@kurum.com">

                        </div>

                    </div>


                    <!-- HESAP DURUMU -->

                    <div class="form-group">

                        <label>
                            Hesap Durumu
                        </label>

                        <div class="form-input-wrapper">

                            <i
                                data-lucide="circle-check"
                                class="form-input-icon">
                            </i>

                            <input
                                type="text"
                                value="<?php
                                echo $personel["durum"] === "aktif"
                                    ? "Aktif"
                                    : "Pasif";
                                ?>"
                                readonly>

                        </div>

                    </div>


                    <!-- BUTONLAR -->

                    <div class="form-actions">

                        <a
                            href="../personel-dashboard.php"
                            class="cancel-button">

                            İptal

                        </a>


                        <button
                            type="submit"
                            class="save-button">

                            <i data-lucide="save"></i>

                            Bilgilerimi Güncelle

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