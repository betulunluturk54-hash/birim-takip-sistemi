<?php

session_start();

if (!isset($_SESSION["kullanici_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";


/* =========================================================
   DOSYA ID
========================================================= */

$dosyaId = (int) (
    $_GET["dosya_id"] ?? 0
);


/* =========================================================
   ESKİ SİSTEM İÇİN EVRAK ID
========================================================= */

$evrakId = (int) (
    $_GET["id"] ?? 0
);


/* =========================================================
   GEÇERLİ ID KONTROLÜ
========================================================= */

if (
    $dosyaId <= 0 &&
    $evrakId <= 0
) {
    http_response_code(400);

    exit("Geçersiz dosya.");
}


/* =========================================================
   DOSYA BİLGİLERİNİ GETİR
========================================================= */

if ($dosyaId > 0) {

    /*
       Yeni çoklu dosya sistemi.
    */

    $stmt = $db->prepare("
        SELECT
            ed.id AS dosya_id,
            ed.evrak_id,
            ed.dosya_adi,
            ed.dosya_yolu,
            e.personel_id
        FROM evrak_dosyalar ed
        INNER JOIN evraklar e
            ON e.id = ed.evrak_id
        WHERE ed.id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $dosyaId
    ]);

    $dosya = $stmt->fetch(
        PDO::FETCH_ASSOC
    );

} else {

    /*
       Eski tekli dosya sistemi.
    */

    $stmt = $db->prepare("
        SELECT
            id AS evrak_id,
            dosya_yolu,
            personel_id,
            evrak_adi AS dosya_adi
        FROM evraklar
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $evrakId
    ]);

    $dosya = $stmt->fetch(
        PDO::FETCH_ASSOC
    );
}


/* =========================================================
   DOSYA KONTROLÜ
========================================================= */

if (!$dosya) {

    http_response_code(404);

    exit("Dosya veritabanında bulunamadı.");
}


/* =========================================================
   PERSONEL YETKİSİ
========================================================= */

$yoneticiMi = in_array(
    $_SESSION["rol"] ?? "",
    [
        "yonetici",
        "admin"
    ],
    true
);


/*
   Personel yalnızca kendi evrakına erişebilir.
*/

if (!$yoneticiMi) {

    $stmt = $db->prepare("
        SELECT
            p.id
        FROM personeller p
        WHERE p.id = ?
          AND p.kullanici_id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $dosya["personel_id"] ?? 0,
        $_SESSION["kullanici_id"]
    ]);

    $yetkili =
        $stmt->fetchColumn();

    if (!$yetkili) {

        http_response_code(403);

        exit("Bu dosyayı görüntüleme yetkiniz yok.");
    }
}


/* =========================================================
   DOSYA YOLU
========================================================= */

$dosyaYolu = trim(
    $dosya["dosya_yolu"] ?? ""
);


if ($dosyaYolu === "") {

    http_response_code(404);

    exit("Dosya yolu bulunamadı.");
}


/*
   Windows / Linux yol ayrımını
   normalize ediyoruz.
*/

$dosyaYolu = str_replace(
    "\\",
    "/",
    $dosyaYolu
);


/*
   Başındaki / işaretlerini temizle.
*/

$dosyaYolu = ltrim(
    $dosyaYolu,
    "/"
);


/* =========================================================
   GERÇEK DOSYA YOLU
========================================================= */

/*
   Veritabanındaki yol:
   
   uploads/evraklar/dosya.png

   Proje kökü:

   C:\wamp64\www\BirimTakipSistemi
*/

$gercekDosya =
    __DIR__ .
    DIRECTORY_SEPARATOR .
    str_replace(
        "/",
        DIRECTORY_SEPARATOR,
        $dosyaYolu
    );

/* =========================================================
   DOSYA KONTROLÜ
========================================================= */

if (!is_file($gercekDosya)) {

    http_response_code(404);

    exit(
        "Dosya bulunamadı.\n\n" .
        "Veritabanındaki yol:\n" .
        $dosyaYolu
    );
}


/* =========================================================
   DOSYA TÜRÜ
========================================================= */

$mimeType =
    mime_content_type(
        $gercekDosya
    );


if (!$mimeType) {

    $mimeType =
        "application/octet-stream";
}


/* =========================================================
   DOSYA GÖNDER
========================================================= */

header(
    "Content-Type: " . $mimeType
);

header(
    "Content-Length: " .
    filesize($gercekDosya)
);

header(
    "Content-Disposition: inline; filename=\"" .
    basename(
        $dosya["dosya_adi"] ?? "dosya"
    ) .
    "\""
);

header(
    "X-Content-Type-Options: nosniff"
);


/* =========================================================
   DOSYAYI GÖNDER
========================================================= */

readfile(
    $gercekDosya
);

exit;