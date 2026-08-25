<?php

session_start();

if (!isset($_SESSION["kullanici_id"])) {
    header("Location: ../login.php");
    exit;
}

/* ==========================================
   SADECE ADMİN ERİŞEBİLİR
========================================== */

if ($_SESSION["rol"] !== "admin") {
    header("Location: ../personel-dashboard.php");
    exit;
}

require_once "../config/database.php";


/* ==========================================
   EVRAK ID KONTROLÜ
========================================== */

$id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;

if ($id <= 0) {
    header("Location: evraklar.php");
    exit;
}


/* ==========================================
   EVRAK BİLGİSİNİ GETİR
========================================== */

$sql = "SELECT id, evrak_adi, durum
        FROM evraklar
        WHERE id = ?";

$stmt = $db->prepare($sql);
$stmt->execute([$id]);

$evrak = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$evrak) {
    header("Location: evraklar.php");
    exit;
}


/* ==========================================
   RED İŞLEMİ
========================================== */

$mesaj = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $red_nedeni = trim($_POST["red_nedeni"] ?? "");

    if ($red_nedeni === "") {

        $mesaj = "Lütfen red nedenini girin.";

    } else {

        try {

            $sql = "UPDATE evraklar
                    SET durum = 'reddedildi',
                        red_nedeni = ?
                    WHERE id = ?";

            $stmt = $db->prepare($sql);
            $stmt->execute([
                $red_nedeni,
                $id
            ]);

            header("Location: evraklar.php");
            exit;

        } catch (PDOException $e) {

            $mesaj = "Evrak reddedilirken bir hata oluştu.";
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

    <title>Evrak Reddet - Birim Takip</title>

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

                <i data-lucide="house" class="menu-icon"></i>

                <span>
                    Ana Sayfa
                </span>

            </a>


            <a
                href="birimler.php"
                class="sidebar-link">

                <i data-lucide="building-2" class="menu-icon"></i>

                <span>
                    Birimler
                </span>

            </a>


            <a
                href="personeller.php"
                class="sidebar-link">

                <i data-lucide="users" class="menu-icon"></i>

                <span>
                    Personeller
                </span>

            </a>


            <a
                href="gorevler.php"
                class="sidebar-link">

                <i data-lucide="clipboard-list" class="menu-icon"></i>

                <span>
                    Görevler
                </span>

            </a>


            <a
                href="evraklar.php"
                class="sidebar-link active">

                <i data-lucide="file-text" class="menu-icon"></i>

                <span>
                    Evraklar
                </span>

            </a>

        </nav>


        <div class="sidebar-bottom">

            <a
                href="../logout.php"
                class="sidebar-link logout">

                <i data-lucide="log-out" class="menu-icon"></i>

                <span>
                    Çıkış Yap
                </span>

            </a>

        </div>

    </aside>


    <!-- ================= ANA ALAN ================= -->

    <main class="main-content">

        <header class="topbar">

            <div class="topbar-left">

                <button
                    type="button"
                    class="menu-button">

                    <i data-lucide="menu"></i>

                </button>

                <span class="page-title">
                    Evrak Reddet
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


        <!-- ================= SAYFA İÇERİĞİ ================= -->

        <section class="page-content">

            <div class="welcome-section">

                <h1>
                    Evrakı Reddet
                </h1>

                <p>
                    Evrağın neden reddedildiğini belirtin.
                </p>

            </div>


            <div class="large-card add-form-card">

                <div class="card-title-area">

                    <div>

                        <h2>
                            Red Nedeni
                        </h2>

                        <p>
                            Bu bilgi personele evrağın neden reddedildiğini açıklamak için kullanılacaktır.
                        </p>

                    </div>

                    <a
                        href="evraklar.php"
                        class="view-button">

                        <i data-lucide="arrow-left"></i>

                        Evraklara Dön

                    </a>

                </div>


                <?php if ($mesaj !== ""): ?>

                    <div class="form-message error">

                        <i data-lucide="circle-alert"></i>

                        <span>
                            <?php echo htmlspecialchars($mesaj); ?>
                        </span>

                    </div>

                <?php endif; ?>


                <form
                    method="POST"
                    class="data-form">

                    <div class="form-group">

                        <label>
                            Evrak
                        </label>

                        <div class="form-input-wrapper">

                            <i
                                data-lucide="file-text"
                                class="form-input-icon">
                            </i>

                            <input
                                type="text"
                                value="<?php echo htmlspecialchars($evrak["evrak_adi"]); ?>"
                                readonly>

                        </div>

                    </div>


                    <div class="form-group">

                        <label for="red_nedeni">
                            Red Nedeni
                        </label>

                        <div class="form-input-wrapper">

                            <i
                                data-lucide="message-square-x"
                                class="form-input-icon">
                            </i>

                            <textarea
                                id="red_nedeni"
                                name="red_nedeni"
                                rows="6"
                                placeholder="Örneğin: Evrakta eksik bilgi bulunmaktadır."
                                required></textarea>

                        </div>

                    </div>


                    <div class="form-actions">

                        <a
                            href="evraklar.php"
                            class="cancel-button">

                            İptal

                        </a>


                        <button
                            type="submit"
                            class="save-button">

                            <i data-lucide="x-circle"></i>

                            Evrakı Reddet

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