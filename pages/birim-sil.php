<?php

session_start();

if (!isset($_SESSION["kullanici_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$id) {
    header("Location: birimler.php");
    exit;
}


/* ============================
   BİRİMİ SİL
============================ */

$sql = "DELETE FROM birimler WHERE id = ?";

$stmt = $db->prepare($sql);
$stmt->execute([$id]);


/* ============================
   LİSTEYE GERİ DÖN
============================ */

header("Location: birimler.php");
exit;