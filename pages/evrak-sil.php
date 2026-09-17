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
   EVRAK VE DOSYALARINI GETİR
========================================== */

$stmt = $db->prepare("
    SELECT
        id,
        dosya_yolu
    FROM evraklar
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([
    $id
]);

$evrak = $stmt->fetch(
    PDO::FETCH_ASSOC
);


if (!$evrak) {
    header("Location: evraklar.php");
    exit;
}


/* ==========================================
   ÇOKLU DOSYALARI GETİR
========================================== */

$stmt = $db->prepare("
    SELECT
        id,
        dosya_yolu
    FROM evrak_dosyalar
    WHERE evrak_id = ?
");

$stmt->execute([
    $id
]);

$dosyalar = $stmt->fetchAll(
    PDO::FETCH_ASSOC
);


/* ==========================================
   TRANSACTION BAŞLAT
========================================== */

try {

    $db->beginTransaction();


    /* ======================================
       DOSYA KAYITLARINI VERİTABANINDAN SİL
    ====================================== */

    $stmt = $db->prepare("
        DELETE FROM evrak_dosyalar
        WHERE evrak_id = ?
    ");

    $stmt->execute([
        $id
    ]);


    /* ======================================
       EVRAKI VERİTABANINDAN SİL
    ====================================== */

    $stmt = $db->prepare("
        DELETE FROM evraklar
        WHERE id = ?
    ");

    $stmt->execute([
        $id
    ]);


    /* ======================================
       TRANSACTION TAMAMLA
    ====================================== */

    $db->commit();


} catch (Throwable $e) {

    if ($db->inTransaction()) {
        $db->rollBack();
    }

    header(
        "Location: evraklar.php"
    );

    exit;
}


/* ==========================================
   FİZİKSEL DOSYALARI SİL
========================================== */


/* ------------------------------------------
   YENİ ÇOKLU DOSYA SİSTEMİ
------------------------------------------ */

foreach ($dosyalar as $dosya) {

    $dosyaYolu = trim(
        $dosya["dosya_yolu"] ?? ""
    );

    if ($dosyaYolu === "") {
        continue;
    }


    $dosyaYolu = str_replace(
        "\\",
        "/",
        $dosyaYolu
    );


    $dosyaYolu = ltrim(
        $dosyaYolu,
        "/"
    );


    $gercekDosya =
        __DIR__ .
        DIRECTORY_SEPARATOR .
        str_replace(
            "/",
            DIRECTORY_SEPARATOR,
            $dosyaYolu
        );


    if (is_file($gercekDosya)) {
        unlink($gercekDosya);
    }
}


/* ------------------------------------------
   ESKİ TEKLİ DOSYA SİSTEMİ
------------------------------------------ */

if (!empty($evrak["dosya_yolu"])) {

    $dosyaYolu = trim(
        $evrak["dosya_yolu"]
    );


    $dosyaYolu = str_replace(
        "\\",
        "/",
        $dosyaYolu
    );


    $dosyaYolu = ltrim(
        $dosyaYolu,
        "/"
    );


    $gercekDosya =
        __DIR__ .
        DIRECTORY_SEPARATOR .
        str_replace(
            "/",
            DIRECTORY_SEPARATOR,
            $dosyaYolu
        );


    if (is_file($gercekDosya)) {
        unlink($gercekDosya);
    }
}


/* ==========================================
   LİSTEYE GERİ DÖN
========================================== */

header(
    "Location: evraklar.php?mesaj=evrak_silindi"
);

exit;