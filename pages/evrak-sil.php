<?php

session_start();

if (!isset($_SESSION["kullanici_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";


/* ==========================================
   EVRAK ID KONTROLÜ
========================================== */

$id = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);

if (!$id) {
    header("Location: evraklar.php");
    exit;
}


/* ==========================================
   EVRAK BİLGİSİNİ GETİR
========================================== */

$sql = "SELECT dosya_yolu
        FROM evraklar
        WHERE id = ?";

$stmt = $db->prepare($sql);
$stmt->execute([$id]);

$evrak = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$evrak) {
    header("Location: evraklar.php");
    exit;
}


/* ==========================================
   VERİTABANINDAN SİL
========================================== */

$sql = "DELETE FROM evraklar
        WHERE id = ?";

$stmt = $db->prepare($sql);
$stmt->execute([$id]);


/* ==========================================
   YÜKLENEN DOSYAYI DA SİL
========================================== */

if (!empty($evrak["dosya_yolu"])) {

    $dosya = __DIR__
           . "/../"
           . $evrak["dosya_yolu"];

    if (file_exists($dosya)) {
        unlink($dosya);
    }
}


/* ==========================================
   LİSTEYE GERİ DÖN
========================================== */

header("Location: evraklar.php");
exit;