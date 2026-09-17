<?php

session_start();

if (!isset($_SESSION["kullanici_id"])) {
    header("Location: login.php");
    exit;
}

require_once "config/database.php";


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

$kullanici = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$kullanici) {
    session_destroy();
    header("Location: login.php");
    exit;
}


/* ==========================================
   BİLGİLERİ GÜNCELLE
========================================== */

$mesaj = "";
$hata = "";

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["profil_guncelle"])
) {

    $ad = trim($_POST["ad"] ?? "");
    $soyad = trim($_POST["soyad"] ?? "");
    $kullanici_adi = trim($_POST["kullanici_adi"] ?? "");
    $yeni_sifre = trim($_POST["yeni_sifre"] ?? "");
    $yeni_sifre_tekrar = trim($_POST["yeni_sifre_tekrar"] ?? "");


    if (
        $ad === "" ||
        $soyad === "" ||
        $kullanici_adi === ""
    ) {

        $hata = "Ad, soyad ve kullanıcı adı boş bırakılamaz.";

    } elseif ($yeni_sifre !== "" && $yeni_sifre !== $yeni_sifre_tekrar) {

        $hata = "Yeni şifreler birbiriyle eşleşmiyor.";

    } elseif ($yeni_sifre !== "" && mb_strlen($yeni_sifre, "UTF-8") < 6) {

        $hata = "Yeni şifre en az 6 karakter olmalıdır.";

    } else {

        /* Kullanıcı adının başka kullanıcıda olup olmadığını kontrol et */

        $kontrolStmt = $db->prepare("
            SELECT id
            FROM kullanicilar
            WHERE kullanici_adi = ?
            AND id <> ?
            LIMIT 1
        ");

        $kontrolStmt->execute([
            $kullanici_adi,
            $kullanici["id"]
        ]);

        $kullanici_adi_kullanimda =
            $kontrolStmt->fetch(PDO::FETCH_ASSOC);


        if ($kullanici_adi_kullanimda) {

            $hata =
                "Bu kullanıcı adı başka bir kullanıcı tarafından kullanılıyor.";

        } else {

            /* Şifre girilmişse şifreyi de güncelle, girilmemişse mevcut şifreyi koru */

            if ($yeni_sifre !== "") {

                $sifreHash = password_hash($yeni_sifre, PASSWORD_DEFAULT);

                $updateStmt = $db->prepare("
                    UPDATE kullanicilar
                    SET
                        ad = ?,
                        soyad = ?,
                        kullanici_adi = ?,
                        sifre = ?
                    WHERE id = ?
                ");

                $updateStmt->execute([
                    $ad,
                    $soyad,
                    $kullanici_adi,
                    $sifreHash,
                    $kullanici["id"]
                ]);

            } else {

                $updateStmt = $db->prepare("
                    UPDATE kullanicilar
                    SET
                        ad = ?,
                        soyad = ?,
                        kullanici_adi = ?
                    WHERE id = ?
                ");

                $updateStmt->execute([
                    $ad,
                    $soyad,
                    $kullanici_adi,
                    $kullanici["id"]
                ]);

            }


            /* Ekrandaki bilgileri yenile */

            $kullanici["ad"] =
                $ad;

            $kullanici["soyad"] =
                $soyad;

            $kullanici["kullanici_adi"] =
                $kullanici_adi;


            /* Oturumdaki kullanıcı adını da güncelle */

            $_SESSION["kullanici_adi"] =
                $kullanici_adi;


            $mesaj =
                "Profil bilgileriniz başarıyla güncellendi.";
        }
    }
}


/* ==========================================
   ROLÜ GÖSTER
========================================== */

if (
    in_array(
        $kullanici["rol"],
        ["yonetici", "admin"],
        true
    )
) {

    $rol_etiketi = "Yönetici";

} elseif (
    $kullanici["rol"] === "personel"
) {

    $rol_etiketi = "Personel";

} else {

    $rol_etiketi = "Kullanıcı";

}

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
        Profilim - Birim Takip
    </title>

    <script src="https://unpkg.com/lucide@latest"></script>

    <link
        rel="stylesheet"
        href="css/style.css"
    >

    <style>

        .profile-card {
            max-width: 760px;
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 25px;
        }

        .profile-avatar {
            width: 58px;
            height: 58px;
            border-radius: 14px;
            background: #eef3ff;
            color: #4d6fd8;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            font-weight: 800;
            flex-shrink: 0;
        }

        .profile-header-text h2 {
            margin: 0 0 4px;
            font-size: 18px;
            color: var(--text);
        }

        .profile-header-text p {
            margin: 0;
            font-size: 12px;
            color: var(--muted);
        }

        .profile-form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .profile-field {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .profile-field.full {
            grid-column: 1 / -1;
        }

        .profile-field label {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-soft);
        }

        .profile-field input {
            width: 100%;
            height: 42px;
            padding: 0 12px;
            border: 1px solid var(--border);
            border-radius: 9px;
            background: var(--white);
            color: var(--text);
            font-size: 13px;
            outline: none;
            box-sizing: border-box;
        }

        .profile-field input:focus {
            border-color: #03195b;
            box-shadow: 0 0 0 3px rgba(77, 111, 216, .08);
        }

        .profile-field input[readonly] {
            background: #f6f8fb;
            color: var(--muted);
            cursor: not-allowed;
        }

        .profile-actions {
            grid-column: 1 / -1;
            display: flex;
            justify-content: flex-end;
            margin-top: 5px;
        }

        .profile-save-button {
            height: 40px;
            padding: 0 17px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            border: none;
            border-radius: 8px;
            background: #03195b;
            color: #ffffff;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }

        .profile-save-button:hover {
            background: #03195b;
        }

        .profile-message,
        .profile-error {
            margin-bottom: 18px;
            padding: 11px 13px;
            border-radius: 9px;
            font-size: 12px;
            font-weight: 600;
        }

        .profile-message {
            background: #eaf8f0;
            color: #21804c;
            border: 1px solid #c8ead7;
        }

        .profile-error {
            background: #fff1f1;
            color: #b33f3f;
            border: 1px solid #efcaca;
        }

        body.dark-mode .profile-avatar {
            background: #1d2a47;
            color: #9eb3ff;
        }

        body.dark-mode .profile-field input {
            background: #121b2d;
            border-color: #26344a;
            color: #e5ebf3;
        }

        body.dark-mode .profile-field input[readonly] {
            background: #0f1826;
            color: #8b98aa;
        }

        body.dark-mode .profile-message {
            background: #183a29;
            color: #7bd9a4;
            border-color: #2c6044;
        }

        body.dark-mode .profile-error {
            background: #321d20;
            color: #f0a2a2;
            border-color: #5a2e32;
        }

        @media (max-width: 700px) {

            .profile-form {
                grid-template-columns: 1fr;
            }

            .profile-field.full {
                grid-column: auto;
            }

            .profile-actions {
                grid-column: auto;
            }

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

            <a
                href="dashboard.php"
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


            <a
                href="pages/birimler.php"
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


            <a
                href="pages/personeller.php"
                class="sidebar-link"
            >

                <i
                    data-lucide="users"
                    class="menu-icon"
                ></i>

                <span>
                    Personeller
                </span>

            </a>


            <a
                href="pages/gorevler.php"
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


            <a
                href="pages/evraklar.php"
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

            <a
                href="profil.php"
                class="sidebar-link active"
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


        <div class="sidebar-bottom">

            <a
                href="logout.php"
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


    <!-- ================= ANA ALAN ================= -->

    <main class="main-content">


        <header class="topbar">

            <div class="topbar-left">

                <button
                    type="button"
                    class="menu-button"
                >

                    <i data-lucide="menu"></i>

                </button>

                <span class="page-title">
                    Profilim
                </span>

            </div>


            <div class="topbar-right">

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


                <div class="user-area">

                    <div class="user-avatar">

                        <?php

                        $ilk_harf =
                            mb_substr(
                                $kullanici["ad"]
                                ?: $kullanici["kullanici_adi"],
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
                                trim(
                                    $kullanici["ad"] .
                                    " " .
                                    $kullanici["soyad"]
                                ),
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


        <!-- ================= SAYFA ================= -->

        <section class="page-content">


            <div class="welcome-section">

                <h1>
                    Profilim
                </h1>

                <p>
                    Kendi kullanıcı bilgilerinizi yönetin.
                </p>

            </div>


            <div class="large-card profile-card">


                <div class="profile-header">

                    <div class="profile-avatar">

                        <?php

                        $profil_harf =
                            mb_substr(
                                $kullanici["ad"]
                                ?: $kullanici["kullanici_adi"],
                                0,
                                1,
                                "UTF-8"
                            );

                        echo htmlspecialchars(
                            mb_strtoupper(
                                $profil_harf,
                                "UTF-8"
                            )
                        );

                        ?>

                    </div>


                    <div class="profile-header-text">

                        <h2>

                            <?= htmlspecialchars(
                                trim(
                                    $kullanici["ad"] .
                                    " " .
                                    $kullanici["soyad"]
                                ),
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>

                        </h2>

                        <p>
                            <?= htmlspecialchars(
                                $rol_etiketi,
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>
                        </p>

                    </div>

                </div>


                <?php if ($mesaj !== ""): ?>

                    <div class="profile-message">
                        <?= htmlspecialchars($mesaj) ?>
                    </div>

                <?php endif; ?>


                <?php if ($hata !== ""): ?>

                    <div class="profile-error">
                        <?= htmlspecialchars($hata) ?>
                    </div>

                <?php endif; ?>


                <form
                    method="POST"
                    class="profile-form"
                >


                    <div class="profile-field">

                        <label for="ad">
                            Ad
                        </label>

                        <input
                            type="text"
                            id="ad"
                            name="ad"
                            value="<?= htmlspecialchars(
                                $kullanici["ad"]
                            ) ?>"
                            required
                        >

                    </div>


                    <div class="profile-field">

                        <label for="soyad">
                            Soyad
                        </label>

                        <input
                            type="text"
                            id="soyad"
                            name="soyad"
                            value="<?= htmlspecialchars(
                                $kullanici["soyad"]
                            ) ?>"
                            required
                        >

                    </div>


                    <div class="profile-field">

                        <label for="kullanici_adi">
                            Kullanıcı Adı
                        </label>

                        <input
                            type="text"
                            id="kullanici_adi"
                            name="kullanici_adi"
                            value="<?= htmlspecialchars(
                                $kullanici["kullanici_adi"]
                            ) ?>"
                            required
                        >

                    </div>


                    <div class="profile-field">

                        <label for="rol">
                            Rol
                        </label>

                        <input
                            type="text"
                            id="rol"
                            value="<?= htmlspecialchars(
                                $rol_etiketi
                            ) ?>"
                            readonly
                        >

                    </div>


                    <div class="profile-field">

                        <label for="yeni_sifre">
                            Yeni Şifre (İsteğe bağlı)
                        </label>

                        <input
                            type="password"
                            id="yeni_sifre"
                            name="yeni_sifre"
                            placeholder="Değiştirmek istemiyorsanız boş bırakın"
                        >

                    </div>


                    <div class="profile-field">

                        <label for="yeni_sifre_tekrar">
                            Yeni Şifre Tekrar
                        </label>

                        <input
                            type="password"
                            id="yeni_sifre_tekrar"
                            name="yeni_sifre_tekrar"
                            placeholder="Yeni şifrenizi tekrar girin"
                        >

                    </div>


                    <div class="profile-actions">

                        <button
                            type="submit"
                            name="profil_guncelle"
                            class="profile-save-button"
                        >

                            <i data-lucide="save"></i>

                            Bilgileri Güncelle

                        </button>

                    </div>


                </form>


            </div>


        </section>


    </main>


</div>


<script src="js/script.js"></script>

<script>

    lucide.createIcons();

</script>


</body>

</html>