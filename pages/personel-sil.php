<?php

session_start();

if (!isset($_SESSION["kullanici_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$id) {
    header("Location: personeller.php");
    exit;
}


/* PERSONELİ SİL */

$sql = "DELETE FROM personeller WHERE id = ?";

$stmt = $db->prepare($sql);
$stmt->execute([$id]);


/* LİSTEYE GERİ DÖN */

header("Location: personeller.php");
exit;