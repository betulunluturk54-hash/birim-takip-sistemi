<?php

session_start();

if (!isset($_SESSION["kullanici_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";
/* =========================================================
   YETKİ KONTROLÜ
========================================================= */

$yoneticiMi = in_array(
    $_SESSION["rol"] ?? "",
    ["yonetici", "admin"],
    true
);

$kendiPersonelId = null;

if (!$yoneticiMi) {

    $stmt = $db->prepare("
        SELECT id
        FROM personeller
        WHERE kullanici_id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $_SESSION["kullanici_id"]
    ]);

    $kendiPersonelId = $stmt->fetchColumn();

    if (!$kendiPersonelId) {
        $kendiPersonelId = 0;
    } else {
        $kendiPersonelId = (int) $kendiPersonelId;
    }
}


/* =========================================================
   YARDIMCI FONKSİYON
========================================================= */

function temizle($veri)
{
    return htmlspecialchars(
        $veri ?? "",
        ENT_QUOTES,
        "UTF-8"
    );
}


/* =========================================================
   MESAJLAR
========================================================= */

$mesaj = "";
$hata = "";


/* =========================================================
   DOSYA YÜKLEME
========================================================= */

$uploadDir = __DIR__ . "/uploads/evraklar/";

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}


$izinVerilenUzantilar = [
    "pdf",
    "doc",
    "docx",
    "xls",
    "xlsx",
    "jpg",
    "jpeg",
    "png"
];

$maxDosyaBoyutu = 10 * 1024 * 1024;


/* =========================================================
   GÖRÜNÜM FİLTRESİ
========================================================= */

$gorunum = $_GET["gorunum"] ?? "tum";

$izinliGorunumler = [
    "tum",
    "beklemede",
    "onaylandi",
    "reddedildi"
];

if (!in_array(
    $gorunum,
    $izinliGorunumler,
    true
)) {
    $gorunum = "tum";
}


/* =========================================================
   ARAMA
========================================================= */

$arama = trim(
    $_GET["arama"] ?? ""
);


/* =========================================================
   EVRAK DURUM GÜNCELLEME
========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    ($_POST["islem"] ?? "") === "durum"
) {

    $id = (int) (
        $_POST["id"] ?? 0
    );

    $yeniDurum =
        trim(
            $_POST["durum"] ?? ""
        );

    $redNedeni =
        trim(
            $_POST["red_nedeni"] ?? ""
        );


    if ($id <= 0) {

        $hata = "Geçersiz evrak.";
        } elseif (!$yoneticiMi) {

    $hata = "Bu işlemi gerçekleştirme yetkiniz yok.";


    } elseif (
        !in_array(
            $yeniDurum,
            [
                "beklemede",
                "onaylandi",
                "reddedildi"
            ],
            true
        )
    ) {

        $hata =
            "Geçersiz evrak durumu.";

    } elseif (
        $yeniDurum === "reddedildi" &&
        $redNedeni === ""
    ) {

        $hata =
            "Reddetme nedeni belirtilmelidir.";

    } else {

        $stmt = $db->prepare("
            UPDATE evraklar
            SET
                durum = ?,
                red_nedeni = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $yeniDurum,
            $yeniDurum === "reddedildi"
                ? $redNedeni
                : null,
            $id
        ]);


        if ($yeniDurum === "onaylandi") {

            $mesaj =
                "Evrak başarıyla onaylandı.";

        } elseif ($yeniDurum === "reddedildi") {

            $mesaj =
                "Evrak reddedildi.";

        } else {

            $mesaj =
                "Evrak tekrar beklemeye alındı.";
        }
    }
}


/* =========================================================
   EVRAK DÜZENLEME VERİSİ
========================================================= */

$duzenlenecekEvrak = null;

if (isset($_GET["duzenle"])) {

    $id = (int) $_GET["duzenle"];

    if ($id > 0) {

if ($yoneticiMi) {

    $stmt = $db->prepare("
        SELECT *
        FROM evraklar
        WHERE id = ?
    ");

    $stmt->execute([
        $id
    ]);

} else {

    $stmt = $db->prepare("
        SELECT *
        FROM evraklar
        WHERE id = ?
          AND personel_id = ?
    ");

    $stmt->execute([
        $id,
        $kendiPersonelId
    ]);
}

        $duzenlenecekEvrak =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );
    }
}


/* =========================================================
   EVRAK EKLE / GÜNCELLE
========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    (
        ($_POST["islem"] ?? "") === "ekle" ||
        ($_POST["islem"] ?? "") === "guncelle"
    )
) {

    $islem =
        $_POST["islem"] ?? "";

    $personel_id =
        (int) (
            $_POST["personel_id"] ?? 0
        );
        if (!$yoneticiMi) {

    $personel_id = $kendiPersonelId;
}

    $evrak_adi =
        trim(
            $_POST["evrak_adi"] ?? ""
        );

    $evrak_no =
        trim(
            $_POST["evrak_no"] ?? ""
        );

    $aciklama =
        trim(
            $_POST["aciklama"] ?? ""
        );

    $tarih =
        $_POST["tarih"] ?? "";


    /* =====================================================
       ZORUNLU ALANLAR
    ===================================================== */

    if ($personel_id <= 0) {

        $hata =
            "Lütfen bir personel seçin.";

    } elseif ($evrak_adi === "") {

        $hata =
            "Lütfen evrak adını girin.";

    } elseif ($tarih === "") {

        $hata =
            "Lütfen evrak tarihini girin.";
    }


    /* =====================================================
       DOSYA
       DOSYA ARTIK ZORUNLU DEĞİL
    ===================================================== */

    $yeniDosyaYolu = null;

    if (
        $hata === "" &&
        isset($_FILES["evrak_dosyasi"])
    ) {

        $dosya =
            $_FILES["evrak_dosyasi"];


        if (
            $dosya["error"] !== UPLOAD_ERR_NO_FILE
        ) {

            if (
                $dosya["error"] !== UPLOAD_ERR_OK
            ) {

                $hata =
                    "Dosya yüklenirken bir hata oluştu.";

            } elseif (
                $dosya["size"] > $maxDosyaBoyutu
            ) {

                $hata =
                    "Dosya boyutu en fazla 10 MB olabilir.";

            } else {

                $uzanti =
                    strtolower(
                        pathinfo(
                            $dosya["name"],
                            PATHINFO_EXTENSION
                        )
                    );


                if (!in_array(
                    $uzanti,
                    $izinVerilenUzantilar,
                    true
                )) {

                    $hata =
                        "Bu dosya türüne izin verilmiyor.";

                } else {

                    $guvenliDosyaAdi =
                        bin2hex(
                            random_bytes(16)
                        )
                        . "_"
                        . date("YmdHis")
                        . "."
                        . $uzanti;


                    $hedef =
                        $uploadDir .
                        $guvenliDosyaAdi;


                    if (
                        move_uploaded_file(
                            $dosya["tmp_name"],
                            $hedef
                        )
                    ) {

                        $yeniDosyaYolu =
                            "uploads/evraklar/" .
                            $guvenliDosyaAdi;

                    } else {

                        $hata =
                            "Dosya sunucuya kaydedilemedi.";
                    }
                }
            }
        }
    }


    /* =====================================================
       YENİ EVRAK
    ===================================================== */

    if (
        $hata === "" &&
        $islem === "ekle"
    ) {

        $stmt = $db->prepare("
            INSERT INTO evraklar
            (
                evrak_adi,
                evrak_no,
                aciklama,
                tarih,
                personel_id,
                dosya_yolu,
                durum,
                red_nedeni
            )
            VALUES
            (
                ?, ?, ?, ?, ?, ?, 'beklemede', NULL
            )
        ");


        $basarili =
            $stmt->execute([
                $evrak_adi,
                $evrak_no !== ""
                    ? $evrak_no
                    : null,
                $aciklama !== ""
                    ? $aciklama
                    : null,
                $tarih,
                $personel_id,
                $yeniDosyaYolu
            ]);


        if ($basarili) {

            header(
                "Location: evraklar.php?mesaj=eklendi"
            );

            exit;

        } else {

            if ($yeniDosyaYolu !== null) {

                $gercekDosya =
                    __DIR__ .
                    "/" .
                    $yeniDosyaYolu;

                if (
                    file_exists(
                        $gercekDosya
                    )
                ) {

                    unlink(
                        $gercekDosya
                    );
                }
            }


            $hata =
                "Evrak kaydedilirken bir hata oluştu.";
        }
    }


    /* =====================================================
       EVRAK GÜNCELLE
    ===================================================== */

    if (
        $hata === "" &&
        $islem === "guncelle"
    ) {

        $id =
            (int) (
                $_POST["id"] ?? 0
            );


        if ($id <= 0) {

            $hata =
                "Geçersiz evrak.";

        } else {

if ($yoneticiMi) {

    $stmt = $db->prepare("
        SELECT *
        FROM evraklar
        WHERE id = ?
    ");

    $stmt->execute([
        $id
    ]);

} else {

    $stmt = $db->prepare("
        SELECT *
        FROM evraklar
        WHERE id = ?
          AND personel_id = ?
    ");

    $stmt->execute([
        $id,
        $kendiPersonelId
    ]);
}


            $mevcut =
                $stmt->fetch(
                    PDO::FETCH_ASSOC
                );


            if (!$mevcut) {

                $hata =
                    "Güncellenecek evrak bulunamadı.";

            } else {


                if ($yeniDosyaYolu !== null) {

                    if (
                        !empty(
                            $mevcut["dosya_yolu"]
                        )
                    ) {

                        $eskiDosya =
                            __DIR__ .
                            "/" .
                            $mevcut["dosya_yolu"];


                        if (
                            file_exists(
                                $eskiDosya
                            )
                        ) {

                            unlink(
                                $eskiDosya
                            );
                        }
                    }


                    $dosyaYolu =
                        $yeniDosyaYolu;

                } else {

                    $dosyaYolu =
                        $mevcut["dosya_yolu"];
                }


                $stmt =
                    $db->prepare("
                        UPDATE evraklar
                        SET
                            evrak_adi = ?,
                            evrak_no = ?,
                            aciklama = ?,
                            tarih = ?,
                            personel_id = ?,
                            dosya_yolu = ?
                        WHERE id = ?
                    ");


                $basarili =
                    $stmt->execute([
                        $evrak_adi,
                        $evrak_no !== ""
                            ? $evrak_no
                            : null,
                        $aciklama !== ""
                            ? $aciklama
                            : null,
                        $tarih,
                        $personel_id,
                        $dosyaYolu,
                        $id
                    ]);


                if ($basarili) {

                    header(
                        "Location: evraklar.php?mesaj=guncellendi"
                    );

                    exit;

                } else {

                    $hata =
                        "Evrak güncellenirken bir hata oluştu.";
                }
            }
        }
    }
}


/* =========================================================
   MESAJLAR
========================================================= */

if (isset($_GET["mesaj"])) {

    switch (
        $_GET["mesaj"]
    ) {

        case "eklendi":

            $mesaj =
                "Evrak başarıyla eklendi.";

            break;


        case "guncellendi":

            $mesaj =
                "Evrak başarıyla güncellendi.";

            break;

        case "onaylandi":

            $mesaj =
                "Evrak başarıyla onaylandı.";

            break;

        case "reddedildi":

            $mesaj =
                "Evrak reddedildi.";

            break;

        case "beklemede":

            $mesaj =
                "Evrak tekrar beklemeye alındı.";

            break;
    }
}


/* =========================================================
   EVRAKLARI GETİR
========================================================= */
$sql = "
    SELECT
        e.id,
        e.evrak_adi,
        e.evrak_no,
        e.aciklama,
        e.tarih,
        e.personel_id,
        e.dosya_yolu,
        e.durum,
        e.red_nedeni,
        CONCAT(
            p.ad,
            ' ',
            p.soyad
        ) AS personel_adi

    FROM evraklar e

    LEFT JOIN personeller p
        ON e.personel_id = p.id

    WHERE 1 = 1
";

$params = [];


/* =========================================================
   PERSONEL YETKİ FİLTRESİ
========================================================= */

if (
    ($_SESSION["rol"] ?? "") === "personel"
) {

    $sql .= "
        AND p.kullanici_id = ?
    ";

    $params[] =
        $_SESSION["kullanici_id"];
}


/* =========================================================
   DURUM FİLTRESİ
========================================================= */

if ($gorunum !== "tum") {

    $sql .= "
        AND e.durum = ?
    ";

    $params[] =
        $gorunum;
}


/* =========================================================
   ARAMA
========================================================= */

if ($arama !== "") {

    $sql .= "
        AND (
            e.evrak_adi LIKE ?
            OR e.evrak_no LIKE ?
            OR e.aciklama LIKE ?
            OR CONCAT(
                p.ad,
                ' ',
                p.soyad
            ) LIKE ?
        )
    ";

    $aramaParam =
        "%" . $arama . "%";

    $params[] = $aramaParam;
    $params[] = $aramaParam;
    $params[] = $aramaParam;
    $params[] = $aramaParam;
}


/* =========================================================
   SIRALAMA
========================================================= */

$sql .= "
    ORDER BY e.id DESC
";


$stmt =
    $db->prepare($sql);

$stmt->execute(
    $params
);

$evraklar =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/* =========================================================
   AKTİF PERSONELLER
========================================================= */

if ($yoneticiMi) {

    $stmt = $db->query("
        SELECT
            id,
            ad,
            soyad
        FROM personeller
        WHERE durum = 'aktif'
        ORDER BY
            ad ASC,
            soyad ASC
    ");

} else {

    $stmt = $db->prepare("
        SELECT
            id,
            ad,
            soyad
        FROM personeller
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $kendiPersonelId
    ]);
}

$personeller =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );

$personeller =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/* =========================================================
   EVRAK SAYILARI
========================================================= */

$stmt = $db->query("
    SELECT
        COUNT(*)
    FROM evraklar
    WHERE durum = 'beklemede'
");

$bekleyenEvrakSayisi =
    (int) $stmt->fetchColumn();


$stmt = $db->query("
    SELECT
        COUNT(*)
    FROM evraklar
    WHERE durum = 'onaylandi'
");

$onaylananEvrakSayisi =
    (int) $stmt->fetchColumn();


$stmt = $db->query("
    SELECT
        COUNT(*)
    FROM evraklar
    WHERE durum = 'reddedildi'
");

$reddedilenEvrakSayisi =
    (int) $stmt->fetchColumn();

?>

<!DOCTYPE html>
<html lang="tr">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Evraklar - Birim Takip
    </title>

    <script src="https://unpkg.com/lucide@latest"></script>

    <link
        rel="stylesheet"
        href="../css/style.css"
    >

    <style>

        /* =========================================================
           EVRAK SAYFASI GENEL
        ========================================================= */

        .evrak-page {
            width: 100%;
        }


        /* =========================================================
           ÜST BAR ARAMA
        ========================================================= */

        .evrak-topbar-search {
            display: flex;
            align-items: center;
            position: relative;
        }

        .evrak-topbar-search form {
            display: flex;
            align-items: center;
            position: relative;
        }

        .evrak-search-icon {
            position: absolute;
            left: 13px;

            width: 17px;
            height: 17px;

            color: #7b8799;

            pointer-events: none;
        }

        .evrak-search-input {
            width: 220px;
            height: 40px;

            padding:
                0 40px
                0 39px;

            border:
                1px solid #e5e9f0;

            border-radius: 10px;

            outline: none;

            background: white;
            color: #18243a;

            font-family: inherit;
            font-size: 13px;

            transition:
                border-color .2s ease,
                box-shadow .2s ease;
        }

        .evrak-search-input:focus {
            border-color: #b7c4d8;

            box-shadow:
                0 0 0 3px
                rgba(53, 109, 243, .08);
        }

        .evrak-search-input::placeholder {
            color: #9aa6b7;
        }

        .evrak-search-submit {
            position: absolute;
            right: 7px;

            width: 27px;
            height: 27px;

            border: none;
            border-radius: 7px;

            background: transparent;
            color: #6f7d91;

            display: flex;
            align-items: center;
            justify-content: center;

            cursor: pointer;
        }

        .evrak-search-submit:hover {
            background: #f1f4f8;
            color: #3372f4;
        }

        .evrak-search-submit svg {
            width: 15px;
            height: 15px;
        }


        /* =========================================================
           MESAJLAR
        ========================================================= */

        .evrak-message,
        .evrak-error {
            display: flex;
            align-items: center;
            gap: 10px;

            margin-bottom: 20px;

            padding: 12px 15px;

            border-radius: 10px;

            font-size: 13px;
            font-weight: 600;
        }

        .evrak-message {
            background: #eefaf3;
            border: 1px solid #ccebd8;
            color: #21804b;
        }

        .evrak-error {
            background: #fff1f1;
            border: 1px solid #f1cdcd;
            color: #b33f3f;
        }


        /* =========================================================
           İSTATİSTİK KARTLARI
        ========================================================= */

        .evrak-stat-grid {
            display: grid;

            grid-template-columns:
                repeat(3, minmax(0, 1fr));

            gap: 18px;

            margin-bottom: 22px;
        }


        /* =========================================================
           EVRAK LİSTESİ KARTI
        ========================================================= */

        .evrak-list-card {
            overflow: hidden;
        }


        .evrak-list-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;

            padding-bottom: 18px;

            border-bottom:
                1px solid var(--border);
        }


        .evrak-list-title h2 {
            margin: 0 0 5px;

            font-size: 18px;
            font-weight: 700;

            letter-spacing: -0.2px;
        }


        .evrak-list-title p {
            margin: 0;

            color: var(--text-soft);

            font-size: 12px;
        }


        /* =========================================================
           FİLTRELER
        ========================================================= */

        .evrak-filters {
            display: flex;
            align-items: center;
            gap: 7px;

            flex-wrap: wrap;
        }


        .evrak-filter {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            gap: 6px;

            min-height: 36px;

            padding:
                0 11px;

            border:
                1px solid var(--border);

            border-radius: 9px;

            background:
                var(--white);

            color:
                var(--text-soft);

            font-size: 12px;
            font-weight: 650;

            transition:
                background-color .2s ease,
                border-color .2s ease,
                color .2s ease,
                transform .2s ease;
        }


        .evrak-filter svg {
            width: 15px;
            height: 15px;
        }


        .evrak-filter:hover {
            background: #f6f8fc;

            border-color: #d9e1ec;

            color: var(--text);

            transform:
                translateY(-1px);
        }


        .evrak-filter.active {
            background: #eaf1ff;

            border-color: #c8d8f7;

            color: var(--blue);

            box-shadow:
                0 3px 10px
                rgba(53, 109, 243, 0.07);
        }


        /* =========================================================
           TABLO ALANI
        ========================================================= */

        .evrak-table-wrapper {
            width: 100%;

            overflow-x: auto;

            margin-top: 18px;

            border:
                1px solid var(--border);

            border-radius: 12px;
        }


        .evrak-table {
            width: 100%;

            border-collapse: collapse;

            table-layout: fixed;

            min-width: 980px;
        }


        .evrak-table thead {
            background: #f8fafc;
        }


        .evrak-table th {
            padding:
                13px 14px;

            text-align: left;

            color: var(--text-soft);

            font-size: 11px;
            font-weight: 750;

            letter-spacing:
                .25px;

            text-transform:
                uppercase;

            border-bottom:
                1px solid var(--border);
        }


        .evrak-table td {
            padding:
                14px;

            color: var(--text);

            font-size: 13px;

            border-bottom:
                1px solid var(--border);

            vertical-align: middle;
        }


        .evrak-table tbody tr {
            transition:
                background-color .18s ease;
        }


        .evrak-table tbody tr:hover {
            background:
                #f8faff;
        }


        .evrak-table tbody tr:last-child td {
            border-bottom: none;
        }


        /* =========================================================
           SÜTUN GENİŞLİKLERİ
        ========================================================= */

        .evrak-table th:nth-child(1),
        .evrak-table td:nth-child(1) {
            width: 55px;
            text-align: center;
        }


        .evrak-table th:nth-child(2),
        .evrak-table td:nth-child(2) {
            width: 190px;
        }


        .evrak-table th:nth-child(3),
        .evrak-table td:nth-child(3) {
            width: 220px;
        }


        .evrak-table th:nth-child(4),
        .evrak-table td:nth-child(4) {
            width: 130px;
        }


        .evrak-table th:nth-child(5),
        .evrak-table td:nth-child(5) {
            width: 105px;
        }


        .evrak-table th:nth-child(6),
        .evrak-table td:nth-child(6) {
            width: 125px;
        }


        .evrak-table th:nth-child(7),
        .evrak-table td:nth-child(7) {
            width: 190px;
        }


        /* =========================================================
           PERSONEL
        ========================================================= */

        .evrak-person {
            display: flex;
            align-items: center;
            gap: 10px;

            min-width: 0;
        }


        .evrak-person-icon {
            width: 34px;
            height: 34px;

            flex-shrink: 0;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 9px;

            background: #eaf1ff;

            color: var(--blue);
        }


        .evrak-person-icon svg {
            width: 16px;
            height: 16px;
        }


        .evrak-person-name {
            display: block;

            overflow: hidden;

            white-space: nowrap;

            text-overflow: ellipsis;

            font-size: 13px;
            font-weight: 650;
        }


        /* =========================================================
           EVRAK ADI
        ========================================================= */

        .evrak-name {
            display: flex;

            flex-direction: column;

            gap: 4px;

            min-width: 0;
        }


        .evrak-name strong {
            display: block;

            overflow: hidden;

            white-space: nowrap;

            text-overflow: ellipsis;

            font-size: 13px;
            font-weight: 700;
        }


        .evrak-description {
            display: block;

            overflow: hidden;

            white-space: nowrap;

            text-overflow: ellipsis;

            color: var(--text-soft);

            font-size: 11px;
        }


        /* =========================================================
           EVRAK NO
        ========================================================= */

        .evrak-number {
            display: inline-flex;

            align-items: center;

            min-height: 28px;

            padding:
                0 9px;

            border-radius: 7px;

            background: #f5f7fb;

            color: #53647d;

            font-size: 11px;
            font-weight: 650;
        }


        /* =========================================================
           TARİH
        ========================================================= */

        .evrak-date {
            color: var(--text-soft);

            font-size: 12px;
            font-weight: 600;
        }


        /* =========================================================
           DURUM
        ========================================================= */

        .evrak-status {
            display: inline-flex;

            align-items: center;

            gap: 6px;

            min-height: 28px;

            padding:
                0 9px;

            border-radius: 999px;

            font-size: 10px;

            font-weight: 750;

            white-space: nowrap;

            border:
                1px solid transparent;
        }


        .evrak-status svg {
            width: 13px;
            height: 13px;
        }


        .evrak-status.pending {
            background: #fff6e5;
            color: #a97212;
            border-color: #f5e3bc;
        }


        .evrak-status.approved {
            background: #eaf8f0;
            color: #21804c;
            border-color: #d2efdf;
        }


        .evrak-status.rejected {
            background: #fff0f0;
            color: #b64545;
            border-color: #f1d0d0;
        }


        .red-reason {
            margin-top: 6px;

            max-width: 180px;

            overflow: hidden;

            white-space: nowrap;

            text-overflow: ellipsis;

            color: #b64545;

            font-size: 10px;
        }


        /* =========================================================
           DOSYA
        ========================================================= */

        .evrak-no-file {
            display: inline-flex;

            align-items: center;

            gap: 5px;

            min-height: 28px;

            padding:
                0 9px;

            border-radius: 7px;

            background: #f5f7fb;

            color: var(--text-muted);

            font-size: 10px;

            font-weight: 650;
        }


        .evrak-no-file svg {
            width: 13px;
            height: 13px;
        }


/* =========================================================
   EVRAK İŞLEM BUTONLARI - YENİ TASARIM
========================================================= */

.evrak-actions {
    display: flex;
    align-items: center;
    gap: 7px;
    flex-wrap: nowrap;
}


/* =========================================================
   ANA BUTON
========================================================= */

.evrak-action {
    position: relative;

    width: 36px;
    height: 36px;

    flex-shrink: 0;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    padding: 0;

    border: 1px solid transparent;
    border-radius: 10px;

    text-decoration: none;

    cursor: pointer;

    transition:
        transform .18s ease,
        box-shadow .18s ease,
        background-color .18s ease,
        border-color .18s ease,
        color .18s ease;
}


/* =========================================================
   İKON
========================================================= */

.evrak-action svg {
    width: 16px;
    height: 16px;

    stroke-width: 2.2;

    transition:
        transform .18s ease;
}


/* =========================================================
   HOVER
========================================================= */

.evrak-action:hover {
    transform: translateY(-2px);
}

.evrak-action:hover svg {
    transform: scale(1.08);
}


/* =========================================================
   GÖRÜNTÜLE
========================================================= */

.evrak-action.view {
    background: #eef4ff;
    color: #3975e8;
    border-color: #d8e5ff;

    box-shadow:
        0 2px 6px rgba(57, 117, 232, .08);
}

.evrak-action.view:hover {
    background: #e2ecff;
    border-color: #c4d7ff;

    color: #2865d9;

    box-shadow:
        0 5px 12px rgba(57, 117, 232, .16);
}


/* =========================================================
   DÜZENLE
========================================================= */

.evrak-action.edit {
    background: #f3efff;
    color: #7558d9;
    border-color: #e4dcff;

    box-shadow:
        0 2px 6px rgba(117, 88, 217, .08);
}

.evrak-action.edit:hover {
    background: #e9e2ff;
    border-color: #d5c9ff;

    color: #6244c8;

    box-shadow:
        0 5px 12px rgba(117, 88, 217, .16);
}


/* =========================================================
   ONAYLA
========================================================= */

.evrak-action.success {
    background: #ecfaf2;
    color: #24945a;
    border-color: #d3efdf;

    box-shadow:
        0 2px 6px rgba(36, 148, 90, .08);
}

.evrak-action.success:hover {
    background: #ddf6e8;
    border-color: #bfe7d0;

    color: #18834c;

    box-shadow:
        0 5px 12px rgba(36, 148, 90, .16);
}


/* =========================================================
   REDDET
========================================================= */

.evrak-action.danger {
    background: #fff0f1;
    color: #d05252;
    border-color: #f3d5d7;

    box-shadow:
        0 2px 6px rgba(208, 82, 82, .08);
}

.evrak-action.danger:hover {
    background: #ffe4e6;
    border-color: #efc1c4;

    color: #bd3f3f;

    box-shadow:
        0 5px 12px rgba(208, 82, 82, .16);
}


/* =========================================================
   TEKRAR İNCELE
========================================================= */

.evrak-action.back {
    background: #f3f6fa;
    color: #607089;
    border-color: #dfe5ed;

    box-shadow:
        0 2px 6px rgba(70, 90, 115, .07);
}

.evrak-action.back:hover {
    background: #e8edf4;
    border-color: #cfd8e4;

    color: #465a73;

    box-shadow:
        0 5px 12px rgba(70, 90, 115, .14);
}


/* =========================================================
   DOSYA YOK
========================================================= */

.evrak-no-file {
    display: inline-flex;

    align-items: center;
    justify-content: center;

    gap: 6px;

    min-height: 36px;

    padding: 0 11px;

    border-radius: 10px;

    background: #f5f7fa;

    color: #7b8798;

    border: 1px solid #e3e8ef;

    font-size: 10px;
    font-weight: 650;
}

.evrak-no-file svg {
    width: 14px;
    height: 14px;

    stroke-width: 2;
}


/* =========================================================
   BUTONLARIN FORM İÇİNDE BOZULMAMASI
========================================================= */

.evrak-actions form {
    display: flex;
    align-items: center;

    margin: 0;
    padding: 0;
}


/* =========================================================
   KLAVYE ODAKLANMASI
========================================================= */

.evrak-action:focus-visible {
    outline: none;

    box-shadow:
        0 0 0 3px rgba(53, 109, 243, .16);
}


/* =========================================================
   DARK MODE
========================================================= */

body.dark-mode .evrak-action.view {
    background: #172b4d;
    color: #82aaff;
    border-color: #29446e;
}

body.dark-mode .evrak-action.view:hover {
    background: #1c355c;
    color: #9abbff;
    border-color: #365883;

    box-shadow:
        0 5px 14px rgba(80, 130, 220, .18);
}


body.dark-mode .evrak-action.edit {
    background: #292344;
    color: #ad9aff;
    border-color: #42366a;
}

body.dark-mode .evrak-action.edit:hover {
    background: #332b55;
    color: #c0b2ff;
    border-color: #574b81;

    box-shadow:
        0 5px 14px rgba(130, 100, 220, .18);
}


body.dark-mode .evrak-action.success {
    background: #173527;
    color: #7bd8a5;
    border-color: #28543d;
}

body.dark-mode .evrak-action.success:hover {
    background: #1c412f;
    color: #91e4b6;
    border-color: #346c4e;

    box-shadow:
        0 5px 14px rgba(70, 180, 115, .16);
}


body.dark-mode .evrak-action.danger {
    background: #392125;
    color: #f2a3a3;
    border-color: #5b3036;
}

body.dark-mode .evrak-action.danger:hover {
    background: #47262b;
    color: #ffb4b4;
    border-color: #704047;

    box-shadow:
        0 5px 14px rgba(220, 90, 90, .16);
}


body.dark-mode .evrak-action.back {
    background: #1b2638;
    color: #aebbd0;
    border-color: #304056;
}

body.dark-mode .evrak-action.back:hover {
    background: #233147;
    color: #c3cfdf;
    border-color: #3d506a;

    box-shadow:
        0 5px 14px rgba(90, 120, 155, .14);
}


body.dark-mode .evrak-no-file {
    background: #1a2434;
    color: #8997aa;
    border-color: #2d3a4d;
}


/* =========================================================
   MOBİL
========================================================= */

@media (max-width: 700px) {

    .evrak-actions {
        gap: 5px;
    }

    .evrak-action {
        width: 34px;
        height: 34px;
        border-radius: 9px;
    }

    .evrak-action svg {
        width: 15px;
        height: 15px;
    }

}


        /* =========================================================
           TABLO BOŞ DURUM
        ========================================================= */

        .evrak-empty {
            min-height: 250px;

            display: flex;

            flex-direction: column;

            align-items: center;
            justify-content: center;

            gap: 10px;

            color: var(--text-muted);
        }


        .evrak-empty-icon {
            width: 52px;
            height: 52px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 14px;

            background: #f1f4f8;

            color: #98a5b7;
        }


        .evrak-empty-icon svg {
            width: 24px;
            height: 24px;
        }


        .evrak-empty span {
            font-size: 13px;
        }


        /* =========================================================
           KULLANICI MENÜSÜ
        ========================================================= */

        .user-dropdown-wrapper {
            position: relative;
        }


        .user-dropdown-wrapper .user-area {
            cursor: pointer;
        }


        .user-dropdown {
            position: absolute;

            top: calc(100% + 10px);
            right: 0;

            min-width: 165px;

            padding: 6px;

            background: var(--white);

            border:
                1px solid var(--border);

            border-radius: 11px;

            box-shadow:
                0 10px 28px
                rgba(25, 45, 80, 0.12);

            opacity: 0;
            visibility: hidden;

            transform:
                translateY(-5px);

            transition:
                opacity .18s ease,
                transform .18s ease,
                visibility .18s ease;

            z-index: 5000;
        }


        .user-dropdown.show {
            opacity: 1;
            visibility: visible;

            transform:
                translateY(0);
        }


        .user-dropdown-item {
            display: flex;

            align-items: center;

            gap: 10px;

            width: 100%;

            padding:
                10px 11px;

            border-radius: 8px;

            color: #b33f3f;

            font-size: 13px;
            font-weight: 600;

            text-decoration: none;
        }


        .user-dropdown-item:hover {
            background: #fff1f1;
            color: #a73535;
        }


        .user-dropdown-item svg {
            width: 17px;
            height: 17px;
        }


        /* =========================================================
           DARK MODE
        ========================================================= */

        body.dark-mode .evrak-search-input {
            background: #111b2d;
            border-color: #2a394f;
            color: #edf2f8;
        }


        body.dark-mode .evrak-search-input::placeholder {
            color: #6f7e95;
        }


        body.dark-mode .evrak-search-submit {
            color: #91a0b7;
        }


        body.dark-mode .evrak-search-submit:hover {
            background: #1d2d47;
            color: #7fa8ff;
        }


        body.dark-mode .evrak-table thead {
            background: #101a2b;
        }


        body.dark-mode .evrak-table th,
        body.dark-mode .evrak-table td {
            border-color: #26344a;
        }


        body.dark-mode .evrak-table tbody tr:hover {
            background: #172236;
        }


        body.dark-mode .evrak-person-icon {
            background: #172c50;
            color: #79a2ff;
        }


        body.dark-mode .evrak-number {
            background: #172236;
            color: #aebbd0;
        }


        body.dark-mode .evrak-no-file {
            background: #172236;
            color: #7c8ba1;
        }


        body.dark-mode .evrak-action.view,
        body.dark-mode .evrak-action.edit,
        body.dark-mode .evrak-action.back {
            background: #172236;
            border-color: #2a394f;
            color: #c8d3e3;
        }


        body.dark-mode .evrak-action.view:hover,
        body.dark-mode .evrak-action.edit:hover {
            background: #1d2d47;
            border-color: #355277;
            color: #7fa8ff;
        }


        body.dark-mode .evrak-action.success {
            background: #143022;
            border-color: #23523b;
            color: #7bd5a2;
        }


        body.dark-mode .evrak-action.danger {
            background: #321d20;
            border-color: #563034;
            color: #f0a2a2;
        }


        body.dark-mode .evrak-empty-icon {
            background: #172236;
            color: #64748a;
        }


        body.dark-mode .user-dropdown {
            background: #121b2d;
            border-color: #26344a;
        }


        body.dark-mode .user-dropdown-item {
            color: #f0a2a2;
        }


        body.dark-mode .user-dropdown-item:hover {
            background: #321d20;
            color: #ffb0b0;
        }


        /* =========================================================
           RESPONSIVE
        ========================================================= */

        @media (max-width: 1100px) {

            .evrak-list-header {
                align-items: flex-start;

                flex-direction: column;
            }

            .evrak-filters {
                width: 100%;
            }

        }


        @media (max-width: 900px) {

            .evrak-stat-grid {
                grid-template-columns:
                    1fr;
            }

        }


        @media (max-width: 700px) {

            .evrak-topbar-search {
                display: none;
            }

            .evrak-list-header {
                gap: 14px;
            }

        }
        /* =========================================================
   EVRAK FORM BUTONLARI
========================================================= */

.evrak-page .view-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    gap: 8px;

    min-height: 40px;

    padding: 0 16px;

    border: 1px solid #d7e1f0;
    border-radius: 10px;

    background: #f5f8fd;
    color: #45607f;

    font-size: 12px;
    font-weight: 700;

    text-decoration: none;

    cursor: pointer;

    transition:
        transform .18s ease,
        background-color .18s ease,
        border-color .18s ease,
        color .18s ease,
        box-shadow .18s ease;
}

.evrak-page .view-button svg {
    width: 16px;
    height: 16px;

    stroke-width: 2.2;
}

.evrak-page .view-button:hover {
    transform: translateY(-2px);

    background: #eaf1ff;
    border-color: #c8d8f7;

    color: #3372f4;

    box-shadow:
        0 5px 14px rgba(53, 109, 243, .12);
}


/* DARK MODE */

body.dark-mode .evrak-page .view-button {
    background: #172236;
    border-color: #2d3d54;
    color: #c2cede;
}

body.dark-mode .evrak-page .view-button:hover {
    background: #1d2d47;
    border-color: #3b5577;
    color: #82aaff;

    box-shadow:
        0 5px 14px rgba(80, 130, 220, .15);
}

    </style>

</head>


<body>

<div class="dashboard">


    <!-- =====================================================
         SIDEBAR
    ====================================================== -->

    <aside class="sidebar">

        <div class="brand">

            <div class="brand-logo">
                BT
            </div>

            <div>

                <h2>
                    BİRİM TAKİP
                </h2>

                <span>
                    Yönetim Sistemi
                </span>

            </div>

        </div>


        <nav class="sidebar-menu">


            <a
                href="../dashboard.php"
                class="sidebar-link"
            >

                <i
                    data-lucide="house"
                    class="menu-icon"
                ></i>

                <span>
                    Ana Sayfa
                </span>

            </a>


            <a
                href="birimler.php"
                class="sidebar-link"
            >

                <i
                    data-lucide="building-2"
                    class="menu-icon"
                ></i>

                <span>
                    Birimler
                </span>

            </a>


            <a
                href="personeller.php"
                class="sidebar-link"
            >

                <i
                    data-lucide="users"
                    class="menu-icon"
                ></i>

                <span>
                    Personeller
                </span>

            </a>


            <a
                href="gorevler.php"
                class="sidebar-link"
            >

                <i
                    data-lucide="clipboard-list"
                    class="menu-icon"
                ></i>

                <span>
                    Görevler
                </span>

            </a>


            <a
                href="evraklar.php"
                class="sidebar-link active"
            >

                <i
                    data-lucide="file-text"
                    class="menu-icon"
                ></i>

                <span>
                    Evraklar
                </span>

            </a>

        </nav>


        <div class="sidebar-bottom">

            <a
                href="../logout.php"
                class="sidebar-link logout"
            >

                <i
                    data-lucide="log-out"
                    class="menu-icon"
                ></i>

                <span>
                    Çıkış Yap
                </span>

            </a>

        </div>

    </aside>


    <!-- =====================================================
         ANA İÇERİK
    ====================================================== -->

    <main class="main-content">


        <!-- TOPBAR -->

        <header class="topbar">

            <div class="topbar-left">

                <button
                    type="button"
                    class="menu-button"
                >

                    <i
                        data-lucide="menu"
                    ></i>

                </button>

                <span class="page-title">
                    Evraklar
                </span>

            </div>


            <div class="topbar-right">


                <!-- ARAMA -->

                <div class="evrak-topbar-search">

                    <form
                        method="GET"
                        action="evraklar.php"
                    >

                        <input
                            type="hidden"
                            name="gorunum"
                            value="<?= temizle($gorunum) ?>"
                        >


                        <i
                            data-lucide="search"
                            class="evrak-search-icon"
                        ></i>


                        <input
                            type="text"
                            name="arama"
                            class="evrak-search-input"
                            placeholder="Evrak, personel veya evrak no ara..."
                            value="<?= temizle($arama) ?>"
                            autocomplete="off"
                        >


                        <button
                            type="submit"
                            class="evrak-search-submit"
                            title="Ara"
                        >

                            <i
                                data-lucide="search"
                            ></i>

                        </button>

                    </form>

                </div>


                <!-- TEMA -->

                <div class="theme-switch">

                    <button
                        type="button"
                        class="theme-button active"
                        id="lightTheme"
                    >

                        <i
                            data-lucide="sun"
                        ></i>

                    </button>


                    <button
                        type="button"
                        class="theme-button"
                        id="darkTheme"
                    >

                        <i
                            data-lucide="moon"
                        ></i>

                    </button>

                </div>


                <div class="topbar-divider"></div>


                <!-- KULLANICI -->

                <div class="user-dropdown-wrapper">

                    <div
                        class="user-area"
                        id="userArea"
                    >

                        <div class="user-avatar">

                            <?=
                                strtoupper(
                                    substr(
                                        $_SESSION[
                                            "kullanici_adi"
                                        ],
                                        0,
                                        1
                                    )
                                )
                            ?>

                        </div>


                        <div class="user-text">

                            <strong>

                                <?= temizle(
                                    $_SESSION[
                                        "kullanici_adi"
                                    ]
                                ) ?>

                            </strong>


                            <span>

                                <?= temizle(
                                    $_SESSION["rol"]
                                ) ?>

                            </span>

                        </div>


                        <i
                            data-lucide="chevron-down"
                            class="user-arrow"
                        ></i>

                    </div>


                    <div
                        class="user-dropdown"
                        id="userDropdown"
                    >

                        <a
                            href="../logout.php"
                            class="user-dropdown-item"
                        >

                            <i
                                data-lucide="log-out"
                            ></i>

                            <span>
                                Çıkış Yap
                            </span>

                        </a>

                    </div>

                </div>

            </div>

        </header>


        <!-- =====================================================
             SAYFA
        ====================================================== -->

        <section
            class="page-content evrak-page"
        >


            <div class="welcome-section">

                <h1>
                    Evraklar
                </h1>

                <p>
                    Personellere ait evrakları tek bir ekrandan yönetin.
                </p>

            </div>


            <!-- MESAJ -->

            <?php if ($mesaj !== ""): ?>

                <div class="evrak-message">

                    <i
                        data-lucide="circle-check"
                    ></i>

                    <span>
                        <?= temizle($mesaj) ?>
                    </span>

                </div>

            <?php endif; ?>


            <?php if ($hata !== ""): ?>

                <div class="evrak-error">

                    <i
                        data-lucide="circle-alert"
                    ></i>

                    <span>
                        <?= temizle($hata) ?>
                    </span>

                </div>

            <?php endif; ?>


            <!-- =================================================
                 İSTATİSTİKLER
            ================================================== -->

            <div class="evrak-stat-grid">


                <div class="stat-card">

                    <div class="stat-icon orange">

                        <i
                            data-lucide="clock-3"
                        ></i>

                    </div>

                    <div class="stat-info">

                        <span>
                            Bekleyen Evrak
                        </span>

                        <strong>
                            <?= $bekleyenEvrakSayisi ?>
                        </strong>

                    </div>

                </div>


                <div class="stat-card">

                    <div class="stat-icon green">

                        <i
                            data-lucide="circle-check"
                        ></i>

                    </div>

                    <div class="stat-info">

                        <span>
                            Onaylanan Evrak
                        </span>

                        <strong>
                            <?= $onaylananEvrakSayisi ?>
                        </strong>

                    </div>

                </div>


                <div class="stat-card">

                    <div class="stat-icon purple">

                        <i
                            data-lucide="circle-x"
                        ></i>

                    </div>

                    <div class="stat-info">

                        <span>
                            Reddedilen Evrak
                        </span>

                        <strong>
                            <?= $reddedilenEvrakSayisi ?>
                        </strong>

                    </div>

                </div>

            </div>


            <!-- =================================================
                 EVRAK EKLE / DÜZENLE
            ================================================== -->
<div
    class="large-card"
    id="evrakFormCard"
    style="<?= $duzenlenecekEvrak ? "" : "display:none;" ?>"
>

    <div class="card-title-area">
                    <div>

                        <h2>

                            <?= $duzenlenecekEvrak
                                ? "Evrak Düzenle"
                                : "Yeni Evrak Ekle"
                            ?>

                        </h2>

                        <p>

                            <?= $duzenlenecekEvrak
                                ? "Evrak bilgilerini güncelleyin."
                                : "Sisteme yeni bir personel evrakı ekleyin."
                            ?>

                        </p>

                    </div>

                </div>


                <form
                    method="POST"
                    enctype="multipart/form-data"
                >

                    <input
                        type="hidden"
                        name="islem"
                        value="<?= $duzenlenecekEvrak
                            ? "guncelle"
                            : "ekle"
                        ?>"
                    >


                    <?php if ($duzenlenecekEvrak): ?>

                        <input
                            type="hidden"
                            name="id"
                            value="<?=
                                (int)
                                $duzenlenecekEvrak["id"]
                            ?>"
                        >

                    <?php endif; ?>


                    <div
                        style="
                            display:grid;
                            grid-template-columns:
                                repeat(
                                    2,
                                    minmax(0,1fr)
                                );
                            gap:20px;
                        "
                    >


                        <!-- PERSONEL -->

                        <div>

                            <label
                                for="personel_id"
                                style="
                                    display:block;
                                    margin-bottom:8px;
                                    font-weight:600;
                                "
                            >
                                Personel
                            </label>


                            <select
                                name="personel_id"
                                id="personel_id"
                                required
                                style="
                                    width:100%;
                                    padding:12px;
                                    border-radius:10px;
                                "
                            >

                                <option value="">
                                    Personel seçin
                                </option>


                                <?php foreach (
                                    $personeller as $personel
                                ): ?>

                                    <option
                                        value="<?=
                                            (int)
                                            $personel["id"]
                                        ?>"
                                        <?=
                                            (
                                                $duzenlenecekEvrak &&
                                                $duzenlenecekEvrak[
                                                    "personel_id"
                                                ] == $personel["id"]
                                            )
                                                ? "selected"
                                                : ""
                                        ?>
                                    >

                                        <?= temizle(
                                            $personel["ad"]
                                            . " "
                                            . $personel["soyad"]
                                        ) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <!-- EVRAK ADI -->

                        <div>

                            <label
                                for="evrak_adi"
                                style="
                                    display:block;
                                    margin-bottom:8px;
                                    font-weight:600;
                                "
                            >
                                Evrak Adı
                            </label>


                            <input
                                type="text"
                                name="evrak_adi"
                                id="evrak_adi"
                                placeholder="Örn: İş sözleşmesi"
                                value="<?= $duzenlenecekEvrak
                                    ? temizle(
                                        $duzenlenecekEvrak[
                                            "evrak_adi"
                                        ]
                                    )
                                    : ""
                                ?>"
                                required
                                style="
                                    width:100%;
                                    padding:12px;
                                    border-radius:10px;
                                "
                            >

                        </div>


                        <!-- EVRAK NO -->

                        <div>

                            <label
                                for="evrak_no"
                                style="
                                    display:block;
                                    margin-bottom:8px;
                                    font-weight:600;
                                "
                            >
                                Evrak No
                            </label>


                            <input
                                type="text"
                                name="evrak_no"
                                id="evrak_no"
                                placeholder="Örn: EVR-2026-001"
                                value="<?= $duzenlenecekEvrak
                                    ? temizle(
                                        $duzenlenecekEvrak[
                                            "evrak_no"
                                        ]
                                    )
                                    : ""
                                ?>"
                                style="
                                    width:100%;
                                    padding:12px;
                                    border-radius:10px;
                                "
                            >

                        </div>


                        <!-- TARİH -->

                        <div>

                            <label
                                for="tarih"
                                style="
                                    display:block;
                                    margin-bottom:8px;
                                    font-weight:600;
                                "
                            >
                                Evrak Tarihi
                            </label>


                            <input
                                type="date"
                                name="tarih"
                                id="tarih"
                                value="<?= $duzenlenecekEvrak
                                    ? temizle(
                                        $duzenlenecekEvrak[
                                            "tarih"
                                        ]
                                    )
                                    : ""
                                ?>"
                                required
                                style="
                                    width:100%;
                                    padding:12px;
                                    border-radius:10px;
                                "
                            >

                        </div>


                        <!-- DOSYA -->

                        <div
                            style="
                                grid-column:1 / -1;
                            "
                        >

                            <label
                                for="evrak_dosyasi"
                                style="
                                    display:block;
                                    margin-bottom:10px;
                                    font-weight:600;
                                "
                            >
                                Evrak Dosyası
                            </label>


                            <label
                                for="evrak_dosyasi"
                                class="evrak-file-area"
                            >

                                <div class="evrak-file-icon">

                                    <i
                                        data-lucide="upload-cloud"
                                    ></i>

                                </div>


                                <div class="evrak-file-text">

                                    <strong>
                                        Dosya yüklemek için tıklayın
                                    </strong>

                                    <span id="fileName">

                                        <?php if (
                                            $duzenlenecekEvrak &&
                                            !empty(
                                                $duzenlenecekEvrak[
                                                    "dosya_yolu"
                                                ]
                                            )
                                        ): ?>

                                            Mevcut dosya korunuyor

                                        <?php else: ?>

                                            Dosya yüklemek isteğe bağlıdır

                                        <?php endif; ?>

                                    </span>

                                </div>


                                <div class="evrak-file-button">
                                    Dosya Seç
                                </div>

                            </label>


                            <input
                                type="file"
                                name="evrak_dosyasi"
                                id="evrak_dosyasi"
                                accept="
                                    .pdf,
                                    .doc,
                                    .docx,
                                    .xls,
                                    .xlsx,
                                    .jpg,
                                    .jpeg,
                                    .png
                                "
                                style="display:none;"
                            >


                            <div
                                style="
                                    margin-top:8px;
                                    font-size:12px;
                                    color:var(--text-muted);
                                "
                            >

                                PDF, DOC, DOCX, XLS, XLSX, JPG veya PNG
                                • Maksimum 10 MB
                                • İsteğe bağlı

                                <?php if (
                                    $duzenlenecekEvrak &&
                                    !empty(
                                        $duzenlenecekEvrak[
                                            "dosya_yolu"
                                        ]
                                    )
                                ): ?>

                                    <br>

                                    Yeni dosya seçmezseniz mevcut dosya korunur.

                                <?php endif; ?>

                            </div>

                        </div>


                        <!-- AÇIKLAMA -->

                        <div
                            style="
                                grid-column:1 / -1;
                            "
                        >

                            <label
                                for="aciklama"
                                style="
                                    display:block;
                                    margin-bottom:8px;
                                    font-weight:600;
                                "
                            >
                                Açıklama
                            </label>


                            <textarea
                                name="aciklama"
                                id="aciklama"
                                placeholder="Evrak hakkında açıklama..."
                                style="
                                    width:100%;
                                    min-height:110px;
                                    padding:12px;
                                    border-radius:10px;
                                    resize:vertical;
                                "
                            ><?= $duzenlenecekEvrak
                                ? temizle(
                                    $duzenlenecekEvrak[
                                        "aciklama"
                                    ]
                                )
                                : ""
                            ?></textarea>

                        </div>

                    </div>


                    <!-- BUTONLAR -->

                    <div
                        style="
                            display:flex;
                            gap:10px;
                            margin-top:20px;
                        "
                    >

                        <button
                            type="submit"
                            class="view-button"
                        >

                            <i
                                data-lucide="<?= $duzenlenecekEvrak
                                    ? "save"
                                    : "plus"
                                ?>"
                            ></i>

                            <?= $duzenlenecekEvrak
                                ? "Değişiklikleri Kaydet"
                                : "Evrak Ekle"
                            ?>

                        </button>


<a
    href="evraklar.php"
    class="view-button"
>
    <i data-lucide="arrow-left"></i>
    Geri
</a>

                    </div>

                </form>

            </div>


            <!-- =================================================
                 EVRAK LİSTESİ
            ================================================== -->

            <div
                class="large-card evrak-list-card"
                 id="evrakListCard"
            >


                <div
                    class="evrak-list-header"
                >

<?php if (!$duzenlenecekEvrak): ?>

    <button
        type="button"
        class="view-button"
        onclick="evrakFormunuAc()"
    >
        <i data-lucide="plus"></i>
        Evrak Ekle
    </button>

<?php endif; ?>


                    <!-- FİLTRELER -->

                    <div
                        class="evrak-filters"
                    >

                        <a
                            href="evraklar.php"
                            class="evrak-filter
                                <?= $gorunum === "tum"
                                    ? "active"
                                    : ""
                                ?>"
                        >

                            <i
                                data-lucide="files"
                            ></i>

                            Tümü

                        </a>


                        <a
                            href="evraklar.php?gorunum=beklemede"
                            class="evrak-filter
                                <?= $gorunum === "beklemede"
                                    ? "active"
                                    : ""
                                ?>"
                        >

                            <i
                                data-lucide="clock-3"
                            ></i>

                            Beklemede

                        </a>


                        <a
                            href="evraklar.php?gorunum=onaylandi"
                            class="evrak-filter
                                <?= $gorunum === "onaylandi"
                                    ? "active"
                                    : ""
                                ?>"
                        >

                            <i
                                data-lucide="circle-check"
                            ></i>

                            Onaylandı

                        </a>


                        <a
                            href="evraklar.php?gorunum=reddedildi"
                            class="evrak-filter
                                <?= $gorunum === "reddedildi"
                                    ? "active"
                                    : ""
                                ?>"
                        >

                            <i
                                data-lucide="circle-x"
                            ></i>

                            Reddedildi

                        </a>

                    </div>

                </div>


                <!-- TABLO -->

                <?php if (
                    count($evraklar) > 0
                ): ?>


                    <div
                        class="evrak-table-wrapper"
                    >

                        <table
                            class="evrak-table"
                        >

                            <thead>

                                <tr>

                                    <th>
                                        #
                                    </th>

                                    <th>
                                        Personel
                                    </th>

                                    <th>
                                        Evrak
                                    </th>

                                    <th>
                                        Evrak No
                                    </th>

                                    <th>
                                        Tarih
                                    </th>

                                    <th>
                                        Durum
                                    </th>

                                    <th>
                                        İşlemler
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                                <?php
                                $sira = 1;
                                ?>


                                <?php foreach (
                                    $evraklar
                                    as $evrak
                                ): ?>


                                    <tr>


                                        <!-- SIRA -->

                                        <td>
                                            <?= $sira ?>
                                        </td>


                                        <!-- PERSONEL -->

                                        <td>

                                            <div
                                                class="evrak-person"
                                            >

                                                <div
                                                    class="evrak-person-icon"
                                                >

                                                    <i
                                                        data-lucide="user"
                                                    ></i>

                                                </div>


                                                <span
                                                    class="evrak-person-name"
                                                >

                                                    <?= temizle(
                                                        $evrak[
                                                            "personel_adi"
                                                        ]
                                                        ?? "-"
                                                    ) ?>

                                                </span>

                                            </div>

                                        </td>


                                        <!-- EVRAK -->

                                        <td>

                                            <div
                                                class="evrak-name"
                                            >

                                                <strong>

                                                    <?= temizle(
                                                        $evrak[
                                                            "evrak_adi"
                                                        ]
                                                    ) ?>

                                                </strong>


                                                <?php if (
                                                    !empty(
                                                        $evrak[
                                                            "aciklama"
                                                        ]
                                                    )
                                                ): ?>

                                                    <span
                                                        class="evrak-description"
                                                    >

                                                        <?= temizle(
                                                            $evrak[
                                                                "aciklama"
                                                            ]
                                                        ) ?>

                                                    </span>

                                                <?php endif; ?>

                                            </div>

                                        </td>


                                        <!-- EVRAK NO -->

                                        <td>

                                            <?php if (
                                                !empty(
                                                    $evrak[
                                                        "evrak_no"
                                                    ]
                                                )
                                            ): ?>

                                                <span
                                                    class="evrak-number"
                                                >

                                                    <?= temizle(
                                                        $evrak[
                                                            "evrak_no"
                                                        ]
                                                    ) ?>

                                                </span>

                                            <?php else: ?>

                                                <span
                                                    class="evrak-number"
                                                >
                                                    -
                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <!-- TARİH -->

                                        <td>

                                            <span
                                                class="evrak-date"
                                            >

                                                <?php

                                                echo !empty(
                                                    $evrak["tarih"]
                                                )
                                                    ? date(
                                                        "d.m.Y",
                                                        strtotime(
                                                            $evrak[
                                                                "tarih"
                                                            ]
                                                        )
                                                    )
                                                    : "-";

                                                ?>

                                            </span>

                                        </td>


                                        <!-- DURUM -->

                                        <td>

                                            <?php

                                            $durum =
                                                $evrak["durum"]
                                                ?? "beklemede";

                                            ?>


                                            <?php if (
                                                $durum ===
                                                "onaylandi"
                                            ): ?>

                                                <span
                                                    class="
                                                        evrak-status
                                                        approved
                                                    "
                                                >

                                                    <i
                                                        data-lucide="circle-check"
                                                    ></i>

                                                    Onaylandı

                                                </span>


                                            <?php elseif (
                                                $durum ===
                                                "reddedildi"
                                            ): ?>

                                                <span
                                                    class="
                                                        evrak-status
                                                        rejected
                                                    "
                                                >

                                                    <i
                                                        data-lucide="circle-x"
                                                    ></i>

                                                    Reddedildi

                                                </span>


                                                <?php if (
                                                    !empty(
                                                        $evrak[
                                                            "red_nedeni"
                                                        ]
                                                    )
                                                ): ?>

                                                    <div
                                                        class="
                                                            red-reason
                                                        "
                                                        title="<?= temizle(
                                                            $evrak[
                                                                "red_nedeni"
                                                            ]
                                                        ) ?>"
                                                    >

                                                        <?= temizle(
                                                            $evrak[
                                                                "red_nedeni"
                                                            ]
                                                        ) ?>

                                                    </div>

                                                <?php endif; ?>


                                            <?php else: ?>

                                                <span
                                                    class="
                                                        evrak-status
                                                        pending
                                                    "
                                                >

                                                    <i
                                                        data-lucide="clock-3"
                                                    ></i>

                                                    Beklemede

                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <!-- İŞLEMLER -->

                                        <!-- İŞLEMLER -->

<td>

    <div class="evrak-actions">

        <!-- GÖRÜNTÜLE -->

        <?php if (
            !empty(
                $evrak["dosya_yolu"]
            )
        ): ?>

            <a
                href="evrak-goruntule.php?id=<?= (int) $evrak["id"] ?>"
                target="_blank"
                class="evrak-action view"
                title="Dosyayı Görüntüle"
                aria-label="Dosyayı Görüntüle"
            >

                <i
                    data-lucide="eye"
                ></i>

            </a>

        <?php else: ?>

            <span
                class="evrak-no-file"
                title="Bu evrak için dosya yüklenmemiş"
            >

                <i
                    data-lucide="file-x"
                ></i>

                Dosya yok

            </span>

        <?php endif; ?>


        <!-- DÜZENLE -->

        <a
            href="evraklar.php?duzenle=<?= (int) $evrak["id"] ?>"
            class="evrak-action edit"
            title="Düzenle"
            aria-label="Düzenle"
        >

            <i
                data-lucide="pencil"
            ></i>

        </a>


        <!-- SADECE YÖNETİCİ -->

<?php if ($yoneticiMi): ?>


            <?php if (
                $durum ===
                "beklemede"
            ): ?>


                <!-- ONAYLA -->

                <form
                    method="POST"
                    style="display:inline;"
                >

                    <input
                        type="hidden"
                        name="islem"
                        value="durum"
                    >

                    <input
                        type="hidden"
                        name="id"
                        value="<?= (int) $evrak["id"] ?>"
                    >

                    <input
                        type="hidden"
                        name="durum"
                        value="onaylandi"
                    >

                    <button
                        type="submit"
                        class="evrak-action success"
                        title="Onayla"
                        aria-label="Onayla"
                    >

                        <i
                            data-lucide="circle-check"
                        ></i>

                    </button>

                </form>


                <!-- REDDET -->

                <button
                    type="button"
                    class="evrak-action danger"
                    title="Reddet"
                    aria-label="Reddet"
                    onclick="
                        reddetEvrak(
                            <?= (int) $evrak["id"] ?>
                        );
                    "
                >

                    <i
                        data-lucide="circle-x"
                    ></i>

                </button>


            <?php elseif (
                $durum ===
                "onaylandi"
            ): ?>


                <!-- TEKRAR İNCELE -->

                <form
                    method="POST"
                    style="display:inline;"
                >

                    <input
                        type="hidden"
                        name="islem"
                        value="durum"
                    >

                    <input
                        type="hidden"
                        name="id"
                        value="<?= (int) $evrak["id"] ?>"
                    >

                    <input
                        type="hidden"
                        name="durum"
                        value="beklemede"
                    >

                    <button
                        type="submit"
                        class="evrak-action back"
                        title="Tekrar İncele"
                        aria-label="Tekrar İncele"
                    >

                        <i
                            data-lucide="rotate-ccw"
                        ></i>

                    </button>

                </form>


            <?php else: ?>


                <!-- REDDEDİLENİ TEKRAR İNCELE -->

                <form
                    method="POST"
                    style="display:inline;"
                >

                    <input
                        type="hidden"
                        name="islem"
                        value="durum"
                    >

                    <input
                        type="hidden"
                        name="id"
                        value="<?= (int) $evrak["id"] ?>"
                    >

                    <input
                        type="hidden"
                        name="durum"
                        value="beklemede"
                    >

                    <button
                        type="submit"
                        class="evrak-action back"
                        title="Tekrar İncele"
                        aria-label="Tekrar İncele"
                    >

                        <i
                            data-lucide="rotate-ccw"
                        ></i>

                    </button>

                </form>


            <?php endif; ?>


        <?php endif; ?>

    </div>

</td>

                                    </tr>


                                    <?php
                                    $sira++;
                                    ?>


                                <?php endforeach; ?>


                            </tbody>

                        </table>

                    </div>


                <?php else: ?>


                    <div
                        class="evrak-empty"
                    >

                        <div
                            class="evrak-empty-icon"
                        >

                            <i
                                data-lucide="file-text"
                            ></i>

                        </div>


                        <span>

                            <?php if (
                                $arama !== ""
                            ): ?>

                                Aramanızla eşleşen evrak bulunamadı.

                            <?php else: ?>

                                Henüz kayıtlı evrak bulunmuyor.

                            <?php endif; ?>

                        </span>

                    </div>

                <?php endif; ?>


            </div>

        </section>

    </main>

</div>


<!-- =====================================================
     JAVASCRIPT
====================================================== -->

<script src="../js/script.js"></script>

<script>

    lucide.createIcons();
    function evrakFormunuAc()
{
    const formCard =
        document.getElementById("evrakFormCard");

    if (!formCard) {
        return;
    }

formCard.style.display = "block";

const listCard =
    document.getElementById("evrakListCard");

if (listCard) {
    listCard.style.display = "none";
}

formCard.scrollIntoView({
    behavior: "smooth",
    block: "start"
});
}


    /* =====================================================
       DOSYA ADI
    ===================================================== */

    const fileInput =
        document.getElementById(
            "evrak_dosyasi"
        );


    const fileName =
        document.getElementById(
            "fileName"
        );


    if (
        fileInput &&
        fileName
    ) {

        fileInput.addEventListener(
            "change",
            function () {

if (
    this.files &&
    this.files.length > 0
) {

    fileName.textContent =
        this.files[0].name;

    const fileArea =
        document.querySelector(
            ".evrak-file-area"
        );

    if (fileArea) {
        fileArea.classList.add(
            "file-selected"
        );
    }

} else {

    fileName.textContent =
        "Dosya yüklemek isteğe bağlıdır";

    const fileArea =
        document.querySelector(
            ".evrak-file-area"
        );

    if (fileArea) {
        fileArea.classList.remove(
            "file-selected"
        );
    }
}

            }
        );

    }


    /* =====================================================
       EVRAK REDDET
    ===================================================== */

    function reddetEvrak(id)
    {

        const neden =
            prompt(
                "Bu evrakı neden reddediyorsunuz?"
            );


        if (neden === null) {
            return;
        }


        if (neden.trim() === "") {

            alert(
                "Lütfen reddetme nedenini belirtin."
            );

            return;
        }


        const form =
            document.createElement("form");

        form.method =
            "POST";

        form.action =
            "evraklar.php";


        const islem =
            document.createElement("input");

        islem.type =
            "hidden";

        islem.name =
            "islem";

        islem.value =
            "durum";


        const idInput =
            document.createElement("input");

        idInput.type =
            "hidden";

        idInput.name =
            "id";

        idInput.value =
            id;


        const durum =
            document.createElement("input");

        durum.type =
            "hidden";

        durum.name =
            "durum";

        durum.value =
            "reddedildi";


        const redNedeni =
            document.createElement("input");

        redNedeni.type =
            "hidden";

        redNedeni.name =
            "red_nedeni";

        redNedeni.value =
            neden.trim();


        form.appendChild(
            islem
        );

        form.appendChild(
            idInput
        );

        form.appendChild(
            durum
        );

        form.appendChild(
            redNedeni
        );


        document.body.appendChild(
            form
        );


        form.submit();
    }

</script>


</body>

</html>