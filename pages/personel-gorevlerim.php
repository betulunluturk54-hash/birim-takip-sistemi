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
$stmt->execute([$_SESSION["kullanici_id"]]);

$personel = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$personel) {
    die("Bu kullanıcıya bağlı personel kaydı bulunamadı.");
}

$personel_id = $personel["id"];


/* ==========================================
   PERSONELE AİT GÖREVLERİ GETİR
========================================== */

$sql = "SELECT
            id,
            baslik,
            aciklama,
            baslangic_tarihi,
            teslim_tarihi,
            durum,
            oncelik
        FROM gorevler
        WHERE personel_id = ?
        ORDER BY teslim_tarihi ASC";

$stmt = $db->prepare($sql);
$stmt->execute([$personel_id]);

$gorevler = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="tr">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Görevlerim - Birim Takip</title>

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
        class="sidebar-link active">

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
        class="sidebar-link">

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


        <!-- TOPBAR -->

        <header class="topbar">


            <div class="topbar-left">

                <button
                    type="button"
                    class="menu-button">

                    <i data-lucide="menu"></i>

                </button>

                <span class="page-title">
                    Görevlerim
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
                    Görevlerim
                </h1>

                <p>
                    Size atanmış görevleri buradan takip edebilirsiniz.
                </p>

            </div>


            <div class="large-card">


                <div class="card-title-area">

                    <div>

                        <h2>
                            Görev Listem
                        </h2>

                        <p>
                            Toplam <?php echo count($gorevler); ?> görev
                        </p>

                    </div>

                </div>


                <?php if (count($gorevler) > 0): ?>


                    <div class="task-list">


                        <?php foreach ($gorevler as $gorev): ?>


                            <div class="task-item">


                                <div class="task-item-icon">

                                    <i
                                        data-lucide="clipboard-list">
                                    </i>

                                </div>


                                <div class="task-item-content">


                                    <strong>

                                        <?php

                                        echo htmlspecialchars(
                                            $gorev["baslik"]
                                        );

                                        ?>

                                    </strong>


                                    <span>

                                        Başlangıç:

                                        <?php

                                        echo $gorev["baslangic_tarihi"]
                                            ? date(
                                                "d.m.Y",
                                                strtotime(
                                                    $gorev["baslangic_tarihi"]
                                                )
                                            )
                                            : "-";

                                        ?>

                                        &nbsp;&nbsp;|&nbsp;&nbsp;

                                        Teslim:

                                        <?php

                                        echo $gorev["teslim_tarihi"]
                                            ? date(
                                                "d.m.Y",
                                                strtotime(
                                                    $gorev["teslim_tarihi"]
                                                )
                                            )
                                            : "-";

                                        ?>

                                    </span>

                                </div>


                                <div class="task-item-status">

    <a
        href="personel-gorev-durum.php?id=<?php echo (int)$gorev["id"]; ?>"
        class="task-status-link">

        <span class="task-status">

            <?php echo htmlspecialchars($gorev["durum"]); ?>

        </span>

        <i data-lucide="chevron-right"></i>

    </a>

</div>


                            </div>


                        <?php endforeach; ?>


                    </div>


                <?php else: ?>


                    <div class="empty-area">

                        <div class="empty-icon">

                            <i
                                data-lucide="clipboard-list">
                            </i>

                        </div>

                        <p>
                            Henüz size atanmış görev bulunmuyor.
                        </p>

                    </div>


                <?php endif; ?>


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