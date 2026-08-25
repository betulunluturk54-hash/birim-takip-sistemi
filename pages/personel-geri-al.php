<?php

session_start();

if (!isset($_SESSION["kullanici_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$id) {
    header("Location: personel-arsiv.php");
    exit;
}

$sql = "UPDATE personeller
        SET durum = 'aktif',
            ayrilma_tarihi = NULL
        WHERE id = ?";

$stmt = $db->prepare($sql);
$stmt->execute([$id]);

header("Location: personel-arsiv.php");
exit;