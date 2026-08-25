<?php

session_start();

if (!isset($_SESSION["kullanici_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";


/* ==========================================
   ARŞİVLENMİŞ PERSONELLERİ GETİR
========================================== */

$sql = "SELECT
            p.id,
            p.ad,
            p.soyad,
            p.telefon,
            p.email,
            p.gorev,
            p.kayit_tarihi,
            p.ayrilma_tarihi,
            b.birim_adi
        FROM personeller p
        LEFT JOIN birimler b
            ON p.birim_id = b.id
        WHERE p.durum = 'pasif'
        ORDER BY p.ayrilma_tarihi DESC";

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

    <title>Personel Arşivi - Birim Takip</title>

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
                    Personel Arşivi
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
                    Personel Arşivi
                </h1>

                <p>
                    Kurumdan ayrılmış personellerin kayıtları.
                </p>

            </div>


            <div class="large-card">


                <div class="card-title-area">

                    <div>

                        <h2>
                            Arşivlenen Personeller
                        </h2>

                        <p>
                            Pasif durumdaki personel kayıtları
                        </p>

                    </div>


                    <a
                        href="personeller.php"
                        class="view-button">

                        <i data-lucide="arrow-left"></i>

                        Aktif Personeller

                    </a>

                </div>


                <div class="table-container">

                    <table class="data-table">


<thead>

    <tr>

        <th>#</th>

        <th>Personel</th>

        <th>Birim</th>

        <th>Görev</th>

        <th>Telefon</th>

        <th>E-posta</th>

        <th>Kayıt Tarihi</th>

        <th>İşten Ayrılma Tarihi</th>

        <th>İşlem</th>

    </tr>

</thead>


                        <tbody>


                            <?php if (count($personeller) > 0): ?>

                                <?php $sira = 1; ?>


                                <?php foreach ($personeller as $personel): ?>


<tr>

    <!-- SIRA -->

    <td>
        <?php echo $sira; ?>
    </td>


    <!-- PERSONEL -->

    <td>

        <div class="table-name">

            <div class="table-icon">

                <i data-lucide="user"></i>

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


    <!-- KAYIT TARİHİ -->

    <td>

        <?php

        echo $personel["kayit_tarihi"]
            ? date(
                "d.m.Y",
                strtotime(
                    $personel["kayit_tarihi"]
                )
            )
            : "-";

        ?>

    </td>


    <!-- İŞTEN AYRILMA TARİHİ -->

    <td>

        <?php

        echo $personel["ayrilma_tarihi"]
            ? date(
                "d.m.Y",
                strtotime(
                    $personel["ayrilma_tarihi"]
                )
            )
            : "-";

        ?>

    </td>


    <!-- GERİ AL -->

    <td>

        <a
            href="personel-geri-al.php?id=<?php echo $personel["id"]; ?>"
            class="action-button edit">

            <i data-lucide="rotate-ccw"></i>

            Geri Al

        </a>

    </td>

</tr>


                                    <?php $sira++; ?>


                                <?php endforeach; ?>


                            <?php else: ?>


                                <tr>

                                    <td
                                     colspan="9"
                                     class="table-empty">

                                        <i
                                            data-lucide="archive">
                                        </i>

                                        <span>
                                            Arşivlenmiş personel bulunmuyor.
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