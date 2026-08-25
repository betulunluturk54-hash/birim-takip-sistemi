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


/* =========================================================
   GİRİŞ YAPAN PERSONELİ BUL
========================================================= */

$sql = "
    SELECT
        id,
        ad,
        soyad
    FROM personeller
    WHERE kullanici_id = ?
";

$stmt = $db->prepare($sql);

$stmt->execute([
    $_SESSION["kullanici_id"]
]);

$personel = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$personel) {
    die("Bu kullanıcıya bağlı personel kaydı bulunamadı.");
}

$personel_id = (int) $personel["id"];


/* =========================================================
   DOSYA GÖRÜNTÜLEME
   Örnek:
   personel-evraklarim.php?dosya=5
========================================================= */

if (isset($_GET["dosya"])) {

    $dosya_id = filter_input(
        INPUT_GET,
        "dosya",
        FILTER_VALIDATE_INT
    );

    if (!$dosya_id) {
        http_response_code(400);
        exit("Geçersiz evrak.");
    }


    /* Sadece giriş yapan personele ait dosyayı getir */

    $sql = "
        SELECT
            dosya_yolu
        FROM evraklar
        WHERE id = ?
        AND personel_id = ?
        LIMIT 1
    ";

    $stmt = $db->prepare($sql);

    $stmt->execute([
        $dosya_id,
        $personel_id
    ]);

    $dosya = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$dosya) {
        http_response_code(404);
        exit("Evrak bulunamadı.");
    }


    if (empty($dosya["dosya_yolu"])) {
        http_response_code(404);
        exit("Bu evraka ait dosya bulunmuyor.");
    }


    /*
        evraklar.php dosyasında yükleme yolu:

        uploads/evraklar/dosya.pdf

        olduğu için pages klasörünün içinden dosyayı buluyoruz.
    */

    $dosya_yolu = ltrim(
        $dosya["dosya_yolu"],
        "/\\"
    );

    $gercek_dosya = __DIR__ . "/" . $dosya_yolu;


    if (!is_file($gercek_dosya)) {
        http_response_code(404);
        exit("Dosya sunucuda bulunamadı.");
    }


    if (!is_readable($gercek_dosya)) {
        http_response_code(403);
        exit("Dosya okunamıyor.");
    }


    /* Dosya uzantısı */

    $uzanti = strtolower(
        pathinfo(
            $gercek_dosya,
            PATHINFO_EXTENSION
        )
    );


    /* MIME türleri */

    $mime_turleri = [
        "pdf" =>
            "application/pdf",

        "jpg" =>
            "image/jpeg",

        "jpeg" =>
            "image/jpeg",

        "png" =>
            "image/png",

        "doc" =>
            "application/msword",

        "docx" =>
            "application/vnd.openxmlformats-officedocument.wordprocessingml.document",

        "xls" =>
            "application/vnd.ms-excel",

        "xlsx" =>
            "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
    ];


    $mime = $mime_turleri[$uzanti]
        ?? "application/octet-stream";


    $dosya_adi = basename($gercek_dosya);


    header("Content-Type: " . $mime);

    header(
        "Content-Length: " . filesize($gercek_dosya)
    );

    header(
        'Content-Disposition: inline; filename="' .
        str_replace(
            '"',
            "",
            $dosya_adi
        ) .
        '"'
    );

    header(
        "Cache-Control: private, max-age=0, must-revalidate"
    );

    header("Pragma: public");


    readfile($gercek_dosya);

    exit;
}


/* =========================================================
   PERSONELE AİT EVRAKLARI GETİR
========================================================= */

$sql = "
    SELECT
        id,
        evrak_adi,
        evrak_no,
        aciklama,
        tarih,
        dosya_yolu,
        durum,
        red_nedeni
    FROM evraklar
    WHERE personel_id = ?
    ORDER BY tarih DESC, id DESC
";

$stmt = $db->prepare($sql);

$stmt->execute([
    $personel_id
]);

$evraklar = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        Evraklarım - Birim Takip
    </title>

    <script src="https://unpkg.com/lucide@latest"></script>

    <link
        rel="stylesheet"
        href="../css/style.css"
    >

    <style>

        /* =====================================================
           KULLANICI AÇILIR MENÜ
        ===================================================== */

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

            border: 1px solid var(--border);

            border-radius: 11px;

            box-shadow:
                0 10px 28px rgba(25, 45, 80, 0.12);

            opacity: 0;
            visibility: hidden;

            transform: translateY(-5px);

            transition:
                opacity 0.18s ease,
                transform 0.18s ease,
                visibility 0.18s ease;

            z-index: 3000;
        }

        .user-dropdown.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
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
        }


        /* =====================================================
           EVRAK DURUMLARI
        ===================================================== */

        .personel-evrak-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;

            padding: 6px 10px;

            border-radius: 999px;

            font-size: 11px;
            font-weight: 700;

            white-space: nowrap;

            border: 1px solid transparent;
        }

        .personel-evrak-status svg {
            width: 14px;
            height: 14px;
        }

        .personel-evrak-status.pending {
            background: #fff6e5;
            color: #a97212;
            border-color: #f5e3bc;
        }

        .personel-evrak-status.approved {
            background: #eaf8f0;
            color: #21804c;
            border-color: #d2efdf;
        }

        .personel-evrak-status.rejected {
            background: #fff0f0;
            color: #b64545;
            border-color: #f1d0d0;
        }


        /* =====================================================
           RED NEDENİ
        ===================================================== */

        .personel-red-reason {
            margin-top: 7px;

            max-width: 220px;

            font-size: 11px;

            line-height: 1.4;

            color: #b64545;
        }


        /* =====================================================
           DOSYA BUTONU
        ===================================================== */

        .personel-file-button {
            display: inline-flex;

            align-items: center;

            gap: 6px;

            white-space: nowrap;
        }


        .personel-file-button svg {
            width: 15px;
            height: 15px;
        }


        /* =====================================================
           DARK MODE
        ===================================================== */

        body.dark-mode .user-dropdown {
            background: #121b2d;
            border-color: #26344a;

            box-shadow:
                0 12px 30px rgba(0, 0, 0, 0.28);
        }

        body.dark-mode .user-dropdown-item {
            color: #f0a2a2;
        }

        body.dark-mode .user-dropdown-item:hover {
            background: #321d20;
            color: #ffb0b0;
        }

        body.dark-mode .personel-evrak-status.pending {
            background: #382d16;
            color: #e3bf6b;
            border-color: #5a481f;
        }

        body.dark-mode .personel-evrak-status.approved {
            background: #143022;
            color: #7bd5a2;
            border-color: #23523b;
        }

        body.dark-mode .personel-evrak-status.rejected {
            background: #321d20;
            color: #f0a2a2;
            border-color: #563034;
        }

        body.dark-mode .personel-red-reason {
            color: #f0a2a2;
        }


        /* =====================================================
           MOBİL
        ===================================================== */

        @media (max-width: 900px) {

            .table-container {
                overflow-x: auto;
            }

            .data-table {
                min-width: 900px;
            }
        }

    </style>

</head>


<body>

<div class="dashboard">


    <!-- =====================================================
         SIDEBAR
    ===================================================== -->

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
                href="/BirimTakipSistemi/personel-dashboard.php"
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


            <!-- GÖREVLERİM -->

            <a
                href="/BirimTakipSistemi/pages/personel-gorevlerim.php"
                class="sidebar-link"
            >

                <i
                    data-lucide="clipboard-list"
                    class="menu-icon"
                ></i>

                <span>
                    Görevlerim
                </span>

            </a>


            <!-- EVRAKLARIM -->

            <a
                href="/BirimTakipSistemi/pages/personel-evraklarim.php"
                class="sidebar-link active"
            >

                <i
                    data-lucide="file-text"
                    class="menu-icon"
                ></i>

                <span>
                    Evraklarım
                </span>

            </a>


            <!-- PROFİL -->

            <a
                href="/BirimTakipSistemi/pages/personel-profil.php"
                class="sidebar-link"
            >

                <i
                    data-lucide="user"
                    class="menu-icon"
                ></i>

                <span>
                    Profilim
                </span>

            </a>

        </nav>


        <!-- ÇIKIŞ -->

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


    <!-- =====================================================
         ANA İÇERİK
    ===================================================== -->

    <main class="main-content">


        <!-- TOPBAR -->

        <header class="topbar">

            <div class="topbar-left">

                <button
                    type="button"
                    class="menu-button"
                >

                    <i data-lucide="menu"></i>

                </button>

                <span class="page-title">
                    Evraklarım
                </span>

            </div>


            <div class="topbar-right">


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

                <div class="user-dropdown-wrapper">

                    <div
                        class="user-area"
                        id="userArea"
                    >

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
                            class="user-arrow"
                        ></i>

                    </div>


                    <div
                        class="user-dropdown"
                        id="userDropdown"
                    >

                        <a
                            href="../logout.php"
                            class="user-dropdown-item"
                        >

                            <i data-lucide="log-out"></i>

                            <span>
                                Çıkış Yap
                            </span>

                        </a>

                    </div>

                </div>

            </div>

        </header>


        <!-- =====================================================
             SAYFA
        ===================================================== -->

        <section class="page-content">


            <div class="welcome-section">

                <h1>
                    Evraklarım
                </h1>

                <p>
                    Size ait kayıtlı evrakları buradan görüntüleyebilirsiniz.
                </p>

            </div>


            <div class="large-card">


                <div class="card-title-area">

                    <div>

                        <h2>
                            Evrak Listem
                        </h2>

                        <p>

                            Toplam
                            <?= count($evraklar) ?>
                            evrak

                        </p>

                    </div>


                    <a
                        href="/BirimTakipSistemi/pages/personel-evrak-ekle.php"
                        class="save-button"
                    >

                        <i data-lucide="plus"></i>

                        Evrak Ekle

                    </a>

                </div>


                <?php if (count($evraklar) > 0): ?>


                    <div class="table-container">

                        <table class="data-table">


                            <thead>

                                <tr>

                                    <th>
                                        #
                                    </th>

                                    <th>
                                        Evrak
                                    </th>

                                    <th>
                                        Evrak No
                                    </th>

                                    <th>
                                        Açıklama
                                    </th>

                                    <th>
                                        Tarih
                                    </th>

                                    <th>
                                        Durum
                                    </th>

                                    <th>
                                        Dosya
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                                <?php $sira = 1; ?>


                                <?php foreach (
                                    $evraklar as $evrak
                                ): ?>

                                    <tr>


                                        <!-- SIRA -->

                                        <td>

                                            <?= $sira ?>

                                        </td>


                                        <!-- EVRAK -->

                                        <td>

                                            <div class="table-name">

                                                <div class="table-icon">

                                                    <i
                                                        data-lucide="file-text"
                                                    ></i>

                                                </div>


                                                <span>

                                                    <?= htmlspecialchars(
                                                        $evrak["evrak_adi"],
                                                        ENT_QUOTES,
                                                        "UTF-8"
                                                    ) ?>

                                                </span>

                                            </div>

                                        </td>


                                        <!-- EVRAK NO -->

                                        <td>

                                            <?php

                                            if (
                                                !empty(
                                                    $evrak["evrak_no"]
                                                )
                                            ) {

                                                echo htmlspecialchars(
                                                    $evrak["evrak_no"],
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                );

                                            } else {

                                                echo "-";

                                            }

                                            ?>

                                        </td>


                                        <!-- AÇIKLAMA -->

                                        <td>

                                            <?php

                                            if (
                                                !empty(
                                                    $evrak["aciklama"]
                                                )
                                            ) {

                                                echo htmlspecialchars(
                                                    $evrak["aciklama"],
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                );

                                            } else {

                                                echo "-";

                                            }

                                            ?>

                                        </td>


                                        <!-- TARİH -->

                                        <td>

                                            <?php

                                            if (
                                                !empty(
                                                    $evrak["tarih"]
                                                )
                                            ) {

                                                echo date(
                                                    "d.m.Y",
                                                    strtotime(
                                                        $evrak["tarih"]
                                                    )
                                                );

                                            } else {

                                                echo "-";

                                            }

                                            ?>

                                        </td>


                                        <!-- DURUM -->

                                        <td>

                                            <?php

                                            $durum =
                                                $evrak["durum"]
                                                ?? "beklemede";

                                            ?>


                                            <?php if (
                                                $durum === "onaylandi"
                                            ): ?>

                                                <span
                                                    class="
                                                        personel-evrak-status
                                                        approved
                                                    "
                                                >

                                                    <i
                                                        data-lucide="circle-check"
                                                    ></i>

                                                    Onaylandı

                                                </span>


                                            <?php elseif (
                                                $durum === "reddedildi"
                                            ): ?>

                                                <span
                                                    class="
                                                        personel-evrak-status
                                                        rejected
                                                    "
                                                >

                                                    <i
                                                        data-lucide="circle-x"
                                                    ></i>

                                                    Reddedildi

                                                </span>


                                                <?php if (
                                                    !empty(
                                                        $evrak["red_nedeni"]
                                                    )
                                                ): ?>

                                                    <div
                                                        class="personel-red-reason"
                                                    >

                                                        <strong>
                                                            Neden:
                                                        </strong>

                                                        <?= htmlspecialchars(
                                                            $evrak["red_nedeni"],
                                                            ENT_QUOTES,
                                                            "UTF-8"
                                                        ) ?>

                                                    </div>

                                                <?php endif; ?>


                                            <?php else: ?>

                                                <span
                                                    class="
                                                        personel-evrak-status
                                                        pending
                                                    "
                                                >

                                                    <i
                                                        data-lucide="clock-3"
                                                    ></i>

                                                    Beklemede

                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <!-- DOSYA -->

                                        <td>

                                            <?php if (
                                                !empty(
                                                    $evrak["dosya_yolu"]
                                                )
                                            ): ?>

                                                <a
                                                    href="personel-evraklarim.php?dosya=<?= (int) $evrak["id"] ?>"
                                                    target="_blank"
                                                    class="
                                                        action-button
                                                        edit
                                                        personel-file-button
                                                    "
                                                    title="Dosyayı Görüntüle"
                                                >

                                                    <i
                                                        data-lucide="eye"
                                                    ></i>

                                                    Görüntüle

                                                </a>

                                            <?php else: ?>

                                                <span>
                                                    Dosya yok
                                                </span>

                                            <?php endif; ?>

                                        </td>

                                    </tr>


                                    <?php $sira++; ?>


                                <?php endforeach; ?>


                            </tbody>

                        </table>

                    </div>


                <?php else: ?>


                    <div class="empty-area">

                        <div class="empty-icon">

                            <i
                                data-lucide="file-text"
                            ></i>

                        </div>

                        <p>
                            Henüz size ait evrak bulunmuyor.
                        </p>

                    </div>


                <?php endif; ?>


            </div>


        </section>

    </main>

</div>


<!-- =====================================================
     JAVASCRIPT
===================================================== -->

<script src="../js/script.js"></script>

<script>
    lucide.createIcons();
</script>

</body>

</html>


</body>

</html>