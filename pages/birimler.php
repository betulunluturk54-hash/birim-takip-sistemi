<?php

session_start();

if (!isset($_SESSION["kullanici_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";


/* ==========================================
   BİRİMLERİ VERİTABANINDAN ÇEK
========================================== */

$sql = "SELECT * FROM birimler ORDER BY id DESC";

$stmt = $db->prepare($sql);
$stmt->execute();

$birimler = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="tr">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Birimler - Birim Takip</title>

    <script src="https://unpkg.com/lucide@latest"></script>

    <link rel="stylesheet" href="../css/style.css">

</head>


<body>


<div class="dashboard">


    <!-- ==========================================
         SIDEBAR
    =========================================== -->

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


    <!-- ==========================================
         ANA İÇERİK
    =========================================== -->

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
                    Birimler
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


        <!-- ==========================================
             SAYFA
        =========================================== -->

        <section class="page-content">


            <div class="welcome-section">

                <h1>
                    Birimler
                </h1>

                <p>
                    Sistemde kayıtlı birimleri yönetin.
                </p>

            </div>


            <!-- ======================================
                 BİRİM LİSTESİ
            ======================================= -->

            <div class="large-card">


                <div class="card-title-area">


                    <div>

                        <h2>
                            Birim Listesi
                        </h2>

                        <p>
                            Sistemde kayıtlı tüm birimler
                        </p>

                    </div>


<a
    href="birim-ekle.php"
    class="view-button">

    <i data-lucide="plus"></i>

    Yeni Birim

</a>

                </div>


                <!-- TABLO -->

                <div class="table-container">


                    <table class="data-table">


                        <thead>

                            <tr>

                                <th>
                                    #
                                </th>

                                <th>
                                    Birim Adı
                                </th>

                                <th>
                                    İşlemler
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                            <?php if (count($birimler) > 0): ?>

                             <?php $sira = 1; ?>

                                <?php foreach ($birimler as $birim): ?>


                                    <tr>

                                        <td>
                                        <?php $sira++; ?>
                                        </td>


                                        <td>

                                            <div class="table-name">

                                                <div class="table-icon">

                                                    <i
                                                        data-lucide="building-2">
                                                    </i>

                                                </div>

                                                <span>

                                                    <?php

                                                    echo htmlspecialchars(
                                                        $birim["birim_adi"]
                                                    );

                                                    ?>

                                                </span>

                                            </div>

                                        </td>


                                        <td>

                                            <div class="table-actions">


                                                <a
    href="birim-duzenle.php?id=<?php echo $birim["id"]; ?>"
    class="action-button edit">

    <i data-lucide="pencil"></i>

    Düzenle

</a>


<a
    href="birim-sil.php?id=<?php echo $birim["id"]; ?>"
    class="action-button delete"
    onclick="return confirm('Bu birimi silmek istediğinize emin misiniz?');">

    <i data-lucide="trash-2"></i>

    Sil

</a>


                                            </div>

                                        </td>

                                    </tr>


                                <?php endforeach; ?>


                            <?php else: ?>


                                <tr>

                                    <td
                                        colspan="3"
                                        class="table-empty">

                                        <i
                                            data-lucide="building-2">
                                        </i>

                                        <span>
                                            Henüz kayıtlı birim bulunmuyor.
                                        </span>

                                    </td>

                                </tr>


                            <?php endif; ?>


                        </tbody>


                    </table>


                </div>


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