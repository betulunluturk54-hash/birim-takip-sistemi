<?php

session_start();

if (!isset($_SESSION["kullanici_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";


/* ==========================================
   GİRİŞ YAPAN KULLANICI
========================================== */

$kullaniciStmt = $db->prepare("
    SELECT
        id,
        ad,
        soyad,
        kullanici_adi,
        rol
    FROM kullanicilar
    WHERE id = ?
    LIMIT 1
");

$kullaniciStmt->execute([
    $_SESSION["kullanici_id"]
]);

$giris_yapan_kullanici = $kullaniciStmt->fetch(PDO::FETCH_ASSOC);

if (!$giris_yapan_kullanici) {
    session_destroy();
    header("Location: ../login.php");
    exit;
}


/* ==========================================
   AD SOYAD
========================================== */

$kullanici_ad_soyad = trim(
    ($giris_yapan_kullanici["ad"] ?? "") .
    " " .
    ($giris_yapan_kullanici["soyad"] ?? "")
);

if ($kullanici_ad_soyad === "") {
    $kullanici_ad_soyad = $giris_yapan_kullanici["kullanici_adi"];
}


/* ==========================================
   ROL ETİKETİ
========================================== */

if (
    in_array(
        $giris_yapan_kullanici["rol"],
        ["yonetici", "admin"],
        true
    )
) {
    $rol_etiketi = "Yönetici";
} elseif ($giris_yapan_kullanici["rol"] === "personel") {
    $rol_etiketi = "Personel";
} else {
    $rol_etiketi = "Kullanıcı";
}


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

function temizle(string $veri): string
{
    return htmlspecialchars(
        $veri,
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
    "reddedildi",
    "arsivlendi"
];

if (
    !in_array(
        $gorunum,
        $izinliGorunumler,
        true
    )
) {
    $gorunum = "tum";
}


/* =========================================================
   ARAMA
========================================================= */

$arama = trim(
    $_GET["arama"] ?? ""
);

/* =========================================================
   DOSYA / EVRAK SİLME
========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    (
        ($_POST["islem"] ?? "") === "dosya_sil" ||
        ($_POST["islem"] ?? "") === "evrak_sil"
    )
) {

    /* =====================================================
       SADECE YÖNETİCİ
    ===================================================== */

    if (!$yoneticiMi) {

        $hata =
            "Bu işlemi gerçekleştirme yetkiniz yok.";

    } else {

        $islem =
            $_POST["islem"] ?? "";


        /* =================================================
           TEK DOSYA SİL
        ================================================= */

        if ($islem === "dosya_sil") {

            $dosyaId =
                (int) (
                    $_POST["dosya_id"] ?? 0
                );


            if ($dosyaId <= 0) {

                $hata =
                    "Geçersiz dosya.";

            } else {

                $stmt = $db->prepare("
                    SELECT
                        id,
                        evrak_id,
                        dosya_yolu
                    FROM evrak_dosyalar
                    WHERE id = ?
                    LIMIT 1
                ");

                $stmt->execute([
                    $dosyaId
                ]);

                $silDosya =
                    $stmt->fetch(
                        PDO::FETCH_ASSOC
                    );


                if (!$silDosya) {

                    $hata =
                        "Silinecek dosya bulunamadı.";

                } else {

                    $dosyaYolu =
                        trim(
                            $silDosya["dosya_yolu"] ?? ""
                        );

                    $stmt = $db->prepare("
                        DELETE FROM evrak_dosyalar
                        WHERE id = ?
                    ");

                    $basarili =
                        $stmt->execute([
                            $dosyaId
                        ]);


                    if ($basarili) {

                        if ($dosyaYolu !== "") {

                            $dosyaYolu =
                                str_replace(
                                    "\\",
                                    "/",
                                    $dosyaYolu
                                );

                            $dosyaYolu =
                                ltrim(
                                    $dosyaYolu,
                                    "/"
                                );


                            $muhtemelDosyalar = [
                                dirname(__DIR__) .
                                DIRECTORY_SEPARATOR .
                                str_replace(
                                    "/",
                                    DIRECTORY_SEPARATOR,
                                    $dosyaYolu
                                ),

                                __DIR__ .
                                DIRECTORY_SEPARATOR .
                                str_replace(
                                    "/",
                                    DIRECTORY_SEPARATOR,
                                    $dosyaYolu
                                )
                            ];


                            foreach (
                                $muhtemelDosyalar
                                as $gercekDosya
                            ) {

                                if (
                                    is_file(
                                        $gercekDosya
                                    )
                                ) {

                                    unlink(
                                        $gercekDosya
                                    );

                                    break;
                                }
                            }
                        }


                        $mesaj =
                            "Dosya başarıyla silindi.";

                    } else {

                        $hata =
                            "Dosya silinemedi.";
                    }
                }
            }
        }


        /* =================================================
           EVRAKI TAMAMEN SİL
        ================================================= */

        if ($islem === "evrak_sil") {

            $evrakId =
                (int) (
                    $_POST["evrak_id"] ?? 0
                );


            if ($evrakId <= 0) {

                $hata =
                    "Geçersiz evrak.";

            } else {

                /* =========================================
                   EVRAKA AİT DOSYALARI GETİR
                ========================================= */

                $stmt = $db->prepare("
                    SELECT
                        id,
                        dosya_yolu
                    FROM evrak_dosyalar
                    WHERE evrak_id = ?
                ");

                $stmt->execute([
                    $evrakId
                ]);

                $dosyalar =
                    $stmt->fetchAll(
                        PDO::FETCH_ASSOC
                    );


                /* =========================================
                   DOSYA KAYITLARINI VERİTABANINDAN SİL
                ========================================= */

                $stmt = $db->prepare("
                    DELETE FROM evrak_dosyalar
                    WHERE evrak_id = ?
                ");

                $stmt->execute([
                    $evrakId
                ]);


                /* =========================================
                   FİZİKSEL DOSYALARI SİL
                ========================================= */

                foreach (
                    $dosyalar
                    as $silDosya
                ) {

                    $dosyaYolu =
                        trim(
                            $silDosya["dosya_yolu"] ?? ""
                        );


                    if ($dosyaYolu === "") {
                        continue;
                    }


                    $dosyaYolu =
                        str_replace(
                            "\\",
                            "/",
                            $dosyaYolu
                        );

                    $dosyaYolu =
                        ltrim(
                            $dosyaYolu,
                            "/"
                        );


                    $muhtemelDosyalar = [
                        dirname(__DIR__) .
                        DIRECTORY_SEPARATOR .
                        str_replace(
                            "/",
                            DIRECTORY_SEPARATOR,
                            $dosyaYolu
                        ),

                        __DIR__ .
                        DIRECTORY_SEPARATOR .
                        str_replace(
                            "/",
                            DIRECTORY_SEPARATOR,
                            $dosyaYolu
                        )
                    ];


                    foreach (
                        $muhtemelDosyalar
                        as $gercekDosya
                    ) {

                        if (
                            is_file(
                                $gercekDosya
                            )
                        ) {

                            unlink(
                                $gercekDosya
                            );

                            break;
                        }
                    }
                }


                /* =========================================
                   ESKİ TEKLİ DOSYA YOLUNU DA GETİR
                ========================================= */

                $stmt = $db->prepare("
                    SELECT
                        dosya_yolu
                    FROM evraklar
                    WHERE id = ?
                    LIMIT 1
                ");

                $stmt->execute([
                    $evrakId
                ]);

                $eskiEvrak =
                    $stmt->fetch(
                        PDO::FETCH_ASSOC
                    );


                /* =========================================
                   ESKİ DOSYA VARSA FİZİKSEL OLARAK SİL
                ========================================= */

                if (
                    !empty(
                        $eskiEvrak["dosya_yolu"]
                    )
                ) {

                    $eskiDosyaYolu =
                        trim(
                            $eskiEvrak["dosya_yolu"]
                        );


                    $eskiDosyaYolu =
                        str_replace(
                            "\\",
                            "/",
                            $eskiDosyaYolu
                        );

                    $eskiDosyaYolu =
                        ltrim(
                            $eskiDosyaYolu,
                            "/"
                        );


                    $eskiGercekDosya =
                        __DIR__ .
                        DIRECTORY_SEPARATOR .
                        str_replace(
                            "/",
                            DIRECTORY_SEPARATOR,
                            $eskiDosyaYolu
                        );


                    if (
                        is_file(
                            $eskiGercekDosya
                        )
                    ) {

                        unlink(
                            $eskiGercekDosya
                        );
                    }
                }


                /* =========================================
                   ANA EVRAK KAYDINI SİL
                ========================================= */

                $stmt = $db->prepare("
                    DELETE FROM evraklar
                    WHERE id = ?
                ");

                $basarili =
                    $stmt->execute([
                        $evrakId
                    ]);


                if ($basarili) {

                    header(
                        "Location: evraklar.php?mesaj=evrak_silindi"
                    );

                    exit;

                } else {

                    $hata =
                        "Evrak silinemedi.";
                }
            }
        }
    }
}


/* =========================================================
   EVRAK ARŞİVLE / ARŞİVDEN ÇIKAR
========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    (
        ($_POST["islem"] ?? "") === "arsivle" ||
        ($_POST["islem"] ?? "") === "arsivden_cikar"
    )
) {

    if (!$yoneticiMi) {

        $hata =
            "Bu işlemi gerçekleştirme yetkiniz yok.";

    } else {

        $evrakId =
            (int) (
                $_POST["evrak_id"] ?? 0
            );

        $islem =
            $_POST["islem"] ?? "";

        if ($evrakId <= 0) {

            $hata =
                "Geçersiz evrak.";

        } else {

            if ($islem === "arsivle") {

                $stmt = $db->prepare("
                    UPDATE evraklar
                    SET durum = 'arsivlendi'
                    WHERE id = ?
                ");

                $basarili =
                    $stmt->execute([
                        $evrakId
                    ]);

                if ($basarili) {

                    header(
                        "Location: evraklar.php?mesaj=arsivlendi"
                    );

                    exit;

                } else {

                    $hata =
                        "Evrak arşivlenemedi.";
                }

            } else {

                $stmt = $db->prepare("
                    UPDATE evraklar
                    SET durum = 'beklemede'
                    WHERE id = ?
                ");

                $basarili =
                    $stmt->execute([
                        $evrakId
                    ]);

                if ($basarili) {

                    header(
                        "Location: evraklar.php?mesaj=arsivden_cikarildi"
                    );

                    exit;

                } else {

                    $hata =
                        "Evrak arşivden çıkarılamadı.";
                }
            }
        }
    }
}


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

    $yeniDurum = trim(
        $_POST["durum"] ?? ""
    );

    $redNedeni = trim(
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
                "reddedildi",
                "arsivlendi"
            ],
            true
        )
    ) {

        $hata = "Geçersiz evrak durumu.";

    } elseif (
        $yeniDurum === "reddedildi" &&
        $redNedeni === ""
    ) {

        $hata = "Reddetme nedeni belirtilmelidir.";

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

            $mesaj = "Evrak başarıyla onaylandı.";

        } elseif ($yeniDurum === "reddedildi") {

            $mesaj = "Evrak reddedildi.";

        } else {

            $mesaj = "Evrak tekrar beklemeye alındı.";
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
                LIMIT 1
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
                LIMIT 1
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
       DOSYALAR
       BİRDEN FAZLA DOSYA YÜKLEME
    ===================================================== */

    $yeniDosyaYolu = null;
    $yuklenenDosyalar = [];

    if (
        $hata === "" &&
        isset($_FILES["evrak_dosyasi"])
    ) {

        $dosya = $_FILES["evrak_dosyasi"];

        if (
            is_array($dosya["name"])
        ) {

            $dosyaSayisi =
                count($dosya["name"]);

            for (
                $i = 0;
                $i < $dosyaSayisi;
                $i++
            ) {

                if (
                    $dosya["error"][$i] === UPLOAD_ERR_NO_FILE
                ) {
                    continue;
                }

                if (
                    $dosya["error"][$i] !== UPLOAD_ERR_OK
                ) {

                    $hata =
                        "Dosyalardan biri yüklenirken bir hata oluştu.";

                    break;
                }

                if (
                    $dosya["size"][$i] > $maxDosyaBoyutu
                ) {

                    $hata =
                        "Dosyalardan birinin boyutu 10 MB sınırını aşıyor.";

                    break;
                }

                $uzanti =
                    strtolower(
                        pathinfo(
                            $dosya["name"][$i],
                            PATHINFO_EXTENSION
                        )
                    );

                if (
                    !in_array(
                        $uzanti,
                        $izinVerilenUzantilar,
                        true
                    )
                ) {

                    $hata =
                        "Dosyalardan birinin türüne izin verilmiyor.";

                    break;
                }

                $guvenliDosyaAdi =
                    bin2hex(
                        random_bytes(16)
                    )
                    . "_"
                    . date("YmdHis")
                    . "_"
                    . $i
                    . "."
                    . $uzanti;

                $hedef =
                    $uploadDir .
                    $guvenliDosyaAdi;

                if (
                    move_uploaded_file(
                        $dosya["tmp_name"][$i],
                        $hedef
                    )
                ) {

                    $dosyaYolu =
                        "uploads/evraklar/" .
                        $guvenliDosyaAdi;

                    $yuklenenDosyalar[] = [
                        "dosya_adi" => basename($dosya["name"][$i]),
                        "dosya_yolu" => $dosyaYolu
                    ];

                    if ($yeniDosyaYolu === null) {
                        $yeniDosyaYolu = $dosyaYolu;
                    }

                } else {

                    $hata =
                        "Dosyalardan biri sunucuya kaydedilemedi.";

                    break;
                }
            }
        }
        else {

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

                    if (
                        !in_array(
                            $uzanti,
                            $izinVerilenUzantilar,
                            true
                        )
                    ) {

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

                            $yuklenenDosyalar[] = [
                                "dosya_adi" => basename($dosya["name"]),
                                "dosya_yolu" => $yeniDosyaYolu
                            ];

                        } else {

                            $hata =
                                "Dosya sunucuya kaydedilemedi.";
                        }
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

            $evrakId =
                (int) $db->lastInsertId();

            if (
                !empty($yuklenenDosyalar)
            ) {

                $dosyaStmt = $db->prepare("
                    INSERT INTO evrak_dosyalar
                    (
                        evrak_id,
                        dosya_adi,
                        dosya_yolu
                    )
                    VALUES
                    (
                        ?, ?, ?
                    )
                ");

                foreach (
                    $yuklenenDosyalar
                    as $dosya
                ) {

                    $dosyaStmt->execute([
                        $evrakId,
                        $dosya["dosya_adi"],
                        $dosya["dosya_yolu"]
                    ]);
                }
            }

            header(
                "Location: evraklar.php?mesaj=eklendi"
            );

            exit;

        } else {

            if (
                !empty($yuklenenDosyalar)
            ) {

                foreach (
                    $yuklenenDosyalar
                    as $dosya
                ) {

                    $gercekDosya =
                        __DIR__ .
                        "/" .
                        $dosya["dosya_yolu"];

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
            }

            if (
                $yeniDosyaYolu !== null
            ) {

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
                    LIMIT 1
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
                    LIMIT 1
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

                $dosyaYolu =
                    $mevcut["dosya_yolu"] ?? null;

                if (
                    !empty($yuklenenDosyalar)
                ) {
                    if (empty($dosyaYolu)) {
                        $dosyaYolu =
                            $yuklenenDosyalar[0]["dosya_yolu"];
                    }
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

                    if (
                        !empty($yuklenenDosyalar)
                    ) {

                        $dosyaStmt =
                            $db->prepare("
                                INSERT INTO evrak_dosyalar
                                (
                                    evrak_id,
                                    dosya_adi,
                                    dosya_yolu
                                )
                                VALUES
                                (
                                    ?, ?, ?
                                )
                            ");

                        foreach (
                            $yuklenenDosyalar
                            as $dosya
                        ) {

                            $dosyaStmt->execute([
                                $id,
                                $dosya["dosya_adi"],
                                $dosya["dosya_yolu"]
                            ]);
                        }
                    }

                    header(
                        "Location: evraklar.php?mesaj=guncellendi"
                    );

                    exit;

                } else {

                    if (
                        !empty($yuklenenDosyalar)
                    ) {

                        foreach (
                            $yuklenenDosyalar
                            as $dosya
                        ) {

                            $dosyaYoluSil =
                                trim(
                                    $dosya["dosya_yolu"] ?? ""
                                );

                            if (
                                $dosyaYoluSil === ""
                            ) {
                                continue;
                            }

                            $dosyaYoluSil =
                                str_replace(
                                    "\\",
                                    "/",
                                    $dosyaYoluSil
                                );

                            $dosyaYoluSil =
                                ltrim(
                                    $dosyaYoluSil,
                                    "/"
                                );

                            $gercekDosya =
                                __DIR__ .
                                DIRECTORY_SEPARATOR .
                                str_replace(
                                    "/",
                                    DIRECTORY_SEPARATOR,
                                    $dosyaYoluSil
                                );

                            if (
                                is_file(
                                    $gercekDosya
                                )
                            ) {

                                unlink(
                                    $gercekDosya
                                );
                            }
                        }
                    }

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
        case "arsivlendi":
            $mesaj = "Evrak başarıyla arşivlendi.";
            break;

        case "arsivden_cikarildi":
            $mesaj = "Evrak arşivden çıkarıldı.";
            break;

        case "eklendi":
            $mesaj = "Evrak başarıyla eklendi.";
            break;

        case "guncellendi":
            $mesaj = "Evrak başarıyla güncellendi.";
            break;

        case "onaylandi":
            $mesaj = "Evrak başarıyla onaylandı.";
            break;

        case "reddedildi":
            $mesaj = "Evrak reddedildi.";
            break;

        case "evrak_silindi":
            $mesaj = "Evrak ve bağlı dosyaları başarıyla silindi.";
            break;

        case "beklemede":
            $mesaj = "Evrak tekrar beklemeye alındı.";
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

if (
    $gorunum !== "tum"
) {

    $sql .= "
        AND e.durum = ?
    ";

    $params[] =
        $gorunum;
}


/* =========================================================
   ARAMA
========================================================= */

if (
    $arama !== ""
) {

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
   PROFESYONEL ARAMA
========================================================= */

.evrak-topbar-search {
    display: flex;
    align-items: center;
    position: relative;
}

.evrak-search-form {
    position: relative;

    display: flex;
    align-items: center;

    width: 340px;
    height: 42px;
}

.evrak-search-input {
    width: 100%;
    height: 42px;

    padding: 0 78px 0 16px;

    border: 1px solid #e1e6ef;
    border-radius: 12px;

    outline: none;

    background: #ffffff;
    color: #18243a;

    font-family: inherit;
    font-size: 13px;
    font-weight: 500;

    box-shadow:
        0 2px 7px rgba(30, 50, 80, 0.04);

    transition:
        border-color .2s ease,
        box-shadow .2s ease,
        background-color .2s ease,
        transform .2s ease;
}

.evrak-search-input:hover {
    border-color: #cfd7e4;
}

.evrak-search-input:focus {
    border-color: #9eb9ef;

    background: #ffffff;

    box-shadow:
        0 0 0 3px rgba(53, 109, 243, .09),
        0 4px 12px rgba(30, 50, 80, 0.06);
}

.evrak-search-input::placeholder {
    color: #9aa6b7;
    font-weight: 500;
}

.evrak-search-submit {
    position: absolute;

    right: 7px;

    width: 29px;
    height: 29px;

    display: flex;
    align-items: center;
    justify-content: center;

    border: none;
    border-radius: 8px;

    background: #f2f5fa;
    color: #68778d;

    cursor: pointer;

    transition:
        background-color .2s ease,
        color .2s ease,
        transform .2s ease;
}

.evrak-search-submit:hover {
    background: #eaf1ff;
    color: #3372f4;

    transform: scale(1.04);
}

.evrak-search-submit svg {
    width: 15px;
    height: 15px;
}

.evrak-search-clear {
    position: absolute;

    right: 43px;

    width: 25px;
    height: 25px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 7px;

    color: #97a3b4;
    text-decoration: none;

    transition:
        background-color .2s ease,
        color .2s ease;
}

.evrak-search-clear:hover {
    background: #f2f4f8;
    color: #d04e4e;
}

.evrak-search-clear svg {
    width: 14px;
    height: 14px;
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
.evrak-filter-bar {
    display: flex;
    align-items: center;
    justify-content: flex-start;

    margin-top: 16px;
    padding-top: 14px;

    border-top: 1px solid var(--border);
}
.evrak-status.archived {
    background: #f1f3f5;
    color: #687385;
    border-color: #dce1e7;
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
           TABLO ALANI & EN ALTAKİ KAYDIRMA ÇUBUĞU (SCROLLBAR)
        ========================================================= */

        .evrak-table-wrapper {
            width: 100%;
            overflow-x: auto;
            overflow-y: hidden;
            margin-top: 18px;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding-bottom: 4px;
            scrollbar-width: thin;
            scrollbar-color: #cfd9e8 #f1f4f9;
        }

        .evrak-table-wrapper::-webkit-scrollbar {
            height: 8px;
        }

        .evrak-table-wrapper::-webkit-scrollbar-track {
            background: #f1f4f9;
            border-radius: 10px;
        }

        .evrak-table-wrapper::-webkit-scrollbar-thumb {
            background: #c5d2e3;
            border-radius: 10px;
        }

        .evrak-table-wrapper::-webkit-scrollbar-thumb:hover {
            background: #9aaec7;
        }

        .evrak-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1050px;
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
            white-space: nowrap;
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
            width: 45px;
            text-align: center;
        }

        .evrak-table th:nth-child(2),
        .evrak-table td:nth-child(2) {
            width: 170px;
        }

        .evrak-table th:nth-child(3),
        .evrak-table td:nth-child(3) {
            width: 200px;
        }

        .evrak-table th:nth-child(4),
        .evrak-table td:nth-child(4) {
            width: 130px;
        }

        .evrak-table th:nth-child(5),
        .evrak-table td:nth-child(5) {
            width: 110px;
        }

        .evrak-table th:nth-child(6),
        .evrak-table td:nth-child(6) {
            width: 120px;
        }

        .evrak-table th:nth-child(7),
        .evrak-table td:nth-child(7) {
            min-width: 320px;
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
           EVRAK ADI & HER ZAMAN MAVİ / ÜZERİNE GELİNCE DAHA BELİRGİN
        ========================================================= */

        .evrak-name {
            display: flex;
            flex-direction: column;
            gap: 4px;
            min-width: 0;
        }

        .evrak-title-link {
            text-decoration: underline !important;
            text-underline-offset: 4px;
            text-decoration-thickness: 1.5px;
            color: #2563eb !important;
            display: inline-block;
            max-width: 100%;
            transition: all .2s ease;
        }

        .evrak-title-link strong {
            display: block;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
            font-size: 13px;
            font-weight: 700;
            color: #2563eb !important;
            cursor: pointer;
            transition: color .2s ease, transform .2s ease;
        }

        .evrak-title-link:hover {
            text-decoration-thickness: 2.5px;
            text-decoration-color: #1e40af !important;
        }

        .evrak-title-link:hover strong {
            color: #1e40af !important;
            transform: translateX(1px);
        }

        body.dark-mode .evrak-title-link,
        body.dark-mode .evrak-title-link strong {
            color: #60a5fa !important;
        }

        body.dark-mode .evrak-title-link:hover {
            text-decoration-color: #93c5fd !important;
        }

        body.dark-mode .evrak-title-link:hover strong {
            color: #93c5fd !important;
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
            white-space: nowrap;
        }


        /* =========================================================
           TARİH
        ========================================================= */

        .evrak-date {
            color: var(--text-soft);

            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
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
            white-space: nowrap;
        }


        .evrak-no-file svg {
            width: 13px;
            height: 13px;
        }
/* =========================================================
   SEÇİLEN DOSYALAR
========================================================= */

.selected-files {
    display: none;

    margin-top: 14px;

    flex-direction: column;

    gap: 8px;
}

.selected-files.show {
    display: flex;
}

.selected-file-item {
    display: flex;

    align-items: center;
    justify-content: space-between;

    gap: 12px;

    padding: 10px 12px;

    border: 1px solid #e1e6ef;

    border-radius: 10px;

    background: #f8fafc;
}

.selected-file-info {
    display: flex;

    align-items: center;

    gap: 9px;

    min-width: 0;
}

.selected-file-icon {
    width: 32px;
    height: 32px;

    flex-shrink: 0;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 8px;

    background: #eef4ff;

    color: #3975e8;
}

.selected-file-icon svg {
    width: 15px;
    height: 15px;
}

.selected-file-name {
    min-width: 0;

    overflow: hidden;

    text-overflow: ellipsis;

    white-space: nowrap;

    font-size: 12px;

    font-weight: 600;

    color: var(--text);
}

.selected-file-actions {
    display: flex;

    align-items: center;

    gap: 6px;

    flex-shrink: 0;
}

.selected-file-button {
    display: inline-flex;

    align-items: center;
    justify-content: center;

    gap: 5px;

    min-height: 30px;

    padding: 0 9px;

    border: 1px solid transparent;

    border-radius: 8px;

    font-size: 11px;

    font-weight: 700;

    cursor: pointer;

    text-decoration: none;

    transition:
        background-color .18s ease,
        border-color .18s ease,
        color .18s ease,
        transform .18s ease;
}

.selected-file-button svg {
    width: 13px;
    height: 13px;
}

.selected-file-view {
    background: #eef4ff;

    border-color: #d8e5ff;

    color: #3975e8;
}

.selected-file-view:hover {
    background: #e2ecff;

    border-color: #c4d7ff;

    color: #2865d9;

    transform: translateY(-1px);
}

.selected-file-remove {
    background: #fff0f1;

    border-color: #f3d5d7;

    color: #d05252;
}

.selected-file-remove:hover {
    background: #ffe4e6;

    border-color: #efc1c4;

    color: #bd3f3f;

    transform: translateY(-1px);
}

body.dark-mode .selected-file-item {
    background: #111b2d;

    border-color: #2a394f;
}

body.dark-mode .selected-file-icon {
    background: #172b4d;

    color: #82aaff;
}

body.dark-mode .selected-file-name {
    color: #edf2f8;
}

body.dark-mode .selected-file-view {
    background: #172b4d;

    border-color: #29446e;

    color: #82aaff;
}

body.dark-mode .selected-file-view:hover {
    background: #1c355c;

    border-color: #365883;

    color: #9abbff;
}

body.dark-mode .selected-file-remove {
    background: #392125;

    border-color: #5b3036;

    color: #f2a3a3;
}

body.dark-mode .selected-file-remove:hover {
    background: #47262b;

    border-color: #704047;

    color: #ffb4b4;
}

        /* =========================================================
           EVRAK İŞLEM BUTONLARI
        ========================================================= */

        .evrak-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: nowrap;
        }

        .evrak-action {
            position: relative;

            width: 34px;
            height: 34px;

            flex-shrink: 0;

            display: inline-flex;
            align-items: center;
            justify-content: center;

            padding: 0;

            border: 1px solid transparent;
            border-radius: 9px;

            text-decoration: none;

            cursor: pointer;

            transition:
                transform .18s ease,
                box-shadow .18s ease,
                background-color .18s ease,
                border-color .18s ease,
                color .18s ease;
        }

        .evrak-action svg {
            width: 15px;
            height: 15px;

            stroke-width: 2.2;

            transition:
                transform .18s ease;
        }

        .evrak-action:hover {
            transform: translateY(-2px);
        }

        .evrak-action:hover svg {
            transform: scale(1.08);
        }

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

        .evrak-no-file {
            display: inline-flex;

            align-items: center;
            justify-content: center;

            gap: 6px;

            min-height: 34px;

            padding: 0 10px;

            border-radius: 9px;

            background: #f5f7fa;

            color: #7b8798;

            border: 1px solid #e3e8ef;

            font-size: 10px;
            font-weight: 650;
            flex-shrink: 0;
        }

        .evrak-no-file svg {
            width: 14px;
            height: 14px;

            stroke-width: 2;
        }

        .evrak-actions form {
            display: flex;
            align-items: center;

            margin: 0;
            padding: 0;
        }

        .evrak-action:focus-visible {
            outline: none;

            box-shadow:
                0 0 0 3px rgba(53, 109, 243, .16);
        }

        .evrak-inline-form {
            display: inline-flex !important;
            align-items: center;
            margin: 0;
            padding: 0;
        }

        /* =========================================================
           ALT ALTA DOSYA LİSTESİ (İSİMLİ)
        ========================================================= */

        .evrak-files-vertical {
            display: flex;
            flex-direction: column;
            gap: 6px;
            align-items: flex-start;
            flex-shrink: 0;
        }

        .evrak-file-row {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #f4f7fb;
            padding: 3px 8px;
            border-radius: 8px;
            border: 1px solid #e3e8ef;
            flex-shrink: 0;
        }

        .evrak-file-name-label {
            display: inline-block;
            max-width: 130px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 11px;
            font-weight: 600;
            color: #4a5568;
            text-decoration: none;
            line-height: 1.2;
        }

        .evrak-file-name-label:hover {
            color: #3975e8;
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

        body.dark-mode .evrak-table-wrapper {
            scrollbar-color: #33445e #111a28;
        }

        body.dark-mode .evrak-table-wrapper::-webkit-scrollbar-track {
            background: #111a28;
        }

        body.dark-mode .evrak-table-wrapper::-webkit-scrollbar-thumb {
            background: #2b3b52;
        }

        body.dark-mode .evrak-table-wrapper::-webkit-scrollbar-thumb:hover {
            background: #3f5575;
        }

        body.dark-mode .evrak-file-row {
            background: #152033;
            border-color: #24354d;
        }

        body.dark-mode .evrak-file-name-label {
            color: #cbd5e1;
        }

        body.dark-mode .evrak-file-name-label:hover {
            color: #82aaff;
        }

        /* =========================================================
           EVRAKLAR TAM DARK MODE
        ========================================================= */

        body.dark-mode .evrak-list-card {
            background: var(--card);
        }

        body.dark-mode .evrak-list-header {
            border-color: var(--border);
        }

        body.dark-mode .evrak-table-wrapper {
            background: #0f1826;
            border-color: #2a394f;
        }

        body.dark-mode .evrak-table {
            background: #0f1826;
        }

        body.dark-mode .evrak-table thead {
            background: #151f2f;
        }

        body.dark-mode .evrak-table th {
            background: #151f2f;
            color: #8f9eb2;
            border-color: #2a394f;
        }

        body.dark-mode .evrak-table td {
            color: #e5ebf3;
            border-color: #243247;
        }

        body.dark-mode .evrak-table tbody tr:hover {
            background: #162235;
        }

        body.dark-mode .evrak-person-icon {
            background: #172b4d;
            color: #82aaff;
        }

        body.dark-mode .evrak-person-name {
            color: #edf2f8;
        }

        body.dark-mode .evrak-description {
            color: #8998ad;
        }

        body.dark-mode .evrak-number {
            background: #182436;
            color: #b8c5d6;
            border: 1px solid #2b3a50;
        }

        body.dark-mode .evrak-date {
            color: #a1aec0;
        }

        /* FİLTRELER */

        body.dark-mode .evrak-filter {
            background: #141f30;
            border-color: #2a394f;
            color: #9eacbe;
        }

        body.dark-mode .evrak-filter:hover {
            background: #1a293d;
            border-color: #3a4c64;
            color: #edf2f8;
        }

        body.dark-mode .evrak-filter.active {
            background: #172b4d;
            border-color: #34517c;
            color: #82aaff;
        }

        /* ARŞİVLENMİŞ DURUM */

        body.dark-mode .evrak-status.archived {
            background: #222c3a;
            color: #a6b1c0;
            border-color: #374457;
        }

        /* DURUMLAR */

        body.dark-mode .evrak-status.pending {
            background: #3a2e18;
            color: #f2c66d;
            border-color: #5b4724;
        }

        body.dark-mode .evrak-status.approved {
            background: #173527;
            color: #7bd8a5;
            border-color: #28543d;
        }

        body.dark-mode .evrak-status.rejected {
            background: #392125;
            color: #f2a3a3;
            border-color: #5b3036;
        }

        body.dark-mode .red-reason {
            color: #f2a3a3;
        }

        /* DOSYA SATIRI */

        body.dark-mode .evrak-file-row {
            background: #152033;
            border-color: #2a3b53;
        }

        body.dark-mode .evrak-file-name-label {
            color: #cbd5e1;
        }

        body.dark-mode .evrak-file-name-label:hover {
            color: #82aaff;
        }

        /* DOSYA YOK */

        body.dark-mode .evrak-no-file {
            background: #1a2434;
            color: #8997aa;
            border-color: #2d3a4d;
        }

        /* BOŞ LİSTE */

        body.dark-mode .evrak-empty {
            color: #8997aa;
        }

        body.dark-mode .evrak-empty-icon {
            background: #172233;
            color: #718198;
        }

        /* MESAJLAR */

        body.dark-mode .evrak-message {
            background: #173527;
            border-color: #28543d;
            color: #7bd8a5;
        }

        body.dark-mode .evrak-error {
            background: #392125;
            border-color: #5b3036;
            color: #f2a3a3;
        }

        /* KULLANICI MENÜSÜ */

        body.dark-mode .user-dropdown {
            background: #111b2d;
            border-color: #2a394f;
            box-shadow:
                0 10px 28px rgba(0, 0, 0, .35);
        }

        body.dark-mode .user-dropdown-item {
            color: #f2a3a3;
        }

        body.dark-mode .user-dropdown-item:hover {
            background: #392125;
            color: #ffb4b4;
        }

        /* TABLO KAYDIRMA ÇUBUĞU */

        body.dark-mode .evrak-table-wrapper {
            scrollbar-color: #33445e #111a28;
        }

        body.dark-mode .evrak-table-wrapper::-webkit-scrollbar-track {
            background: #111a28;
        }

        body.dark-mode .evrak-table-wrapper::-webkit-scrollbar-thumb {
            background: #33445e;
        }

        body.dark-mode .evrak-table-wrapper::-webkit-scrollbar-thumb:hover {
            background: #466081;
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

    box-shadow:
        0 2px 7px rgba(0, 0, 0, .12);
}

body.dark-mode .evrak-search-input:hover {
    border-color: #3b4c65;
}

body.dark-mode .evrak-search-input:focus {
    border-color: #527ac4;

    box-shadow:
        0 0 0 3px rgba(82, 122, 196, .12),
        0 4px 12px rgba(0, 0, 0, .16);
}

body.dark-mode .evrak-search-input::placeholder {
    color: #6f7e95;
}

body.dark-mode .evrak-search-submit {
    background: #1a2638;
    color: #91a0b7;
}

body.dark-mode .evrak-search-submit:hover {
    background: #223450;
    color: #7fa8ff;
}

body.dark-mode .evrak-search-clear {
    color: #7d8da4;
}

body.dark-mode .evrak-search-clear:hover {
    background: #202c3f;
    color: #ff9d9d;
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
<!-- PROFİL -->

<a
    href="../profil.php"
    class="sidebar-link"
>

    <i
        data-lucide="user-round"
        class="menu-icon"
    ></i>

    <span>
        Profilim
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

                <?php require_once __DIR__ . "/../includes/duyuru-sistemi.php"; ?>


                <!-- ARAMA -->

                <div class="evrak-topbar-search">

    <form
        method="GET"
        action="evraklar.php"
        class="evrak-search-form"
    >

        <input
            type="hidden"
            name="gorunum"
            value="<?= temizle($gorunum) ?>"
        >


        <input
            type="text"
            name="arama"
            id="evrakArama"
            class="evrak-search-input"
            placeholder="Evrak, personel veya evrak no ara..."
            value="<?= temizle($arama) ?>"
            autocomplete="off"
        >

        <?php if ($arama !== ""): ?>

            <a
                href="evraklar.php?gorunum=<?= temizle($gorunum) ?>"
                class="evrak-search-clear"
                title="Aramayı temizle"
                aria-label="Aramayı temizle"
            >
                <i data-lucide="x"></i>
            </a>

        <?php endif; ?>

        <button
            type="submit"
            class="evrak-search-submit"
            title="Ara"
            aria-label="Ara"
        >
            <i data-lucide="search"></i>
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

                            <?php
                            $ilk_harf = mb_substr(
                                $giris_yapan_kullanici["ad"]
                                ?: $giris_yapan_kullanici["kullanici_adi"],
                                0,
                                1,
                                "UTF-8"
                            );

                            echo htmlspecialchars(
                                mb_strtoupper($ilk_harf, "UTF-8")
                            );
                            ?>

                        </div>


                        <div class="user-text">

                            <strong>
                                <?= htmlspecialchars(
                                    $kullanici_ad_soyad,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </strong>


                            <span>
                                <?= htmlspecialchars(
                                    $rol_etiketi,
                                    ENT_QUOTES,
                                    "UTF-8"
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
        <i data-lucide="upload-cloud"></i>
    </div>

    <div class="evrak-file-text">

        <strong>
            Dosya yüklemek için tıklayın
        </strong>

        <span>
            Birden fazla dosya seçebilirsiniz
        </span>

    </div>

    <div class="evrak-file-button">
        Dosya Seç
    </div>
</label>


<input
    type="file"
    name="evrak_dosyasi[]"
    id="evrak_dosyasi"
    multiple
    accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
    style="display:none;"
    
>
<div
    id="seciliDosyalar"
    class="selected-files"
></div>

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
                                    ] ?? ""
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
================================================= -->

<div
    class="large-card evrak-list-card"
    id="evrakListCard"
>

    <!-- =================================================
         LİSTE BAŞLIĞI
    ================================================== -->

    <div class="evrak-list-header">

        <div class="evrak-list-title">

            <h2>
                Evrak Listesi
            </h2>

            <p>
                Sistemde kayıtlı evrakları görüntüleyin ve yönetin.
            </p>

            <?php if ($arama !== ""): ?>

                <div class="evrak-search-result-info">

                    <i data-lucide="search"></i>

                    <span>

                        "<strong><?= temizle($arama) ?></strong>"

                        için

                        <strong><?= count($evraklar) ?></strong>

                        sonuç bulundu

                    </span>

                </div>

            <?php endif; ?>

        </div>


        <!-- EVRAK EKLE -->

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

    </div>


    <!-- =================================================
         FİLTRELER
    ================================================== -->

    <div class="evrak-filter-bar">

        <div class="evrak-filters">


            <!-- TÜMÜ -->

            <a
                href="evraklar.php"
                class="evrak-filter
                    <?= $gorunum === "tum"
                        ? "active"
                        : ""
                    ?>"
            >

                <i data-lucide="files"></i>

                Tümü

            </a>


            <!-- BEKLEMEDE -->

            <a
                href="evraklar.php?gorunum=beklemede"
                class="evrak-filter
                    <?= $gorunum === "beklemede"
                        ? "active"
                        : ""
                    ?>"
            >

                <i data-lucide="clock-3"></i>

                Beklemede

            </a>


            <!-- ONAYLANDI -->

            <a
                href="evraklar.php?gorunum=onaylandi"
                class="evrak-filter
                    <?= $gorunum === "onaylandi"
                        ? "active"
                        : ""
                    ?>"
            >

                <i data-lucide="circle-check"></i>

                Onaylandı

            </a>


            <!-- REDDEDİLDİ -->

            <a
                href="evraklar.php?gorunum=reddedildi"
                class="evrak-filter
                    <?= $gorunum === "reddedildi"
                        ? "active"
                        : ""
                    ?>"
            >

                <i data-lucide="circle-x"></i>

                Reddedildi

            </a>


            <!-- ARŞİVLENDİ -->

            <a
                href="evraklar.php?gorunum=arsivlendi"
                class="evrak-filter
                    <?= $gorunum === "arsivlendi"
                        ? "active"
                        : ""
                    ?>"
            >

                <i data-lucide="archive"></i>

                Arşivlendi

            </a>

        </div>

    </div>


    <!-- =================================================
         TABLO BAŞLANGICI
    ================================================== -->


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


                                        <!-- EVRAK (SÜREKLİ MAVİ VE HOVER'DA DAHA BELİRGİN ALTI ÇİZİLİ LİNK) -->

                                        <td>

                                            <div
                                                class="evrak-name"
                                            >

                                                <a 
                                                    href="evrak-detay.php?id=<?= (int)$evrak['id'] ?>" 
                                                    class="evrak-title-link"
                                                    title="Evrak Detayını ve Çıktı Formunu Görüntüle"
                                                >
                                                    <strong>
                                                        <?= temizle($evrak["evrak_adi"]) ?>
                                                    </strong>
                                                </a>


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
    $durum === "arsivlendi" 
): ?>

    <span 
        class="
            evrak-status
            archived
        "
    >

        <i 
            data-lucide="archive"
        ></i>

        Arşivlendi

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


                                       <!-- =====================================================
     İŞLEMLER
===================================================== -->

<td>

    <div class="evrak-actions">

        <!-- =================================================
             DOSYALAR (ALT ALTA SIRALI VE İSİMLİ)
        ================================================== -->

        <?php

        $dosyaStmt = $db->prepare("
            SELECT
                id,
                dosya_adi,
                dosya_yolu
            FROM evrak_dosyalar
            WHERE evrak_id = ?
            ORDER BY id ASC
        ");

        $dosyaStmt->execute([
            (int) $evrak["id"]
        ]);

        $evrakDosyalari =
            $dosyaStmt->fetchAll(
                PDO::FETCH_ASSOC
            );

        ?>


        <?php if (!empty($evrakDosyalari)): ?>

            <div class="evrak-files-vertical">

                <?php foreach ($evrakDosyalari as $dosya): ?>

                    <div class="evrak-file-row">

                        <!-- GÖRÜNTÜLE (GÖZ İKONU) -->
                        <a
                            href="evrak-goruntule.php?dosya_id=<?= (int) $dosya["id"] ?>"
                            target="_blank"
                            class="evrak-action view"
                            title="<?= temizle($dosya["dosya_adi"]) ?>"
                            aria-label="Dosyayı görüntüle"
                            style="width: 26px; height: 26px;"
                        >
                            <i data-lucide="eye" style="width: 13px; height: 13px;"></i>
                        </a>

                        <!-- KÜÇÜK DOSYA ADI -->
                        <a
                            href="evrak-goruntule.php?dosya_id=<?= (int) $dosya["id"] ?>"
                            target="_blank"
                            class="evrak-file-name-label"
                            title="<?= temizle($dosya["dosya_adi"]) ?>"
                        >
                            <?= temizle($dosya["dosya_adi"]) ?>
                        </a>

                        <!-- DOSYA SİL BUTONU -->
                        <?php if ($yoneticiMi): ?>
                            <form
                                method="POST"
                                class="evrak-inline-form"
                                onsubmit="return confirm('Bu dosyayı silmek istediğinize emin misiniz?');"
                            >
                                <input type="hidden" name="islem" value="dosya_sil">
                                <input type="hidden" name="dosya_id" value="<?= (int) $dosya["id"] ?>">

                                <button
                                    type="submit"
                                    class="evrak-action danger"
                                    title="Dosyayı Sil"
                                    aria-label="Dosyayı Sil"
                                    style="width: 22px; height: 22px;"
                                >
                                    <i data-lucide="trash-2" style="width: 11px; height: 11px;"></i>
                                </button>
                            </form>
                        <?php endif; ?>

                    </div>

                <?php endforeach; ?>

            </div>


        <?php elseif (!empty($evrak["dosya_yolu"])): ?>

            <!-- ESKİ TEKLİ DOSYA DESTEĞİ -->
            <div class="evrak-files-vertical">
                <div class="evrak-file-row">
                    <a
                        href="evrak-goruntule.php?id=<?= (int) $evrak["id"] ?>"
                        target="_blank"
                        class="evrak-action view"
                        title="Dosyayı Görüntüle"
                        aria-label="Dosyayı Görüntüle"
                        style="width: 26px; height: 26px;"
                    >
                        <i data-lucide="eye" style="width: 13px; height: 13px;"></i>
                    </a>

                    <a
                        href="evrak-goruntule.php?id=<?= (int) $evrak["id"] ?>"
                        target="_blank"
                        class="evrak-file-name-label"
                        title="<?= temizle(basename($evrak["dosya_yolu"])) ?>"
                    >
                        <?= temizle(basename($evrak["dosya_yolu"])) ?>
                    </a>
                </div>
            </div>

        <?php else: ?>

            <span
                class="evrak-no-file"
                title="Bu evrak için dosya yüklenmemiş"
            >
                <i data-lucide="file-x"></i>
                Dosya yok
            </span>

        <?php endif; ?>


        <!-- =================================================
             DÜZENLE
        ================================================== -->

        <a
            href="evraklar.php?duzenle=<?= (int) $evrak["id"] ?>"
            class="evrak-action edit"
            title="Düzenle"
            aria-label="Düzenle"
        >
            <i data-lucide="pencil"></i>
        </a>


        <!-- ================================================= 
             YÖNETİCİ İŞLEMLERİ 
        ================================================== -->

        <?php if ($yoneticiMi): ?>


            <!-- =================================================
                 ARŞİVLE
            ================================================== -->

            <?php if (
                $durum === "onaylandi" ||
                $durum === "reddedildi"
            ): ?>

                <form
                    method="POST"
                    class="evrak-inline-form"
                    onsubmit="
                        return confirm(
                            'Bu evrakı arşivlemek istediğinize emin misiniz?'
                        );
                    "
                >

                    <input
                        type="hidden"
                        name="islem"
                        value="arsivle"
                    >

                    <input
                        type="hidden"
                        name="evrak_id"
                        value="<?= (int) $evrak["id"] ?>"
                    >

                    <button
                        type="submit"
                        class="evrak-action back"
                        title="Arşivle"
                        aria-label="Arşivle"
                    >

                        <i data-lucide="archive"></i>

                    </button>

                </form>

            <?php endif; ?>


            <!-- =================================================
                 ARŞİVDEN ÇIKAR
            ================================================== -->

            <?php if (
                $durum === "arsivlendi"
            ): ?>

                <form
                    method="POST"
                    class="evrak-inline-form"
                    onsubmit="
                        return confirm(
                            'Bu evrakı arşivden çıkarmak istediğinize emin misiniz?'
                        );
                    "
                >

                    <input
                        type="hidden"
                        name="islem"
                        value="arsivden_cikar"
                    >

                    <input
                        type="hidden"
                        name="evrak_id"
                        value="<?= (int) $evrak["id"] ?>"
                    >

                    <button
                        type="submit"
                        class="evrak-action back"
                        title="Arşivden Çıkar"
                        aria-label="Arşivden Çıkar"
                    >

                        <i data-lucide="archive-restore"></i>

                    </button>

                </form>

            <?php endif; ?>

            <!-- =================================================
                 EVRAKI TAMAMEN SİL
            ================================================== -->

            <form
                method="POST"
                class="evrak-inline-form"
                onsubmit="
                    return confirm(
                        'Bu evrakı ve bağlı tüm dosyaları tamamen silmek istediğinize emin misiniz?'
                    );
                "
            >

                <input
                    type="hidden"
                    name="islem"
                    value="evrak_sil"
                >

                <input
                    type="hidden"
                    name="evrak_id"
                    value="<?= (int) $evrak["id"] ?>"
                >

                <button
                    type="submit"
                    class="evrak-action danger"
                    title="Evrakı Tamamen Sil"
                    aria-label="Evrakı Tamamen Sil"
                >

                    <i
                        data-lucide="trash-2"
                    ></i>

                </button>

            </form>


            <?php if (
                $durum ===
                "beklemede"
            ): ?>


                <!-- ONAYLA -->

                <form
                    method="POST"
                    class="evrak-inline-form"
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
                    class="evrak-inline-form"
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
                    class="evrak-inline-form"
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

        formCard.style.display =
            "block";


        const listCard =
            document.getElementById("evrakListCard");

        if (listCard) {

            listCard.style.display =
                "none";
        }


        formCard.scrollIntoView({
            behavior: "smooth",
            block: "start"
        });
    }


    /* =====================================================
       DOSYA ADI
    ===================================================== */
    /* =====================================================
   ÇOKLU DOSYA SEÇİMİ
===================================================== */

const fileInput =
    document.getElementById(
        "evrak_dosyasi"
    );

const selectedFilesContainer =
    document.getElementById(
        "seciliDosyalar"
    );


let secilenDosyalar = [];


/* =====================================================
   DOSYA LİSTESİNİ GÖSTER
===================================================== */

function dosyalariGoster()
{
    if (!selectedFilesContainer) {
        return;
    }

    selectedFilesContainer.innerHTML = "";

    if (secilenDosyalar.length === 0) {

        selectedFilesContainer.classList.remove(
            "show"
        );

        return;
    }


    selectedFilesContainer.classList.add(
        "show"
    );


    secilenDosyalar.forEach(
        function(dosya, index)
        {

            const item =
                document.createElement(
                    "div"
                );

            item.className =
                "selected-file-item";


            /* DOSYA BİLGİSİ */

            const info =
                document.createElement(
                    "div"
                );

            info.className =
                "selected-file-info";


            const icon =
                document.createElement(
                    "div"
                );

            icon.className =
                "selected-file-icon";

            icon.innerHTML =
                '<i data-lucide="file"></i>';


            const name =
                document.createElement(
                    "span"
                );

            name.className =
                "selected-file-name";

            name.textContent =
                dosya.name;

            name.title =
                dosya.name;


            info.appendChild(
                icon
            );

            info.appendChild(
                name
            );


            /* BUTONLAR */

            const actions =
                document.createElement(
                    "div"
                );

            actions.className =
                "selected-file-actions";


            /* GÖRÜNTÜLE */

            const viewButton =
                document.createElement(
                    "a"
                );

            viewButton.className =
                "selected-file-button selected-file-view";

            viewButton.href =
                URL.createObjectURL(
                    dosya
                );

            viewButton.target =
                "_blank";

            viewButton.rel =
                "noopener noreferrer";

            viewButton.innerHTML =
                '<i data-lucide="eye"></i> Görüntüle';

            viewButton.title =
                "Dosyayı görüntüle";


            /* KALDIR */

            const removeButton =
                document.createElement(
                    "button"
                );

            removeButton.type =
                "button";

            removeButton.className =
                "selected-file-button selected-file-remove";

            removeButton.innerHTML =
                '<i data-lucide="x"></i> Kaldır';

            removeButton.title =
                "Dosyayı kaldır";


            removeButton.addEventListener(
                "click",
                function()
                {
                    secilenDosyalar.splice(
                        index,
                        1
                    );

                    dosyalariGoster();

                    dosyalariInputaAktar();
                }
            );


            actions.appendChild(
                viewButton
            );

            actions.appendChild(
                removeButton
            );


            item.appendChild(
                info
            );

            item.appendChild(
                actions
            );


            selectedFilesContainer.appendChild(
                item
            );

        }
    );


    if (
        typeof lucide !== "undefined"
    ) {

        lucide.createIcons();
    }
}


/* =====================================================
   DOSYALARI INPUT'A GERİ YAZ
===================================================== */

function dosyalariInputaAktar()
{
    if (!fileInput) {
        return;
    }


    const dataTransfer =
        new DataTransfer();


    secilenDosyalar.forEach(
        function(dosya)
        {

            dataTransfer.items.add(
                dosya
            );

        }
    );


    fileInput.files =
        dataTransfer.files;
}


/* =====================================================
   DOSYA SEÇİMİ
===================================================== */

if (fileInput) {

    fileInput.addEventListener(
        "change",
        function()
        {

            const yeniDosyalar =
                Array.from(
                    this.files
                );


            yeniDosyalar.forEach(
                function(dosya)
                {

                    const zatenVar =
                        secilenDosyalar.some(
                            function(mevcutDosya)
                            {

                                return (
                                    mevcutDosya.name ===
                                    dosya.name &&
                                    mevcutDosya.size ===
                                    dosya.size &&
                                    mevcutDosya.lastModified ===
                                    dosya.lastModified
                                );

                            }
                        );


                    if (!zatenVar) {

                        secilenDosyalar.push(
                            dosya
                        );

                    }

                }
            );


            dosyalariInputaAktar();

            dosyalariGoster();


            const fileArea =
                document.querySelector(
                    ".evrak-file-area"
                );


            if (
                fileArea &&
                secilenDosyalar.length > 0
            ) {

                fileArea.classList.add(
                    "file-selected"
                );

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