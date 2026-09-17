<?php

session_start();

if (!isset($_SESSION["kullanici_id"])) {
    header("Location: login.php");
    exit;
}

require_once "config/database.php";


/* ==========================================
   BİLDİRİM ID
========================================== */

$bildirim_id = (int) ($_GET["id"] ?? 0);

if ($bildirim_id <= 0) {
    header("Location: /BirimTakipSistemi/personel-dashboard.php");
    exit;
}


/* ==========================================
   BİLDİRİMİ GETİR
   SADECE GİRİŞ YAPAN KULLANICIYA AİT
========================================== */

$sql = "
    SELECT
        id,
        gorev_id,
        tur,
        mesaj,
        okundu
    FROM bildirimler
    WHERE id = ?
    AND kullanici_id = ?
    LIMIT 1
";

$stmt = $db->prepare($sql);

$stmt->execute([
    $bildirim_id,
    $_SESSION["kullanici_id"]
]);

$bildirim = $stmt->fetch(PDO::FETCH_ASSOC);


/* ==========================================
   BİLDİRİM BULUNAMADI
========================================== */

if (!$bildirim) {
    header("Location: /BirimTakipSistemi/personel-dashboard.php");
    exit;
}


/* ==========================================
   BİLDİRİMİ OKUNDU YAP
========================================== */

$sql = "
    UPDATE bildirimler
    SET okundu = 1
    WHERE id = ?
    AND kullanici_id = ?
";

$stmt = $db->prepare($sql);

$stmt->execute([
    $bildirim_id,
    $_SESSION["kullanici_id"]
]);


/* ==========================================
   GÖREV BİLDİRİMİ
========================================== */

if (
    $bildirim["tur"] === "gorev" &&
    !empty($bildirim["gorev_id"])
) {

    header(
        "Location: /BirimTakipSistemi/pages/personel-gorevlerim.php?gorev_id="
        . (int) $bildirim["gorev_id"]
    );

    exit;
}


/* ==========================================
   EVRAK BİLDİRİMİ
========================================== */

if ($bildirim["tur"] === "evrak") {

    header(
        "Location: /BirimTakipSistemi/pages/personel-evraklarim.php"
    );

    exit;
}


/* ==========================================
   PROFİL BİLDİRİMİ
========================================== */

if ($bildirim["tur"] === "profil") {

    header(
        "Location: /BirimTakipSistemi/pages/personel-profil.php"
    );

    exit;
}


/* ==========================================
   GÖREV ID VARSA TÜRDEN BAĞIMSIZ GİT
========================================== */

if (!empty($bildirim["gorev_id"])) {

    header(
        "Location: /BirimTakipSistemi/pages/personel-gorevlerim.php?gorev_id="
        . (int) $bildirim["gorev_id"]
    );

    exit;
}


/* ==========================================
   HİÇBİR EŞLEŞME YOKSA
========================================== */

header(
    "Location: /BirimTakipSistemi/personel-dashboard.php"
);

exit;