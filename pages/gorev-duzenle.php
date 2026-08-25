<?php

session_start();

if (!isset($_SESSION["kullanici_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";

$mesaj = "";


/* ==========================================
   GÖREV ID KONTROLÜ
========================================== */

$id = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);

if (!$id) {
    header("Location: gorevler.php");
    exit;
}


/* ==========================================
   MEVCUT GÖREVİ GETİR
========================================== */

$sql = "SELECT * FROM gorevler WHERE id = ?";

$stmt = $db->prepare($sql);
$stmt->execute([$id]);

$gorev = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$gorev) {
    header("Location: gorevler.php");
    exit;
}


/* ==========================================
   PERSONELLERİ GETİR
   Aktif personeller + mevcut görevdeki personel
========================================== */

$sql = "SELECT
            id,
            ad,
            soyad,
            durum
        FROM personeller
        WHERE durum = 'aktif'
           OR id = ?
        ORDER BY ad ASC, soyad ASC";

$stmt = $db->prepare($sql);
$stmt->execute([$gorev["personel_id"]]);

$personeller = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* ==========================================
   GÖREV GÜNCELLE
========================================== */

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $baslik = trim($_POST["baslik"]);
    $aciklama = trim($_POST["aciklama"]);
    $personel_id = $_POST["personel_id"];
    $baslangic_tarihi = $_POST["baslangic_tarihi"];
    $teslim_tarihi = $_POST["teslim_tarihi"];
    $durum = $_POST["durum"];
    $oncelik = $_POST["oncelik"];


    if ($baslik == "") {

        $mesaj = "Görev başlığı boş bırakılamaz.";

    } elseif ($personel_id == "") {

        $mesaj = "Lütfen görev atanacak personeli seçin.";

    } elseif ($baslangic_tarihi == "") {

        $mesaj = "Başlangıç tarihi seçilmelidir.";

    } elseif ($teslim_tarihi == "") {

        $mesaj = "Teslim tarihi seçilmelidir.";

    } elseif ($teslim_tarihi < $baslangic_tarihi) {

        $mesaj = "Teslim tarihi başlangıç tarihinden önce olamaz.";

    } else {

        $sql = "UPDATE gorevler
                SET
                    baslik = ?,
                    aciklama = ?,
                    personel_id = ?,
                    baslangic_tarihi = ?,
                    teslim_tarihi = ?,
                    durum = ?,
                    oncelik = ?
                WHERE id = ?";

        $stmt = $db->prepare($sql);

        if (
            $stmt->execute([
                $baslik,
                $aciklama,
                $personel_id,
                $baslangic_tarihi,
                $teslim_tarihi,
                $durum,
                $oncelik,
                $id
            ])
        ) {

            header("Location: gorevler.php");
            exit;

        } else {

            $mesaj = "Görev güncellenirken bir hata oluştu.";

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

    <title>Görev Düzenle - Birim Takip</title>

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
                class="sidebar-link">

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
                class="sidebar-link active">

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


        <!-- ÜST BAR -->

        <header class="topbar">

            <div class="topbar-left">

                <button
                    type="button"
                    class="menu-button">

                    <i data-lucide="menu"></i>

                </button>

                <span class="page-title">
                    Görev Düzenle
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
                    Görev Düzenle
                </h1>

                <p>
                    Mevcut görev bilgilerini güncelleyin.
                </p>

            </div>


            <!-- FORM KARTI -->

            <div class="large-card add-form-card">


                <div class="card-title-area">

                    <div>

                        <h2>
                            Görev Bilgileri
                        </h2>

                        <p>
                            Görev detaylarını değiştirebilirsiniz.
                        </p>

                    </div>


                    <a
                        href="gorevler.php"
                        class="view-button">

                        <i data-lucide="arrow-left"></i>

                        Görevlere Dön

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


                <form
                    method="POST"
                    class="data-form">


                    <!-- BAŞLIK -->

                    <div class="form-group">

                        <label for="baslik">
                            Görev Başlığı
                        </label>

                        <div class="form-input-wrapper">

                            <i
                                data-lucide="clipboard-list"
                                class="form-input-icon">
                            </i>

                            <input
                                type="text"
                                id="baslik"
                                name="baslik"
                                value="<?php echo htmlspecialchars($gorev["baslik"]); ?>"
                                required>

                        </div>

                    </div>


                    <!-- AÇIKLAMA -->

                    <div class="form-group">

                        <label for="aciklama">
                            Açıklama
                        </label>

                        <div class="form-input-wrapper">

                            <i
                                data-lucide="align-left"
                                class="form-input-icon">
                            </i>

                            <textarea
                                id="aciklama"
                                name="aciklama"
                                rows="5"
                                placeholder="Görev hakkında açıklama girin"><?php echo htmlspecialchars($gorev["aciklama"] ?? ""); ?></textarea>

                        </div>

                    </div>


                    <!-- PERSONEL -->

                    <div class="form-group">

                        <label for="personel_id">
                            Görev Atanacak Personel
                        </label>

                        <div class="form-input-wrapper">

                            <i
                                data-lucide="user"
                                class="form-input-icon">
                            </i>

                            <select
                                id="personel_id"
                                name="personel_id"
                                required>

                                <option value="">
                                    Personel seçin
                                </option>


                                <?php foreach ($personeller as $personel): ?>

                                    <option
                                        value="<?php echo $personel["id"]; ?>"
                                        <?php
                                        echo (
                                            $gorev["personel_id"]
                                            == $personel["id"]
                                        )
                                            ? "selected"
                                            : "";
                                        ?>>

                                        <?php

                                        echo htmlspecialchars(
                                            $personel["ad"] . " " .
                                            $personel["soyad"]
                                        );

                                        if (
                                            $personel["durum"] == "pasif"
                                        ) {
                                            echo " (Arşiv)";
                                        }

                                        ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                    </div>


                    <!-- BAŞLANGIÇ TARİHİ -->

                    <div class="form-group">

                        <label for="baslangic_tarihi">
                            Başlangıç Tarihi
                        </label>

                        <div class="form-input-wrapper">

                            <i
                                data-lucide="calendar-days"
                                class="form-input-icon">
                            </i>

                            <input
                                type="date"
                                id="baslangic_tarihi"
                                name="baslangic_tarihi"
                                value="<?php echo htmlspecialchars($gorev["baslangic_tarihi"] ?? ""); ?>"
                                required>

                        </div>

                    </div>


                    <!-- TESLİM TARİHİ -->

                    <div class="form-group">

                        <label for="teslim_tarihi">
                            Teslim Tarihi
                        </label>

                        <div class="form-input-wrapper">

                            <i
                                data-lucide="calendar-check"
                                class="form-input-icon">
                            </i>

                            <input
                                type="date"
                                id="teslim_tarihi"
                                name="teslim_tarihi"
                                value="<?php echo htmlspecialchars($gorev["teslim_tarihi"] ?? ""); ?>"
                                required>

                        </div>

                    </div>


                    <!-- DURUM -->

                    <div class="form-group">

                        <label for="durum">
                            Durum
                        </label>

                        <div class="form-input-wrapper">

                            <i
                                data-lucide="circle-dot"
                                class="form-input-icon">
                            </i>

                            <select
                                id="durum"
                                name="durum">

                                <option
                                    value="Bekliyor"
                                    <?php
                                    echo $gorev["durum"] == "Bekliyor"
                                        ? "selected"
                                        : "";
                                    ?>>

                                    Bekliyor

                                </option>

                                <option
                                    value="Devam Ediyor"
                                    <?php
                                    echo $gorev["durum"] == "Devam Ediyor"
                                        ? "selected"
                                        : "";
                                    ?>>

                                    Devam Ediyor

                                </option>

                                <option
                                    value="Tamamlandı"
                                    <?php
                                    echo $gorev["durum"] == "Tamamlandı"
                                        ? "selected"
                                        : "";
                                    ?>>

                                    Tamamlandı

                                </option>

                            </select>

                        </div>

                    </div>


                    <!-- ÖNCELİK -->

                    <div class="form-group">

                        <label for="oncelik">
                            Öncelik
                        </label>

                        <div class="form-input-wrapper">

                            <i
                                data-lucide="flag"
                                class="form-input-icon">
                            </i>

                            <select
                                id="oncelik"
                                name="oncelik">

                                <option
                                    value="Normal"
                                    <?php
                                    echo $gorev["oncelik"] == "Normal"
                                        ? "selected"
                                        : "";
                                    ?>>

                                    Normal

                                </option>

                                <option
                                    value="Düşük"
                                    <?php
                                    echo $gorev["oncelik"] == "Düşük"
                                        ? "selected"
                                        : "";
                                    ?>>

                                    Düşük

                                </option>

                                <option
                                    value="Yüksek"
                                    <?php
                                    echo $gorev["oncelik"] == "Yüksek"
                                        ? "selected"
                                        : "";
                                    ?>>

                                    Yüksek

                                </option>

                            </select>

                        </div>

                    </div>


                    <!-- BUTONLAR -->

                    <div class="form-actions">

                        <a
                            href="gorevler.php"
                            class="cancel-button">

                            İptal

                        </a>


                        <button
                            type="submit"
                            class="save-button">

                            <i data-lucide="save"></i>

                            Değişiklikleri Kaydet

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