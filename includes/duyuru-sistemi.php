<?php

/* =====================================================
   ORTAK DUYURU SİSTEMİ
   Tüm yönetici/personel sayfalarında kullanılabilir.
===================================================== */


/* =====================================================
   DUYURU OKUNDU İŞLEMİ
===================================================== */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["action"]) &&
    $_POST["action"] === "duyuruyu_oku" &&
    isset($_POST["duyuru_id"])
) {

    header("Content-Type: application/json; charset=UTF-8");

    $duyuru_id = (int) $_POST["duyuru_id"];

    $sql = "
        INSERT IGNORE INTO duyuru_okumalari
        (
            duyuru_id,
            kullanici_id
        )
        VALUES
        (
            ?,
            ?
        )
    ";

    $stmt = $db->prepare($sql);

    $stmt->execute([
        $duyuru_id,
        $_SESSION["kullanici_id"]
    ]);

    echo json_encode([
        "status" => "success"
    ]);

    exit;
}


/* =====================================================
   AKTİF DUYURULAR
===================================================== */

$sql = "
    SELECT
        d.id,
        d.baslik,
        d.icerik,
        d.yayin_tarihi,
        d.son_tarihi,
        d.oncelik,

        CASE
            WHEN o.id IS NULL THEN 0
            ELSE 1
        END AS okundu

    FROM duyurular d

    LEFT JOIN duyuru_okumalari o
        ON d.id = o.duyuru_id
        AND o.kullanici_id = ?

    WHERE d.aktif = 1

      AND (
          d.son_tarihi IS NULL
          OR d.son_tarihi >= NOW()
      )

    ORDER BY d.yayin_tarihi DESC
";

$stmt = $db->prepare($sql);

$stmt->execute([
    $_SESSION["kullanici_id"]
]);

$ortak_duyurular = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* =====================================================
   OKUNMAMIŞ / OKUNMUŞ
===================================================== */

$ortak_okunmamis_duyurular = array_filter(
    $ortak_duyurular,
    function ($duyuru) {

        return (int) $duyuru["okundu"] === 0;

    }
);

$sql = "
    SELECT
        d.id,
        d.baslik,
        d.icerik,
        d.yayin_tarihi,
        d.son_tarihi,
        d.oncelik
    FROM duyurular d
    WHERE d.aktif = 0
       OR (
           d.son_tarihi IS NOT NULL
           AND d.son_tarihi < NOW()
       )
    ORDER BY d.yayin_tarihi DESC
";

$stmt = $db->query($sql);
$ortak_gecmis_duyurular = $stmt->fetchAll(PDO::FETCH_ASSOC);


$ortak_okunmamis_sayi =
    count($ortak_okunmamis_duyurular);

?>


<style>

/* =====================================================
   ORTAK DUYURU SİSTEMİ
===================================================== */

.announcement-wrapper {

    position: relative;

    display: flex;

    align-items: center;
}


/* =====================================================
   BUTON
===================================================== */

.announcement-button {

    position: relative;

    width: 40px;
    height: 40px;

    padding: 0;

    border: 1px solid var(--border);

    border-radius: 10px;

    background: var(--white);

    color: var(--text);

    display: flex;

    align-items: center;
    justify-content: center;

    cursor: pointer;

    transition:
        background-color 0.2s ease,
        border-color 0.2s ease;
}


.announcement-button:hover {

    background: #f6f8fb;
}


.announcement-button svg {

    width: 19px;
    height: 19px;
}


/* =====================================================
   ROZET
===================================================== */

.announcement-badge {

    position: absolute;

    top: -4px;
    right: -4px;

    min-width: 18px;
    height: 18px;

    padding: 0 5px;

    border-radius: 20px;

    background: #dc3545;

    color: #ffffff;

    font-size: 10px;

    font-weight: 700;

    display: flex;

    align-items: center;
    justify-content: center;

    border: 2px solid var(--white);
}


/* =====================================================
   DROPDOWN
===================================================== */

.announcement-dropdown {

    position: absolute;

    top: calc(100% + 10px);

    right: 0;

    width: 370px;

    max-width: calc(100vw - 30px);

    padding: 0;

    background: var(--white);

    border: 1px solid var(--border);

    border-radius: 13px;

    box-shadow:
        0 12px 30px
        rgba(25, 45, 80, 0.14);

    opacity: 0;

    visibility: hidden;

    transform: translateY(-5px);

    transition:
        opacity 0.18s ease,
        transform 0.18s ease,
        visibility 0.18s ease;

    z-index: 9999;

    overflow: hidden;
}


.announcement-dropdown.show {

    opacity: 1;

    visibility: visible;

    transform: translateY(0);
}


/* =====================================================
   BAŞLIK
===================================================== */

.announcement-header {

    padding: 14px 16px;

    border-bottom: 1px solid var(--border);

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 12px;
}


.announcement-header-left {

    display: flex;

    align-items: center;

    gap: 9px;
}


.announcement-header strong {

    font-size: 14px;

    color: var(--text);
}


.announcement-header span {

    font-size: 11px;

    color: var(--muted);
}


/* =====================================================
   DUYURU EKLE
===================================================== */

.announcement-add-button {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 5px;

    padding: 6px 9px;

    border-radius: 7px;

    background: #eef3ff;

    color: #4d6fd8;

    text-decoration: none;

    font-size: 10px;

    font-weight: 700;

    white-space: nowrap;

    transition:
        background-color 0.2s ease,
        color 0.2s ease;
}


.announcement-add-button:hover {

    background: #e2e9ff;

    color: #405fbe;
}


.announcement-add-button svg {

    width: 13px;
    height: 13px;
}


/* =====================================================
   LİSTE
===================================================== */

.announcement-list {

    max-height: 390px;

    overflow-y: auto;
}


/* =====================================================
   DUYURU
===================================================== */

.announcement-item {

    display: flex;

    align-items: flex-start;

    gap: 11px;

    width: 100%;

    box-sizing: border-box;

    padding: 13px 15px;

    border-bottom: 1px solid var(--border);

    background: transparent;

    cursor: pointer;

    transition:
        background-color 0.2s ease,
        opacity 0.2s ease;
}


.announcement-item:hover {

    background: #f8fafc;
}


.announcement-item.unread {

    background: #f5f8ff;
}


.announcement-item.read {

    opacity: 0.82;
}


/* =====================================================
   İKON
===================================================== */

.announcement-icon {

    width: 34px;
    height: 34px;

    flex-shrink: 0;

    border-radius: 9px;

    display: flex;

    align-items: center;
    justify-content: center;

    background: #eef3ff;

    color: #4d6fd8;
}


.announcement-icon svg {

    width: 17px;
    height: 17px;
}


/* =====================================================
   İÇERİK
===================================================== */

.announcement-content {

    flex: 1;

    min-width: 0;
}


.announcement-content strong {

    display: block;

    margin-bottom: 4px;

    font-size: 12px;

    color: var(--text);
}


.announcement-content p {

    margin: 0 0 5px;

    font-size: 11px;

    line-height: 1.45;

    color: var(--text);

    display: -webkit-box;

    -webkit-line-clamp: 3;

    -webkit-box-orient: vertical;

    overflow: hidden;
}


.announcement-content span {

    font-size: 10px;

    color: var(--muted);
}


/* =====================================================
   BOŞ
===================================================== */

.announcement-empty {

    padding: 30px 20px;

    text-align: center;

    color: var(--muted);

    font-size: 12px;
}


.announcement-empty svg {

    display: block;

    margin: 0 auto 8px;
}


/* =====================================================
   TÜMÜNÜ GÖSTER
===================================================== */

.announcement-show-all {

    display: block;

    width: 100%;

    padding: 11px 15px;

    border: none;

    border-top: 1px solid var(--border);

    background: transparent;

    color: #4d6fd8;

    font-size: 11px;

    font-weight: 700;

    text-align: center;

    cursor: pointer;

    transition:
        background-color 0.2s ease,
        color 0.2s ease;
}


.announcement-show-all:hover {

    background: #f8fafc;

    color: #405fbe;
}


/* =====================================================
   OKUNMUŞLAR
===================================================== */

.announcement-read-list {

    display: none;
}


.announcement-read-list.show {

    display: block;
}


/* =====================================================
   DARK MODE
===================================================== */

body.dark-mode .announcement-button {

    background: #121b2d;

    border-color: #26344a;

    color: #ffffff;
}


body.dark-mode .announcement-badge {

    border-color: #121b2d;
}


body.dark-mode .announcement-dropdown {

    background: #121b2d;

    border-color: #26344a;

    box-shadow:
        0 12px 30px
        rgba(0, 0, 0, 0.30);
}


body.dark-mode .announcement-header {

    border-color: #26344a;
}


body.dark-mode .announcement-item {

    border-color: #26344a;
}


body.dark-mode .announcement-item:hover {

    background: #182338;
}


body.dark-mode .announcement-item.unread {

    background: #17233b;
}


body.dark-mode .announcement-content strong,
body.dark-mode .announcement-content p {

    color: #ffffff;
}


body.dark-mode .announcement-add-button {

    background: #1d2a47;

    color: #9eb3ff;
}


body.dark-mode .announcement-add-button:hover {

    background: #26385d;

    color: #b7c7ff;
}


body.dark-mode .announcement-show-all {

    border-color: #26344a;

    color: #9eb3ff;
}


body.dark-mode .announcement-show-all:hover {

    background: #182338;

    color: #b7c7ff;
}


/* =====================================================
   MOBİL
===================================================== */

@media (max-width: 700px) {

    .announcement-dropdown {

        right: -60px;
    }

}

</style>


<!-- =====================================================
     DUYURU BUTONU
===================================================== -->

<div class="announcement-wrapper">

    <button
        type="button"
        class="announcement-button"
        id="announcementButton"
        title="Duyurular">

        <i data-lucide="megaphone"></i>


        <?php if ($ortak_okunmamis_sayi > 0): ?>

            <span
                class="announcement-badge"
                id="announcementBadge">

                <?= $ortak_okunmamis_sayi > 99
                    ? "99+"
                    : $ortak_okunmamis_sayi ?>

            </span>

        <?php endif; ?>

    </button>


    <div
        class="announcement-dropdown"
        id="announcementDropdown">


       <!-- BAŞLIK -->

        <div class="announcement-header">

            <div class="announcement-header-left">

                <strong>
                    Duyurular
                </strong>

                <span
                    id="announcementUnreadCount">

                    <?= $ortak_okunmamis_sayi ?>
                    okunmamış

                </span>

            </div>

<a
    href="<?= strpos($_SERVER["PHP_SELF"], "/pages/") !== false
        ? "duyurular.php?gorunum=liste"
        : "pages/duyurular.php?gorunum=liste" ?>"
    class="announcement-archive-link">

    <i data-lucide="list"></i>
    Tüm Duyurular
</a>

            <?php if (
                in_array(
                    $_SESSION["rol"],
                    ["yonetici", "admin"]
                )
            ): ?>
                
                <!-- "DUYURU EKLE" BUTONU DÜZENLENDİ -->
                <a
                    href="<?= strpos($_SERVER["PHP_SELF"], "/pages/") !== false
                        ? "duyurular.php"
                        : "pages/duyurular.php" ?>"
                    class="announcement-add-button">

                    <i data-lucide="plus"></i>

                    Duyuru Ekle

                </a>

            <?php endif; ?>

        </div>

        <!-- DUYURULAR -->

        <div
            class="announcement-list"
            id="announcementList">


            <?php if (
                count($ortak_okunmamis_duyurular) > 0
            ): ?>


                <?php foreach (
                    $ortak_okunmamis_duyurular
                    as $duyuru
                ): ?>


                    <div
                        class="announcement-item unread"
                        data-id="<?= (int) $duyuru["id"] ?>">

                        <div class="announcement-icon">

                            <i
                                data-lucide="megaphone">
                            </i>

                        </div>


                        <div class="announcement-content">

                            <strong>

                                <?= htmlspecialchars(
                                    $duyuru["baslik"]
                                ) ?>

                            </strong>


                            <p>

                                <?= htmlspecialchars(
                                    $duyuru["icerik"]
                                ) ?>

                            </p>


                            <span>

                                <?= date(
                                    "d.m.Y H:i",
                                    strtotime(
                                        $duyuru["yayin_tarihi"]
                                    )
                                ) ?>

                            </span>

                        </div>

                    </div>


                <?php endforeach; ?>


            <?php else: ?>


                <div class="announcement-empty">

                    <i
                        data-lucide="megaphone-off"
                        width="22"
                        height="22">
                    </i>

                    Okunmamış duyuru bulunmuyor.

                </div>


            <?php endif; ?>


            <?php if (
                count($ortak_gecmis_duyurular) > 0
            ): ?>


                <button
                    type="button"
                    class="announcement-show-all"
                    id="announcementShowPast">

                    Geçmiş Duyurular ↓

                </button>


                <div
                    class="announcement-read-list"
                    id="announcementPastList">


                    <?php foreach (
                        $ortak_gecmis_duyurular
                        as $duyuru
                    ): ?>


                        <div
                            class="announcement-item read"
                            data-id="<?= (int) $duyuru["id"] ?>">

                            <div class="announcement-icon">

                                <i
                                    data-lucide="megaphone">
                                </i>

                            </div>


                            <div class="announcement-content">

                                <strong>

                                    <?= htmlspecialchars(
                                        $duyuru["baslik"]
                                    ) ?>

                                </strong>


                                <p>

                                    <?= htmlspecialchars(
                                        $duyuru["icerik"]
                                    ) ?>

                                </p>


                                <span>

                                    <?= date(
                                        "d.m.Y H:i",
                                        strtotime(
                                            $duyuru["yayin_tarihi"]
                                        )
                                    ) ?>

                                </span>

                            </div>

                        </div>


                    <?php endforeach; ?>


                    <button
                        type="button"
                        class="announcement-show-all"
                        id="announcementHidePast">

                        Güncel Duyurulara Dön ↑

                    </button>


                </div>


            <?php endif; ?>


        </div>

    </div>

</div>


<script>

    if (typeof lucide !== "undefined") {

        lucide.createIcons();

    }


    /* =====================================================
       DUYURU ELEMENTLERİ
    ===================================================== */

    const announcementButton =
        document.getElementById(
            "announcementButton"
        );

    const announcementDropdown =
        document.getElementById(
            "announcementDropdown"
        );

    const announcementBadge =
        document.getElementById(
            "announcementBadge"
        );

    const announcementUnreadCount =
        document.getElementById(
            "announcementUnreadCount"
        );


    /* =====================================================
       AÇ / KAPAT
    ===================================================== */

    if (announcementButton) {

        announcementButton.addEventListener(
            "click",
            function (e) {

                e.stopPropagation();

                announcementDropdown.classList.toggle(
                    "show"
                );

            }
        );

    }


    /* =====================================================
       DUYURU OKUNDU
    ===================================================== */

    document
        .querySelectorAll(
            ".announcement-item.unread"
        )
        .forEach(function (item) {


            item.addEventListener(
                "click",
                function () {


                    const duyuruId =
                        this.dataset.id;


                    if (!duyuruId) {

                        return;

                    }


                    this.classList.remove(
                        "unread"
                    );

                    this.classList.add(
                        "read"
                    );


                    let yeniSayi = 0;


                    document
                        .querySelectorAll(
                            ".announcement-item.unread"
                        )
                        .forEach(function () {

                            yeniSayi++;

                        });


                    if (
                        announcementUnreadCount
                    ) {

                        announcementUnreadCount.textContent =
                            yeniSayi +
                            " okunmamış";

                    }


                    if (announcementBadge) {


                        if (yeniSayi > 0) {


                            announcementBadge.textContent =
                                yeniSayi > 99
                                    ? "99+"
                                    : yeniSayi;


                        } else {


                            announcementBadge.remove();


                        }

                    }


                    const formData =
                        new FormData();


                    formData.append(
                        "action",
                        "duyuruyu_oku"
                    );


                    formData.append(
                        "duyuru_id",
                        duyuruId
                    );


                    fetch(
                        window.location.href,
                        {
                            method: "POST",
                            body: formData
                        }
                    ).catch(
                        function (err) {

                            console.error(
                                "Duyuru okuma hatası:",
                                err
                            );

                        }
                    );


                }
            );

        });


    /* =====================================================
       GEÇMİŞ DUYURULAR
    ===================================================== */

    const announcementShowPast =
        document.getElementById(
            "announcementShowPast"
        );

    const announcementHidePast =
        document.getElementById(
            "announcementHidePast"
        );

    const announcementPastList =
        document.getElementById(
            "announcementPastList"
        );


    if (announcementShowPast) {

        announcementShowPast.addEventListener(
            "click",
            function (e) {

                e.stopPropagation();

                announcementPastList.classList.add(
                    "show"
                );

                announcementShowPast.style.display =
                    "none";

            }
        );

    }


    if (announcementHidePast) {

        announcementHidePast.addEventListener(
            "click",
            function (e) {

                e.stopPropagation();

                announcementPastList.classList.remove(
                    "show"
                );

                announcementShowPast.style.display =
                    "block";

            }
        );

    }


    /* =====================================================
       MENÜ İÇİNE TIKLAYINCA KAPANMASIN
    ===================================================== */

    if (announcementDropdown) {

        announcementDropdown.addEventListener(
            "click",
            function (e) {

                e.stopPropagation();

            }
        );

    }


    /* =====================================================
       DIŞARI TIKLANINCA KAPAT
    ===================================================== */

    document.addEventListener(
        "click",
        function () {

            if (announcementDropdown) {

                announcementDropdown.classList.remove(
                    "show"
                );

            }

        }
    );

</script>
