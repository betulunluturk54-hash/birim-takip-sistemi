<?php

session_start();

require_once "config/database.php";

$mesaj = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $kullanici_adi = $_POST["kullanici_adi"];
    $sifre = $_POST["sifre"];
    $giris_turu = $_POST["giris_turu"];

    $sql = "SELECT * FROM kullanicilar WHERE kullanici_adi = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$kullanici_adi]);

    $kullanici = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($kullanici) {

        if (password_verify($sifre, $kullanici["sifre"])) {

            /*
             * Kullanıcının seçtiği giriş türü
             * ile veritabanındaki rol aynı mı?
             */

            if ($giris_turu == "yonetici" && $kullanici["rol"] != "admin") {

                $mesaj = "Bu hesap yönetici olarak giriş yapamaz.";

            } elseif ($giris_turu == "personel" && $kullanici["rol"] != "personel") {

                $mesaj = "Bu hesap personel olarak giriş yapamaz.";

            } else {

$_SESSION["kullanici_id"] = $kullanici["id"];
$_SESSION["kullanici_adi"] = $kullanici["kullanici_adi"];
$_SESSION["rol"] = $kullanici["rol"];

if ($kullanici["rol"] == "admin") {

    header("Location: dashboard.php");

} elseif ($kullanici["rol"] == "personel") {

    header("Location: personel-dashboard.php");

}

exit;
            }

        } else {

            $mesaj = "Şifre yanlış!";

        }

    } else {

        $mesaj = "Kullanıcı bulunamadı!";

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

    <title>Birim Takip Sistemi - Giriş</title>

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- Login CSS -->
    <link rel="stylesheet" href="css/login.css">

</head>

<body>

<div class="login-wrapper">

    <!-- ==========================================
         SOL TANITIM ALANI
    =========================================== -->

<section class="login-form-area">
    <div class="login-header-brand">

    <div class="login-header-logo">
        BT
    </div>

    <div>
        <h1>BİRİM TAKİP</h1>
        <span>Yönetim Sistemi</span>
    </div>

</div>


    <!-- ==========================================
         GİRİŞ FORMU
    =========================================== -->

    <section class="login-form-area">


        <div class="login-form-header">

            <h2>
                Giriş Yap
            </h2>

            <p>
                Sisteme devam etmek için hesabınızla giriş yapın.
            </p>

        </div>


        <!-- HATA MESAJI -->

        <?php if ($mesaj != ""): ?>

            <div class="login-message">

                <?php echo htmlspecialchars($mesaj); ?>

            </div>

        <?php endif; ?>


        <form method="POST">


            <!-- GİRİŞ TÜRÜ -->

            <div class="login-type-title">
                Giriş türünüzü seçin
            </div>


            <div class="login-type">


                <!-- YÖNETİCİ -->

                <div>

                    <input
                        type="radio"
                        name="giris_turu"
                        id="yonetici"
                        value="yonetici"
                        required
                    >

                    <label for="yonetici">

                        <i
                            data-lucide="shield-check"
                            class="login-type-icon">
                        </i>

                        <span>
                            Yönetici
                        </span>

                    </label>

                </div>


                <!-- PERSONEL -->

                <div>

                    <input
                        type="radio"
                        name="giris_turu"
                        id="personel"
                        value="personel"
                    >

                    <label for="personel">

                        <i
                            data-lucide="users"
                            class="login-type-icon">
                        </i>

                        <span>
                            Personel
                        </span>

                    </label>

                </div>


            </div>


            <!-- KULLANICI ADI -->

            <div class="input-group">

                <label for="kullanici_adi">
                    Kullanıcı Adı
                </label>


                <div class="input-wrapper">

                    <i
                        data-lucide="user"
                        class="input-icon">
                    </i>

                    <input
                        type="text"
                        id="kullanici_adi"
                        name="kullanici_adi"
                        placeholder="Kullanıcı adınızı girin"
                        required
                    >

                </div>

            </div>


            <!-- ŞİFRE -->

            <div class="input-group">

                <label for="sifre">
                    Şifre
                </label>


                <div class="input-wrapper">

                    <i
                        data-lucide="lock"
                        class="input-icon">
                    </i>

                    <input
                        type="password"
                        id="sifre"
                        name="sifre"
                        placeholder="Şifrenizi girin"
                        required
                    >

                </div>

            </div>


            <!-- GİRİŞ BUTONU -->

            <button
                type="submit"
                class="login-button">

                <span>
                    Giriş Yap
                </span>

                <i data-lucide="arrow-right"></i>

            </button>


        </form>


        <div class="login-footer">
            Güvenli giriş • Birim Takip Sistemi
        </div>


    </section>

</div>


<script>
    lucide.createIcons();
</script>

</body>
</html>