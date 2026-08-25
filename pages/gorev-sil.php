<?php

session_start();

if (!isset($_SESSION["kullanici_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$id) {
    header("Location: gorevler.php");
    exit;
}


/* GÖREVİ SİL */

$sql = "DELETE FROM gorevler WHERE id = ?";

$stmt = $db->prepare($sql);
$stmt->execute([$id]);


/* GÖREV LİSTESİNE DÖN */

header("Location: gorevler.php");
exit;