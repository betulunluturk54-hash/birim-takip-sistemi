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


/* ==========================================
   PERSONELİ BUL
========================================== */

$sql = "SELECT
            id,
            ad,
            soyad
        FROM personeller
        WHERE kullanici_id = ?";

$stmt = $db->prepare($sql);
$stmt->execute([
    $_SESSION["kullanici_id"]
]);

$personel = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$personel) {
    die("Bu kullanıcıya bağlı personel kaydı bulunamadı.");
}

$personel_id = $personel["id"];


/* ==========================================
   GÖREV ID KONTROLÜ
========================================== */

$gorev_id = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);

if (!$gorev_id) {
    header("Location: personel-gorevlerim.php");
    exit;
}


/* ==========================================
   SADECE BU PERSONELE AİT GÖREVİ GETİR
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
        WHERE id = ?
        AND personel_id = ?";

$stmt = $db->prepare($sql);

$stmt->execute([
    $gorev_id,
    $personel_id
]);

$gorev = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$gorev) {
    header("Location: personel-gorevlerim.php");
    exit;
}


/* ==========================================
   DURUM GÜNCELLE
========================================== */

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $yeni_durum = trim(
        $_POST["durum"] ?? ""
    );


    $izin_verilen_durumlar = [
        "Bekliyor",
        "Devam Ediyor",
        "Tamamlandı"
    ];


    if (
        !in_array(
            $yeni_durum,
            $izin_verilen_durumlar,
            true
        )
    ) {

        $mesaj =
            "Geçersiz görev durumu.";

    } else {

        $sql = "UPDATE gorevler
                SET durum = ?
                WHERE id = ?
                AND personel_id = ?";

        $stmt = $db->prepare($sql);


        if (
            $stmt->execute([
                $yeni_durum,
                $gorev_id,
                $personel_id
            ])
        ) {

            header(
                "Location: personel-gorevlerim.php"
            );

            exit;

        } else {

            $mesaj =
                "Görev durumu güncellenirken bir hata oluştu.";
        }
    }
}


/* ==========================================
   DURUM GÖRÜNÜMÜ
========================================== */

$mevcut_durum =
    $gorev["durum"] ?? "";


if ($mevcut_durum === "Tamamlandı") {

    $durum_sinifi = "completed";
    $durum_ikonu = "circle-check";

} elseif ($mevcut_durum === "Devam Ediyor") {

    $durum_sinifi = "ongoing";
    $durum_ikonu = "loader-circle";

} else {

    $durum_sinifi = "waiting";
    $durum_ikonu = "clock-3";
}

?>

<!DOCTYPE html>
<html lang="tr">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        Görev Durumu - Birim Takip
    </title>

    <script src="https://unpkg.com/lucide@latest"></script>

    <link
        rel="stylesheet"
        href="../css/style.css">

    <style>

        /* =========================================================
           MEVCUT DURUM
        ========================================================= */

        .current-task-status {

            display: inline-flex;

            align-items: center;

            gap: 7px;

            margin-top: 10px;

            padding: 7px 12px;

            border-radius: 999px;

            font-size: 12px;

            font-weight: 700;

            border: 1px solid transparent;
        }


        .current-task-status svg {

            width: 15px;

            height: 15px;
        }


        .current-task-status.completed {

            background: #eaf8f0;

            color: #21804c;

            border-color: #d2efdf;
        }


        .current-task-status.ongoing {

            background: #eaf1ff;

            color: #3372f4;

            border-color: #d2def7;
        }


        .current-task-status.waiting {

            background: #fff6e5;

            color: #a97212;

            border-color: #f5e3bc;
        }


        /* =========================================================
           KULLANICI AÇILIR MENÜ
        ========================================================= */

        .user-dropdown-wrapper {

            position: relative;
        }


        .user-dropdown-wrapper .user-area {

            cursor: pointer;
        }


        .user-dropdown {

            position: absolute;

            top: calc(100% + 10px);

            right: 0;

            min-width: 165px;

            padding: 6px;

            background: var(--white);

            border:
                1px solid var(--border);

            border-radius: 11px;

            box-shadow:
                0 10px 28px
                rgba(25, 45, 80, 0.12);

            opacity: 0;

            visibility: hidden;

            transform:
                translateY(-5px);

            transition:
                opacity 0.18s ease,
                transform 0.18s ease,
                visibility 0.18s ease;

            z-index: 3000;
        }


        .user-dropdown.show {

            opacity: 1;

            visibility: visible;

            transform:
                translateY(0);
        }


        .user-dropdown-item {

            display: flex;

            align-items: center;

            gap: 10px;

            width: 100%;

            padding: 10px 11px;

            border-radius: 8px;

            color: #b33f3f;

            font-size: 13px;

            font-weight: 600;

            transition:
                background-color 0.2s ease,
                color 0.2s ease;
        }


        .user-dropdown-item:hover {

            background: #fff1f1;

            color: #a73535;
        }


        .user-dropdown-item svg {

            width: 17px;

            height: 17px;

            flex-shrink: 0;
        }


        /* =========================================================
           DARK MODE
        ========================================================= */

        body.dark-mode .current-task-status.completed {

            background: #143022;

            color: #7bd5a2;

            border-color: #23523b;
        }


        body.dark-mode .current-task-status.ongoing {

            background: #172c50;

            color: #79a2ff;

            border-color: #355277;
        }


        body.dark-mode .current-task-status.waiting {

            background: #382d16;

            color: #e3bf6b;

            border-color: #5a481f;
        }


        body.dark-mode .user-dropdown {

            background: #121b2d;

            border-color: #26344a;

            box-shadow:
                0 12px 30px
                rgba(0, 0, 0, 0.28);
        }


        body.dark-mode .user-dropdown-item {

            color: #f0a2a2;
        }


        body.dark-mode .user-dropdown-item:hover {

            background: #321d20;

            color: #ffb0b0;
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


            <!-- PROFİL -->

            <a
                href="personel-profil.php"
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


        <header class="topbar">

            <div class="topbar-left">

                <button
                    type="button"
                    class="menu-button">

                    <i data-lucide="menu"></i>

                </button>

                <span class="page-title">
                    Görev Durumu
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

                <div class="user-dropdown-wrapper">

                    <div
                        class="user-area"
                        id="userArea">

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


                        <i
                            data-lucide="chevron-down"
                            class="user-arrow">
                        </i>

                    </div>


                    <!-- AÇILIR MENÜ -->

                    <div
                        class="user-dropdown"
                        id="userDropdown">

                        <a
                            href="../logout.php"
                            class="user-dropdown-item">

                            <i
                                data-lucide="log-out">
                            </i>

                            <span>
                                Çıkış Yap
                            </span>

                        </a>

                    </div>

                </div>

            </div>

        </header>


        <!-- ================= SAYFA ================= -->

        <section class="page-content">


            <div class="welcome-section">

                <h1>
                    Görev Durumunu Güncelle
                </h1>

                <p>
                    Görevin mevcut durumunu görüntüleyebilir ve güncelleyebilirsiniz.
                </p>

            </div>


            <div class="large-card add-form-card">


                <div class="card-title-area">

                    <div>

                        <h2>

                            <?php

                            echo htmlspecialchars(
                                $gorev["baslik"]
                            );

                            ?>

                        </h2>

                        <p>
                            Görev bilgileri ve durum yönetimi
                        </p>


                        <!-- MEVCUT DURUM -->

                        <div
                            class="
                                current-task-status
                                <?= $durum_sinifi ?>
                            "
                        >

                            <i
                                data-lucide="<?= $durum_ikonu ?>"
                            ></i>

                            Mevcut Durum:

                            <?= htmlspecialchars(
                                $mevcut_durum
                            ) ?>

                        </div>

                    </div>


                    <a
                        href="personel-gorevlerim.php"
                        class="view-button">

                        <i data-lucide="arrow-left"></i>

                        Görevlerime Dön

                    </a>

                </div>


                <!-- MESAJ -->

                <?php if ($mesaj != ""): ?>

                    <div class="form-message error">

                        <i
                            data-lucide="circle-alert">
                        </i>

                        <span>

                            <?php

                            echo htmlspecialchars(
                                $mesaj
                            );

                            ?>

                        </span>

                    </div>

                <?php endif; ?>


                <!-- GÖREV BİLGİLERİ -->

                <div class="task-detail-info">


                    <!-- AÇIKLAMA -->

                    <div class="task-detail-row">

                        <span>
                            Açıklama
                        </span>

                        <strong>

                            <?php

                            echo $gorev["aciklama"]

                                ? nl2br(
                                    htmlspecialchars(
                                        $gorev["aciklama"]
                                    )
                                )

                                : "Açıklama bulunmuyor.";

                            ?>

                        </strong>

                    </div>


                    <!-- BAŞLANGIÇ -->

                    <div class="task-detail-row">

                        <span>
                            Başlangıç Tarihi
                        </span>

                        <strong>

                            <?php

                            echo $gorev[
                                "baslangic_tarihi"
                            ]

                                ? date(
                                    "d.m.Y",
                                    strtotime(
                                        $gorev[
                                            "baslangic_tarihi"
                                        ]
                                    )
                                )

                                : "-";

                            ?>

                        </strong>

                    </div>


                    <!-- TESLİM -->

                    <div class="task-detail-row">

                        <span>
                            Teslim Tarihi
                        </span>

                        <strong>

                            <?php

                            echo $gorev[
                                "teslim_tarihi"
                            ]

                                ? date(
                                    "d.m.Y",
                                    strtotime(
                                        $gorev[
                                            "teslim_tarihi"
                                        ]
                                    )
                                )

                                : "-";

                            ?>

                        </strong>

                    </div>


                    <!-- ÖNCELİK -->

                    <div class="task-detail-row">

                        <span>
                            Öncelik
                        </span>

                        <strong>

                            <?php

                            echo htmlspecialchars(
                                $gorev["oncelik"]
                            );

                            ?>

                        </strong>

                    </div>


                </div>


                <!-- DURUM FORMU -->

                <form
                    method="POST"
                    class="data-form">


                    <div class="form-group">

                        <label for="durum">
                            Yeni Görev Durumu
                        </label>


                        <div class="form-input-wrapper">

                            <i
                                data-lucide="circle-dot"
                                class="form-input-icon">
                            </i>


                            <select
                                id="durum"
                                name="durum"
                                required
                            >


                                <option
                                    value="Bekliyor"

                                    <?php

                                    echo $gorev["durum"]
                                        == "Bekliyor"
                                        ? "selected"
                                        : "";

                                    ?>
                                >

                                    Bekliyor

                                </option>


                                <option
                                    value="Devam Ediyor"

                                    <?php

                                    echo $gorev["durum"]
                                        == "Devam Ediyor"
                                        ? "selected"
                                        : "";

                                    ?>
                                >

                                    Devam Ediyor

                                </option>


                                <option
                                    value="Tamamlandı"

                                    <?php

                                    echo $gorev["durum"]
                                        == "Tamamlandı"
                                        ? "selected"
                                        : "";

                                    ?>
                                >

                                    Tamamlandı

                                </option>


                            </select>

                        </div>

                    </div>


                    <!-- BUTONLAR -->

                    <div class="form-actions">


                        <a
                            href="personel-gorevlerim.php"
                            class="cancel-button">

                            İptal

                        </a>


                        <button
                            type="submit"
                            class="save-button">

                            <i data-lucide="save"></i>

                            Durumu Kaydet

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


    /* =====================================================
       KULLANICI MENÜSÜ
    ===================================================== */

    const userArea =
        document.getElementById(
            "userArea"
        );

    const userDropdown =
        document.getElementById(
            "userDropdown"
        );


    if (
        userArea &&
        userDropdown
    ) {

        userArea.addEventListener(
            "click",
            function (event) {

                event.stopPropagation();

                userDropdown.classList.toggle(
                    "show"
                );

            }
        );


        document.addEventListener(
            "click",
            function () {

                userDropdown.classList.remove(
                    "show"
                );

            }
        );


        userDropdown.addEventListener(
            "click",
            function (event) {

                event.stopPropagation();

            }
        );

    }

</script>


</body>

</html>
