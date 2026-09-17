<?php

session_start();

if (!isset($_SESSION["kullanici_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";

/* ==========================================
   PERSONEL ID KONTROLÜ
========================================== */

$id = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);

if (!$id) {
    header("Location: personeller.php");
    exit;
}

/* ==========================================
   PERSONELİ GETİR
========================================== */

$sql = "SELECT * FROM personeller WHERE id = ?";
$stmt = $db->prepare($sql);
$stmt->execute([$id]);
$personel = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$personel) {
    header("Location: personeller.php");
    exit;
}

/* ==========================================
   BİRİMLERİ GETİR
========================================== */

$sql = "SELECT id, birim_adi FROM birimler ORDER BY birim_adi ASC";
$stmt = $db->prepare($sql);
$stmt->execute();
$birimler = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ==========================================
   PERSONELİN KULLANICI HESABINI GETİR
========================================== */

$kullanici = null;

if (!empty($personel["kullanici_id"])) {
    $sql = "SELECT id, kullanici_adi, rol FROM kullanicilar WHERE id = ? LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute([$personel["kullanici_id"]]);
    $kullanici = $stmt->fetch(PDO::FETCH_ASSOC);
}

$mesaj = "";

/* ==========================================
   FORM DEĞERLERİ
========================================== */

$ad = $personel["ad"] ?? "";
$soyad = $personel["soyad"] ?? "";
$telefon = $personel["telefon"] ?? "";
$email = $personel["email"] ?? "";
$birim_id = $personel["birim_id"] ?? "";
$gorev = $personel["gorev"] ?? "";
$kullanici_adi = $kullanici["kullanici_adi"] ?? "";

/* ==========================================
   GÜNCELLEME İŞLEMİ
========================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $ad = trim($_POST["ad"] ?? "");
    $soyad = trim($_POST["soyad"] ?? "");
    $telefon = trim($_POST["telefon"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $birim_id = $_POST["birim_id"] ?? "";
    $gorev = trim($_POST["gorev"] ?? "");
    $kullanici_adi = trim($_POST["kullanici_adi"] ?? "");
    $yeni_sifre = trim($_POST["yeni_sifre"] ?? "");

    if ($ad === "" || $soyad === "") {
        $mesaj = "Ad ve soyad alanları zorunludur.";
    } elseif ($birim_id === "") {
        $mesaj = "Lütfen birim seçin.";
    } elseif ($kullanici && $kullanici_adi === "") {
        $mesaj = "Kullanıcı adı boş bırakılamaz.";
    } elseif ($yeni_sifre !== "" && mb_strlen($yeni_sifre) < 6) {
        $mesaj = "Yeni şifre en az 6 karakter olmalıdır.";
    } else {
        try {
            $db->beginTransaction();

            $sql = "UPDATE personeller
                    SET ad = ?, soyad = ?, telefon = ?, email = ?, birim_id = ?, gorev = ?
                    WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$ad, $soyad, $telefon, $email, $birim_id, $gorev, $id]);

            if ($kullanici) {
                $sql = "SELECT id FROM kullanicilar WHERE kullanici_adi = ? AND id != ? LIMIT 1";
                $stmt = $db->prepare($sql);
                $stmt->execute([$kullanici_adi, $kullanici["id"]]);
                
                if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                    throw new Exception("Bu kullanıcı adı başka bir kullanıcı tarafından kullanılıyor.");
                }

                if ($yeni_sifre !== "") {
                    $sifreHash = password_hash($yeni_sifre, PASSWORD_DEFAULT);
                    $sql = "UPDATE kullanicilar SET kullanici_adi = ?, sifre = ? WHERE id = ?";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$kullanici_adi, $sifreHash, $kullanici["id"]]);
                } else {
                    $sql = "UPDATE kullanicilar SET kullanici_adi = ? WHERE id = ?";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$kullanici_adi, $kullanici["id"]]);
                }
            }

            $db->commit();
            header("Location: personeller.php?durum=guncellendi");
            exit;

        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $mesaj = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personel Düzenle - Birim Takip</title>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        /* Modern form ve şifre göster/gizle stilleri */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }
        .full-width {
            grid-column: 1 / -1;
        }
        .form-input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        .form-input-wrapper .form-input-icon {
            position: absolute;
            left: 14px;
            color: #94a3b8;
            pointer-events: none;
            z-index: 1;
        }
        .form-input-wrapper input,
        .form-input-wrapper select {
            width: 100%;
            padding: 11px 16px 11px 42px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.95rem;
            color: #1e293b;
            background-color: #fff;
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
        }
        .form-input-wrapper input:focus,
        .form-input-wrapper select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
        }
        .toggle-password {
            position: absolute;
            right: 12px;
            background: none;
            border: none;
            cursor: pointer;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 4px;
            border-radius: 4px;
            transition: color 0.2s;
        }
        .toggle-password:hover {
            color: #1e293b;
        }
        .card-divider {
            border: 0;
            height: 1px;
            background: #e2e8f0;
            margin: 32px 0 24px 0;
        }
    </style>
</head>

<body>
<div class="dashboard">

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-logo">BT</div>
            <div>
                <h2>BİRİM TAKİP</h2>
                <span>Yönetim Sistemi</span>
            </div>
        </div>

        <nav class="sidebar-menu">
            <a href="../dashboard.php" class="sidebar-link">
                <i data-lucide="house" class="menu-icon"></i>
                <span>Ana Sayfa</span>
            </a>
            <a href="birimler.php" class="sidebar-link">
                <i data-lucide="building-2" class="menu-icon"></i>
                <span>Birimler</span>
            </a>
            <a href="personeller.php" class="sidebar-link active">
                <i data-lucide="users" class="menu-icon"></i>
                <span>Personeller</span>
            </a>
            <a href="gorevler.php" class="sidebar-link">
                <i data-lucide="clipboard-list" class="menu-icon"></i>
                <span>Görevler</span>
            </a>
            <a href="evraklar.php" class="sidebar-link">
                <i data-lucide="file-text" class="menu-icon"></i>
                <span>Evraklar</span>
            </a>
        </nav>

        <div class="sidebar-bottom">
            <a href="../logout.php" class="sidebar-link logout">
                <i data-lucide="log-out" class="menu-icon"></i>
                <span>Çıkış Yap</span>
            </a>
        </div>
    </aside>

    <!-- ANA İÇERİK -->
    <main class="main-content">

        <!-- TOPBAR -->
        <header class="topbar">
            <div class="topbar-left">
                <button type="button" class="menu-button">
                    <i data-lucide="menu"></i>
                </button>
                <span class="page-title">Personel Düzenle</span>
            </div>

            <div class="topbar-right">

                <?php require_once __DIR__ . "/../includes/duyuru-sistemi.php"; ?>
                <div class="theme-switch">
                    <button type="button" class="theme-button active" id="lightTheme">
                        <i data-lucide="sun"></i>
                    </button>
                    <button type="button" class="theme-button" id="darkTheme">
                        <i data-lucide="moon"></i>
                    </button>
                </div>

                <div class="topbar-divider"></div>

                <div class="user-area">
                    <div class="user-avatar">
                        <?= strtoupper(substr($_SESSION["kullanici_adi"], 0, 1)); ?>
                    </div>
                    <div class="user-text">
                        <strong><?= htmlspecialchars($_SESSION["kullanici_adi"]); ?></strong>
                        <span><?= htmlspecialchars($_SESSION["rol"]); ?></span>
                    </div>
                    <i data-lucide="chevron-down" class="user-arrow"></i>
                </div>
            </div>
        </header>

        <!-- SAYFA İÇERİĞİ -->
        <section class="page-content">
            <div class="welcome-section">
                <h1>Personel Düzenle</h1>
                <p>Personelin kurumsal profilini ve erişim bilgilerini yapılandırın.</p>
            </div>

            <div class="large-card add-form-card">
                <div class="card-title-area">
                    <div>
                        <h2>Personel Bilgileri</h2>
                        <p>Kişisel ve birim ataması detayları</p>
                    </div>
                    <a href="personeller.php" class="view-button">
                        <i data-lucide="arrow-left"></i>
                        Personellere Dön
                    </a>
                </div>

                <?php if ($mesaj !== ""): ?>
                    <div class="form-message error">
                        <i data-lucide="circle-alert"></i>
                        <span><?= htmlspecialchars($mesaj); ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" class="data-form">
                    <div class="form-grid">
                        <!-- Ad -->
                        <div class="form-group">
                            <label for="ad">Ad</label>
                            <div class="form-input-wrapper">
                                <i data-lucide="user" class="form-input-icon"></i>
                                <input type="text" id="ad" name="ad" value="<?= htmlspecialchars($ad); ?>" required>
                            </div>
                        </div>

                        <!-- Soyad -->
                        <div class="form-group">
                            <label for="soyad">Soyad</label>
                            <div class="form-input-wrapper">
                                <i data-lucide="user" class="form-input-icon"></i>
                                <input type="text" id="soyad" name="soyad" value="<?= htmlspecialchars($soyad); ?>" required>
                            </div>
                        </div>

                        <!-- Telefon -->
                        <div class="form-group">
                            <label for="telefon">Telefon</label>
                            <div class="form-input-wrapper">
                                <i data-lucide="phone" class="form-input-icon"></i>
                                <input type="text" id="telefon" name="telefon" value="<?= htmlspecialchars($telefon); ?>">
                            </div>
                        </div>

                        <!-- E-posta -->
                        <div class="form-group">
                            <label for="email">E-posta</label>
                            <div class="form-input-wrapper">
                                <i data-lucide="mail" class="form-input-icon"></i>
                                <input type="email" id="email" name="email" value="<?= htmlspecialchars($email); ?>">
                            </div>
                        </div>

                        <!-- Birim -->
                        <div class="form-group">
                            <label for="birim_id">Birim</label>
                            <div class="form-input-wrapper">
                                <i data-lucide="building-2" class="form-input-icon"></i>
                                <select id="birim_id" name="birim_id" required>
                                    <option value="">Birim seçin</option>
                                    <?php foreach ($birimler as $birim): ?>
                                        <option value="<?= $birim["id"]; ?>" <?= ($birim_id == $birim["id"]) ? "selected" : ""; ?>>
                                            <?= htmlspecialchars($birim["birim_adi"]); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Görev -->
                        <div class="form-group">
                            <label for="gorev">Görev</label>
                            <div class="form-input-wrapper">
                                <i data-lucide="briefcase" class="form-input-icon"></i>
                                <input type="text" id="gorev" name="gorev" value="<?= htmlspecialchars($gorev); ?>">
                            </div>
                        </div>
                    </div>

                    <hr class="card-divider">

                    <!-- Kullanıcı Hesabı Başlığı -->
                    <div class="card-title-area">
                        <div>
                            <h2>Kullanıcı Hesabı</h2>
                            <p>Sisteme giriş ve oturum yetkilendirme ayarları</p>
                        </div>
                    </div>

                    <?php if ($kullanici): ?>
                        <div class="form-grid">
                            <!-- Kullanıcı Adı -->
                            <div class="form-group">
                                <label for="kullanici_adi">Kullanıcı Adı</label>
                                <div class="form-input-wrapper">
                                    <i data-lucide="badge-check" class="form-input-icon"></i>
                                    <input type="text" id="kullanici_adi" name="kullanici_adi" value="<?= htmlspecialchars($kullanici_adi); ?>" required>
                                </div>
                            </div>

                            <!-- Yeni Şifre & Göz Butonu -->
                            <div class="form-group">
                                <label for="yeni_sifre">Yeni Şifre</label>
                                <div class="form-input-wrapper">
                                    <i data-lucide="lock" class="form-input-icon"></i>
                                    <input type="password" id="yeni_sifre" name="yeni_sifre" placeholder="Değiştirmek istemiyorsanız boş bırakın" autocomplete="new-password">
                                    <button type="button" class="toggle-password" id="togglePassword" title="Şifreyi Göster/Gizle">
                                        <i data-lucide="eye" id="eyeIcon"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="form-message" style="margin-top: 15px;">
                            <i data-lucide="info"></i>
                            <span>Şifreyi değiştirmek istemiyorsanız alanı boş bırakabilirsiniz.</span>
                        </div>
                    <?php else: ?>
                        <div class="form-message">
                            <i data-lucide="info"></i>
                            <span>Bu personele tanımlanmış aktif bir kullanıcı hesabı bulunmamaktadır.</span>
                        </div>
                    <?php endif; ?>

                    <!-- Form Butonları -->
                    <div class="form-actions" style="margin-top: 30px;">
                        <a href="personeller.php" class="cancel-button">İptal</a>
                        <button type="submit" class="save-button">
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

    // Şifre Göster/Gizle Mekanizması
    const toggleBtn = document.getElementById('togglePassword');
    const sifreInput = document.getElementById('yeni_sifre');

    if (toggleBtn && sifreInput) {
        toggleBtn.addEventListener('click', function () {
            const isPassword = sifreInput.getAttribute('type') === 'password';
            sifreInput.setAttribute('type', isPassword ? 'text' : 'password');
            
            // Lucide ikonunu dinamik değiştir
            this.innerHTML = isPassword 
                ? '<i data-lucide="eye-off"></i>' 
                : '<i data-lucide="eye"></i>';
            
            lucide.createIcons();
        });
    }
</script>
</body>
</html>
