<?php

session_start();

if (!isset($_SESSION["kullanici_id"])) {
    header("Location: ../login.php");
    exit;
}

/* ==========================================
   SADECE ADMİN ERİŞEBİLİR
========================================== */

if ($_SESSION["rol"] !== "admin") {
    header("Location: ../personel-dashboard.php");
    exit;
}

require_once "../config/database.php";


/* ==========================================
   EVRAK ID KONTROLÜ
========================================== */

$id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;

if ($id <= 0) {
    header("Location: evraklar.php");
    exit;
}


/* ==========================================
   EVRAKI ONAYLA
========================================== */

try {

    $sql = "UPDATE evraklar
            SET durum = 'onaylandi',
                red_nedeni = NULL
            WHERE id = ?";

    $stmt = $db->prepare($sql);
    $stmt->execute([$id]);

} catch (PDOException $e) {

    /* Hata olursa yine listeye dön */
}


/* ==========================================
   EVRAKLAR SAYFASINA DÖN
========================================== */

header("Location: evraklar.php");
exit;