<?php

$sql = "
    SELECT
        id,
        gorev_id,
        mesaj,
        tur,
        okundu,
        tarih
    FROM bildirimler
    WHERE kullanici_id = ?
    ORDER BY id DESC
    LIMIT 10
";

$stmt = $db->prepare($sql);

$stmt->execute([
    $_SESSION["kullanici_id"]
]);

$bildirimler = $stmt->fetchAll(PDO::FETCH_ASSOC);


$sql = "
    SELECT COUNT(*)
    FROM bildirimler
    WHERE kullanici_id = ?
    AND okundu = 0
";

$stmt = $db->prepare($sql);

$stmt->execute([
    $_SESSION["kullanici_id"]
]);

$okunmamis_bildirim = (int) $stmt->fetchColumn();

?>

<div class="notification-wrapper" id="notificationWrapper">

    <button
        type="button"
        class="notification-button"
        id="notificationButton"
        title="Bildirimler"
    >

        <i data-lucide="bell"></i>

        <?php if ($okunmamis_bildirim > 0): ?>

            <span
                class="notification-badge"
                id="notificationBadge"
            >
                <?= $okunmamis_bildirim > 99
                    ? "99+"
                    : $okunmamis_bildirim ?>
            </span>

        <?php endif; ?>

    </button>


    <div
        class="notification-dropdown"
        id="notificationDropdown"
    >

        <div class="notification-header">

            <strong>
                Bildirimler
            </strong>

            <span>
                <?= $okunmamis_bildirim ?> okunmamış
            </span>

        </div>


        <div class="notification-list">

            <?php if (!empty($bildirimler)): ?>

                <?php foreach ($bildirimler as $bildirim): ?>

<a
    href="/BirimTakipSistemi/bildirim-okundu.php?id=<?= (int) $bildirim["id"] ?>"
    class="notification-item <?= $bildirim["okundu"] == 0 ? "unread" : "" ?>"
>

                        <div class="notification-icon">

                            <?php if ($bildirim["tur"] === "gorev"): ?>

                                <i data-lucide="clipboard-list"></i>

                            <?php elseif ($bildirim["tur"] === "evrak"): ?>

                                <i data-lucide="file-text"></i>

                            <?php else: ?>

                                <i data-lucide="bell"></i>

                            <?php endif; ?>

                        </div>


                        <div class="notification-content">

                            <p>
                                <?= htmlspecialchars(
                                    $bildirim["mesaj"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </p>

                            <span>
                                <?= date(
                                    "d.m.Y H:i",
                                    strtotime($bildirim["tarih"])
                                ) ?>
                            </span>

                        </div>

                    </a>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="notification-empty">

                    <i
                        data-lucide="bell-off"
                        width="22"
                        height="22"
                    ></i>

                    Henüz bildiriminiz bulunmuyor.

                </div>

            <?php endif; ?>

        </div>

    </div>

</div>
