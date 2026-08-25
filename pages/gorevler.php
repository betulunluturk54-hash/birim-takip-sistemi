<?php


session_start();

if (!isset($_SESSION["kullanici_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";


/* ==========================================
   GÖREVLERİ PERSONEL ADIYLA GETİR
========================================== */

$sql = "SELECT
            g.id,
            g.baslik,
            g.aciklama,
            g.personel_id,
            g.baslangic_tarihi,
            g.teslim_tarihi,
            g.durum,
            g.oncelik,
            p.ad,
            p.soyad
        FROM gorevler g
        LEFT JOIN personeller p
            ON g.personel_id = p.id
        ORDER BY g.id DESC";

try {

    $stmt = $db->prepare($sql);
    $stmt->execute();

    $gorevler = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    die(
        "<h2>Veritabanı Hatası</h2>" .
        "<p>" .
        htmlspecialchars($e->getMessage()) .
        "</p>"
    );
}

?>

<!DOCTYPE html>
<html lang="tr">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Görevler - Birim Takip</title>

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
                class="sidebar-link active">

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
                    Görevler
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


        <!-- ================= SAYFA ================= -->

        <section class="page-content">


            <div class="welcome-section">

                <h1>
                    Görevler
                </h1>

                <p>
                    Sistemdeki görevleri ve personel atamalarını yönetin.
                </p>

            </div>


            <!-- ================= GÖREV LİSTESİ ================= -->

            <div class="large-card">


                <div class="card-title-area">


                    <div>

                        <h2>
                            Görev Listesi
                        </h2>

                        <p>
                            Sistemde kayıtlı tüm görevler
                        </p>

                    </div>


                    <a
                        href="gorev-ekle.php"
                        class="view-button">

                        <i data-lucide="plus"></i>

                        Yeni Görev

                    </a>

                </div>


                <div class="table-container">

                    <table class="data-table">


                        <thead>

                            <tr>

                                <th>#</th>

                                <th>Görev</th>

                                <th>Personel</th>

                                <th>Başlangıç</th>

                                <th>Teslim Tarihi</th>

                                <th>Durum</th>

                                <th>Öncelik</th>

                                <th>İşlemler</th>

                            </tr>

                        </thead>


                        <tbody>


                            <?php if (count($gorevler) > 0): ?>

                                <?php $sira = 1; ?>


                                <?php foreach ($gorevler as $gorev): ?>


                                    <tr>


                                        <!-- SIRA -->

                                        <td>
                                            <?php echo $sira; ?>
                                        </td>


                                        <!-- GÖREV -->

                                        <td>

                                            <div class="table-name">

                                                <div class="table-icon">

                                                    <i
                                                        data-lucide="clipboard-list">
                                                    </i>

                                                </div>

                                                <span>

                                                    <?php

                                                    echo htmlspecialchars(
                                                        $gorev["baslik"]
                                                    );

                                                    ?>

                                                </span>

                                            </div>

                                        </td>


                                        <!-- PERSONEL -->

                                        <td>

                                            <?php if (
                                                !empty($gorev["ad"]) &&
                                                !empty($gorev["soyad"])
                                            ): ?>

                                                <?php

                                                echo htmlspecialchars(
                                                    $gorev["ad"] . " " .
                                                    $gorev["soyad"]
                                                );

                                                ?>

                                            <?php else: ?>

                                                <span>
                                                    Atanmamış
                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <!-- BAŞLANGIÇ -->

                                        <td>

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

                                        </td>


                                        <!-- TESLİM -->

                                        <td>

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

                                        </td>


                                        <!-- DURUM -->

                                        <td>

                                            <span class="task-status">

                                                <?php

                                                echo htmlspecialchars(
                                                    $gorev["durum"]
                                                );

                                                ?>

                                            </span>

                                        </td>


                                        <!-- ÖNCELİK -->

                                        <td>

                                            <span class="task-priority">

                                                <?php

                                                echo htmlspecialchars(
                                                    $gorev["oncelik"]
                                                );

                                                ?>

                                            </span>

                                        </td>


                                        <!-- İŞLEMLER -->

                                        <td>

                                            <div class="table-actions">

<a
    href="gorev-duzenle.php?id=<?php echo $gorev["id"]; ?>"
    class="action-button edit">

    <i data-lucide="pencil"></i>

    Düzenle

</a>


<a
    href="gorev-sil.php?id=<?php echo $gorev["id"]; ?>"
    class="action-button delete"
    onclick="return confirm('Bu görevi silmek istediğinize emin misiniz?');">

    <i data-lucide="trash-2"></i>

    Sil

</a>

                                            </div>

                                        </td>


                                    </tr>


                                    <?php $sira++; ?>


                                <?php endforeach; ?>


                            <?php else: ?>


                                <tr>

                                    <td
                                        colspan="8"
                                        class="table-empty">

                                        <i
                                            data-lucide="clipboard-list">
                                        </i>

                                        <span>
                                            Henüz kayıtlı görev bulunmuyor.
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

