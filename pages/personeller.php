<?php

session_start();

if (!isset($_SESSION["kullanici_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";


/* ==========================================
   GİRİŞ YAPAN KULLANICI
========================================== */

$stmt = $db->prepare("
    SELECT
        id,
        ad,
        soyad,
        kullanici_adi,
        rol
    FROM kullanicilar
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([
    $_SESSION["kullanici_id"]
]);

$giris_yapan_kullanici = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$giris_yapan_kullanici) {
    session_destroy();
    header("Location: ../login.php");
    exit;
}


/* ==========================================
   AD SOYAD
========================================== */

$kullanici_ad_soyad = trim(
    ($giris_yapan_kullanici["ad"] ?? "") .
    " " .
    ($giris_yapan_kullanici["soyad"] ?? "")
);


/* Ad soyad boşsa kullanıcı adını göster */

if ($kullanici_ad_soyad === "") {

    $kullanici_ad_soyad =
        $giris_yapan_kullanici["kullanici_adi"];

}


/* ==========================================
   ROL ETİKETİ
========================================== */

if (
    in_array(
        $giris_yapan_kullanici["rol"],
        ["yonetici", "admin"],
        true
    )
) {

    $rol_etiketi = "Yönetici";

} elseif (
    $giris_yapan_kullanici["rol"] === "personel"
) {

    $rol_etiketi = "Personel";

} else {

    $rol_etiketi = "Kullanıcı";

}


/* ==========================================
   PERSONELLERİ VERİTABANINDAN ÇEK
========================================== */

$sql = "
    SELECT
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
    ORDER BY p.id DESC
";

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
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Personeller - Birim Takip
    </title>

    <script src="https://unpkg.com/lucide@latest"></script>

    <link
        rel="stylesheet"
        href="../css/style.css"
    >

    <style>

        /* =====================================================
           DURUMA GÖRE RENKLER
        ===================================================== */

        .personel-gorev-durum.bekliyor {
            background: #fff6e5;
            color: #a97212;
            border-color: #f0d9a5;
        }


        .personel-gorev-durum.devam-ediyor {
            background: #eaf1ff;
            color: #3372f4;
            border-color: #cbd9fa;
        }


        .personel-gorev-durum.tamamlandi {
            background: #eaf8f0;
            color: #21804c;
            border-color: #c8ead7;
        }


        /* =====================================================
           DARK MODE
        ===================================================== */

        body.dark-mode .personel-gorev-adi {
            color: #e5ebf3;
        }


        body.dark-mode .personel-gorev-durum {
            background: #121b2d;
            border-color: #2a394f;
            color: #e5ebf3;
        }


        body.dark-mode .personel-gorev-durum.bekliyor {
            background: #382d16;
            color: #e3bf6b;
            border-color: #5a481f;
        }


        body.dark-mode .personel-gorev-durum.devam-ediyor {
            background: #172c50;
            color: #9db8ff;
            border-color: #304c80;
        }


        body.dark-mode .personel-gorev-durum.tamamlandi {
            background: #183a29;
            color: #7bd9a4;
            border-color: #2c6044;
        }

    </style>

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

                <h2>
                    BİRİM TAKİP
                </h2>

                <span>
                    Yönetim Sistemi
                </span>

            </div>

        </div>


        <nav class="sidebar-menu">


            <!-- ANA SAYFA -->

            <a
                href="../dashboard.php"
                class="sidebar-link"
            >

                <i
                    data-lucide="house"
                    class="menu-icon"
                ></i>

                <span>
                    Ana Sayfa
                </span>

            </a>


            <!-- BİRİMLER -->

            <a
                href="birimler.php"
                class="sidebar-link"
            >

                <i
                    data-lucide="building-2"
                    class="menu-icon"
                ></i>

                <span>
                    Birimler
                </span>

            </a>


            <!-- PERSONELLER -->

            <a
                href="personeller.php"
                class="sidebar-link active"
            >

                <i
                    data-lucide="users"
                    class="menu-icon"
                ></i>

                <span>
                    Personeller
                </span>

            </a>


            <!-- GÖREVLER -->

            <a
                href="gorevler.php"
                class="sidebar-link"
            >

                <i
                    data-lucide="clipboard-list"
                    class="menu-icon"
                ></i>

                <span>
                    Görevler
                </span>

            </a>


            <!-- EVRAKLAR -->

            <a
                href="evraklar.php"
                class="sidebar-link"
            >

                <i
                    data-lucide="file-text"
                    class="menu-icon"
                ></i>

                <span>
                    Evraklar
                </span>

            </a>
<!-- PROFİL -->

<a
    href="../profil.php"
    class="sidebar-link"
>

    <i
        data-lucide="user-round"
        class="menu-icon"
    ></i>

    <span>
        Profilim
    </span>

</a>
        </nav>


        <!-- SIDEBAR ALT -->

        <div class="sidebar-bottom">

            <a
                href="../logout.php"
                class="sidebar-link logout"
            >

                <i
                    data-lucide="log-out"
                    class="menu-icon"
                ></i>

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
                    class="menu-button"
                >

                    <i data-lucide="menu"></i>

                </button>

                <span class="page-title">
                    Personeller
                </span>

            </div>


            <div class="topbar-right">

                <?php require_once __DIR__ . "/../includes/duyuru-sistemi.php"; ?>


                <!-- TEMA -->

                <div class="theme-switch">

                    <button
                        type="button"
                        class="theme-button active"
                        id="lightTheme"
                    >

                        <i data-lucide="sun"></i>

                    </button>


                    <button
                        type="button"
                        class="theme-button"
                        id="darkTheme"
                    >

                        <i data-lucide="moon"></i>

                    </button>

                </div>


                <div class="topbar-divider"></div>


                <!-- KULLANICI -->

                <div class="user-area">

                    <div class="user-avatar">

                        <?php

                        $ilk_harf =
                            mb_substr(
                                $giris_yapan_kullanici["ad"]
                                ?: $giris_yapan_kullanici["kullanici_adi"],
                                0,
                                1,
                                "UTF-8"
                            );

                        echo htmlspecialchars(
                            mb_strtoupper(
                                $ilk_harf,
                                "UTF-8"
                            )
                        );

                        ?>

                    </div>


                    <div class="user-text">

                        <strong>

                            <?= htmlspecialchars(
                                $kullanici_ad_soyad,
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>

                        </strong>


                        <span>

                            <?= htmlspecialchars(
                                $rol_etiketi,
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>

                        </span>

                    </div>


                    <i
                        data-lucide="chevron-down"
                        class="user-arrow"
                    ></i>

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


                    <div
                        style="
                            display: flex;
                            gap: 10px;
                        "
                    >

                        <!-- PERSONEL ARŞİVİ -->

                        <a
                            href="personel-arsiv.php"
                            class="view-button"
                        >

                            <i data-lucide="archive"></i>

                            Personel Arşivi

                        </a>


                        <!-- YENİ PERSONEL -->

                        <a
                            href="personel-ekle.php"
                            class="view-button"
                        >

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


                                <?php foreach (
                                    $personeller
                                    as $personel
                                ): ?>


                                    <?php

                                    $personelId =
                                        (int) $personel["id"];

                                    $gorev =
                                        $gorevDurumlari[
                                            $personelId
                                        ]
                                        ?? null;

                                    ?>


                                    <tr>


                                        <!-- SIRA -->

                                        <td>

                                            <?= $sira ?>

                                        </td>


                                        <!-- PERSONEL -->

                                        <td>

                                            <div class="table-name">

                                                <div class="table-icon">

                                                    <i
                                                        data-lucide="user"
                                                    ></i>

                                                </div>

                                                <span>

                                                    <?= htmlspecialchars(
                                                        $personel["ad"]
                                                        . " "
                                                        . $personel["soyad"]
                                                    ) ?>

                                                </span>

                                            </div>

                                        </td>


                                        <!-- BİRİM -->

                                        <td>

                                            <?= htmlspecialchars(
                                                $personel["birim_adi"]
                                                ?? "-"
                                            ) ?>

                                        </td>


                                        <!-- GÖREV -->

                                        <td>

                                            <?= htmlspecialchars(
                                                $personel["gorev"]
                                                ?? "-"
                                            ) ?>

                                        </td>


                                        <!-- TELEFON -->

                                        <td>

                                            <?= htmlspecialchars(
                                                $personel["telefon"]
                                                ?? "-"
                                            ) ?>

                                        </td>


                                        <!-- E-POSTA -->

                                        <td>

                                            <?= htmlspecialchars(
                                                $personel["email"]
                                                ?? "-"
                                            ) ?>

                                        </td>


                                        <!-- İŞLEMLER -->

                                        <td>

                                            <div class="table-actions">


                                                <!-- DÜZENLE -->

                                                <a
                                                    href="personel-duzenle.php?id=<?= $personel["id"] ?>"
                                                    class="action-button edit"
                                                >

                                                    <i
                                                        data-lucide="pencil"
                                                    ></i>

                                                    Düzenle

                                                </a>


                                                <!-- ARŞİVLE -->

                                                <a
                                                    href="personel-arsivle.php?id=<?= $personel["id"] ?>"
                                                    class="action-button delete"
                                                    onclick="return confirm('Bu personeli arşivlemek istediğinize emin misiniz?');"
                                                >

                                                    <i
                                                        data-lucide="archive"
                                                    ></i>

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
                                        class="table-empty"
                                    >

                                        <i
                                            data-lucide="users"
                                        ></i>

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


<script src="../js/script.js"></script>

<script>

    lucide.createIcons();

</script>


</body>

</html>