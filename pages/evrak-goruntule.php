<?php

session_start();

if (!isset($_SESSION["kullanici_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";


/* =========================================================
   EVRAK ID
========================================================= */

$id = (int) ($_GET["id"] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    die("Geçersiz evrak ID.");
}


/* =========================================================
   KULLANICI ROLÜ
========================================================= */

$rol = $_SESSION["rol"] ?? "";

$kullaniciId = (int) (
    $_SESSION["kullanici_id"] ?? 0
);


/* =========================================================
   EVRAKI GETİR
========================================================= */

/*
   YÖNETİCİ:
   - Tüm evrakları görüntüleyebilir.

   PERSONEL:
   - Sadece kendi personel kaydına bağlı
     evrakları görüntüleyebilir.

   İlişki:
   kullanicilar.id
        ↓
   personeller.kullanici_id
        ↓
   evraklar.personel_id
*/


if ($rol === "yonetici") {

    $stmt = $db->prepare("
        SELECT
            id,
            evrak_adi,
            dosya_yolu
        FROM evraklar
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $id
    ]);

} else {

    $stmt = $db->prepare("
        SELECT
            e.id,
            e.evrak_adi,
            e.dosya_yolu
        FROM evraklar e

        INNER JOIN personeller p
            ON e.personel_id = p.id

        WHERE e.id = ?
          AND p.kullanici_id = ?

        LIMIT 1
    ");

    $stmt->execute([
        $id,
        $kullaniciId
    ]);
}


$evrak =
    $stmt->fetch(
        PDO::FETCH_ASSOC
    );


/* =========================================================
   EVRAK KONTROLÜ
========================================================= */

if (!$evrak) {

    http_response_code(403);

    die(
        "Bu evraka erişim yetkiniz yok."
    );
}


/* =========================================================
   DOSYA YOLU KONTROLÜ
========================================================= */

$kayitliYol = trim(
    $evrak["dosya_yolu"] ?? ""
);

if ($kayitliYol === "") {

    http_response_code(404);

    die(
        "Bu evraka ait dosya yolu bulunmuyor."
    );
}


/* =========================================================
   MUHTEMEL DOSYA YOLLARI
========================================================= */

/*
   Veritabanında örneğin:

   uploads/evraklar/dosya.pdf

   tutuluyorsa aşağıdaki yollar kontrol edilir.
*/

$olasiYollar = [];


/* 1. pages klasörü içinden */

$olasiYollar[] =
    __DIR__ . "/" . ltrim(
        $kayitliYol,
        "/\\"
    );


/* 2. Proje ana klasörü içinden */

$olasiYollar[] =
    dirname(__DIR__) . "/" . ltrim(
        $kayitliYol,
        "/\\"
    );


/* 3. Eğer veritabanında pages/ ile birlikte kayıt varsa */

$temizYol =
    ltrim(
        $kayitliYol,
        "/\\"
    );

if (
    str_starts_with(
        $temizYol,
        "pages/"
    )
) {

    $olasiYollar[] =
        dirname(__DIR__) . "/" . $temizYol;
}


/* =========================================================
   GERÇEK DOSYAYI BUL
========================================================= */

$gercekDosya = null;

foreach ($olasiYollar as $yol) {

    if (
        is_file($yol) &&
        is_readable($yol)
    ) {

        $gercekDosya = $yol;

        break;
    }
}


if ($gercekDosya === null) {

    http_response_code(404);

    echo "<h2>Dosya bulunamadı.</h2>";

    echo "<p>Veritabanındaki dosya yolu:</p>";

    echo "<pre>" .
        htmlspecialchars(
            $kayitliYol,
            ENT_QUOTES,
            "UTF-8"
        ) .
        "</pre>";

    exit;
}


/* =========================================================
   DOSYA UZANTISI
========================================================= */

$uzanti = strtolower(
    pathinfo(
        $gercekDosya,
        PATHINFO_EXTENSION
    )
);


/* =========================================================
   MIME TÜRÜ
========================================================= */

$mimeTurleri = [

    "pdf" =>
        "application/pdf",

    "jpg" =>
        "image/jpeg",

    "jpeg" =>
        "image/jpeg",

    "png" =>
        "image/png",

    "doc" =>
        "application/msword",

    "docx" =>
        "application/vnd.openxmlformats-officedocument.wordprocessingml.document",

    "xls" =>
        "application/vnd.ms-excel",

    "xlsx" =>
        "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
];


$mime =
    $mimeTurleri[$uzanti]
    ?? "application/octet-stream";


/* =========================================================
   DOSYA ADI
========================================================= */

$dosyaAdi =
    basename($gercekDosya);


/* =========================================================
   HEADER
========================================================= */

header(
    "Content-Type: " . $mime
);

header(
    "Content-Length: " .
    filesize($gercekDosya)
);

header(
    'Content-Disposition: inline; filename="' .
    str_replace(
        '"',
        "",
        $dosyaAdi
    ) .
    '"'
);

header(
    "Cache-Control: private, max-age=0, must-revalidate"
);

header(
    "Pragma: public"
);


/* =========================================================
   DOSYAYI GÖNDER
========================================================= */

readfile($gercekDosya);

exit;