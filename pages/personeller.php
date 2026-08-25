<?php

session_start();

if (!isset($_SESSION["kullanici_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";


/* ==========================================
   PERSONELLERİ VERİTABANINDAN ÇEK
========================================== */

$sql = "SELECT
            p.id,
            p.ad,
            p.soyad,
            p.telefon,
            p.email,
            p.gorev,
            p.kayit_tarihi,
            p.durum,
            p.ayrilma_tarihi,
            b.birim_adi
        FROM personeller p
        LEFT JOIN birimler b
            ON p.birim_id = b.id
        WHERE p.durum = 'aktif'
        ORDER BY p.id DESC";

$stmt = $db->prepare($sql);
$stmt->execute();

$personeller = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="tr">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Personeller - Birim Takip</title>

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
                    Personeller
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


        <!-- ================= SAYFA İÇERİĞİ ================= -->

        <section class="page-content">


            <!-- SAYFA BAŞLIĞI -->

            <div class="welcome-section">

                <h1>
                    Personeller
                </h1>

                <p>
                    Sistemde kayıtlı personelleri yönetin.
                </p>

            </div>


            <!-- ================= PERSONEL LİSTESİ ================= -->

            <div class="large-card">


                <!-- KART BAŞLIĞI -->

                <div class="card-title-area">

                    <div>

                        <h2>
                            Personel Listesi
                        </h2>

                        <p>
                            Sistemde kayıtlı aktif personeller
                        </p>

                    </div>


                    <div style="display: flex; gap: 10px;">

                        <!-- PERSONEL ARŞİVİ -->

                        <a
                            href="personel-arsiv.php"
                            class="view-button">

                            <i data-lucide="archive"></i>

                            Personel Arşivi

                        </a>


                        <!-- YENİ PERSONEL -->

                        <a
                            href="personel-ekle.php"
                            class="view-button">

                            <i data-lucide="plus"></i>

                            Yeni Personel

                        </a>

                    </div>

                </div>


                <!-- ================= TABLO ================= -->

                <div class="table-container">

                    <table class="data-table">


                        <thead>

                            <tr>

                                <th>
                                    #
                                </th>

                                <th>
                                    Personel
                                </th>

                                <th>
                                    Birim
                                </th>

                                <th>
                                    Görev
                                </th>

                                <th>
                                    Telefon
                                </th>

                                <th>
                                    E-posta
                                </th>

                                <th>
                                    İşlemler
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                            <?php if (count($personeller) > 0): ?>

                                <?php $sira = 1; ?>


                                <?php foreach ($personeller as $personel): ?>

                                    <tr>


                                        <!-- SIRA -->

                                        <td>

                                            <?php

                                            echo $sira;

                                            ?>

                                        </td>


                                        <!-- PERSONEL -->

                                        <td>

                                            <div class="table-name">

                                                <div class="table-icon">

                                                    <i
                                                        data-lucide="user">
                                                    </i>

                                                </div>

                                                <span>

                                                    <?php

                                                    echo htmlspecialchars(
                                                        $personel["ad"] . " " .
                                                        $personel["soyad"]
                                                    );

                                                    ?>

                                                </span>

                                            </div>

                                        </td>


                                        <!-- BİRİM -->

                                        <td>

                                            <?php

                                            echo htmlspecialchars(
                                                $personel["birim_adi"] ?? "-"
                                            );

                                            ?>

                                        </td>


                                        <!-- GÖREV -->

                                        <td>

                                            <?php

                                            echo htmlspecialchars(
                                                $personel["gorev"] ?? "-"
                                            );

                                            ?>

                                        </td>


                                        <!-- TELEFON -->

                                        <td>

                                            <?php

                                            echo htmlspecialchars(
                                                $personel["telefon"] ?? "-"
                                            );

                                            ?>

                                        </td>


                                        <!-- E-POSTA -->

                                        <td>

                                            <?php

                                            echo htmlspecialchars(
                                                $personel["email"] ?? "-"
                                            );

                                            ?>

                                        </td>


                                        <!-- İŞLEMLER -->

                                        <td>

                                            <div class="table-actions">


                                                <!-- DÜZENLE -->

                                                <a
                                                    href="personel-duzenle.php?id=<?php echo $personel["id"]; ?>"
                                                    class="action-button edit">

                                                    <i data-lucide="pencil"></i>

                                                    Düzenle

                                                </a>


                                                <!-- ARŞİVLE -->

                                                <a
                                                    href="personel-arsivle.php?id=<?php echo $personel["id"]; ?>"
                                                    class="action-button delete"
                                                    onclick="return confirm('Bu personeli arşivlemek istediğinize emin misiniz?');">

                                                    <i data-lucide="archive"></i>

                                                    Arşivle

                                                </a>


                                            </div>

                                        </td>


                                    </tr>


                                    <?php $sira++; ?>


                                <?php endforeach; ?>


                            <?php else: ?>


                                <!-- KAYIT YOK -->

                                <tr>

                                    <td
                                        colspan="7"
                                        class="table-empty">

                                        <i
                                            data-lucide="users">
                                        </i>

                                        <span>
                                            Henüz kayıtlı personel bulunmuyor.
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


<!-- ================= JAVASCRIPT ================= -->

<script src="../js/script.js"></script>

<script>
    lucide.createIcons();
</script>


</body>

</html>