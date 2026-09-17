<?php

session_start();

if (!isset($_SESSION["kullanici_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";

$mesaj = "";
$basarili = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $birim_adi = trim($_POST["birim_adi"]);

    if ($birim_adi == "") {

        $mesaj = "Birim adı boş bırakılamaz.";

    } else {

        $sql = "INSERT INTO birimler (birim_adi) VALUES (?)";

        $stmt = $db->prepare($sql);

        if ($stmt->execute([$birim_adi])) {

            $basarili = "Birim başarıyla eklendi.";

        } else {

            $mesaj = "Birim eklenirken bir hata oluştu.";

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

    <title>Yeni Birim - Birim Takip</title>

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
                class="sidebar-link active">

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
                    Yeni Birim
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
                    Yeni Birim Ekle
                </h1>

                <p>
                    Sisteme yeni bir birim ekleyin.
                </p>

            </div>


            <!-- FORM KARTI -->

            <div class="large-card add-form-card">


                <div class="card-title-area">

                    <div>

                        <h2>
                            Birim Bilgileri
                        </h2>

                        <p>
                            Yeni oluşturulacak birimin adını girin.
                        </p>

                    </div>


                    <a
                        href="birimler.php"
                        class="view-button">

                        <i data-lucide="arrow-left"></i>

                        Birimlere Dön

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


                <!-- BAŞARI MESAJI -->

                <?php if ($basarili != ""): ?>

                    <div class="form-message success">

                        <i data-lucide="circle-check"></i>

                        <span>
                            <?php echo htmlspecialchars($basarili); ?>
                        </span>

                    </div>

                <?php endif; ?>


                <!-- FORM -->

                <form method="POST" class="data-form">


                    <div class="form-group">

                        <label for="birim_adi">
                            Birim Adı
                        </label>

                        <div class="form-input-wrapper">

                            <i
                                data-lucide="building-2"
                                class="form-input-icon">
                            </i>

                            <input
                                type="text"
                                id="birim_adi"
                                name="birim_adi"
                                placeholder="Örneğin: İnsan Kaynakları"
                                required>

                        </div>

                    </div>


                    <!-- BUTONLAR -->

                    <div class="form-actions">


                        <a
                            href="birimler.php"
                            class="cancel-button">

                            İptal

                        </a>


                        <button
                            type="submit"
                            class="save-button">

                            <i data-lucide="save"></i>

                            Birimi Kaydet

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
