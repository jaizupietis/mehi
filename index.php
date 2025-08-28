<?php
require_once 'config.php';

$pageTitle = 'Sākums';
$pageHeader = 'Sveicināti AVOTI Task Management sistēmā';
$currentUser = getCurrentUser();

/**
 * Apstrādā uzdevuma pabeigšanu, ja tam ir piešķirti vairāki mehāniķi.
 *
 * @param int $taskId Uzdevuma ID.
 * @param int $mechanicId Pašreizējā mehāniķa ID.
 * @return bool Vai atjaunināšana bija veiksmīga.
 */
function completeMultiMechanicTask(int $taskId, int $mechanicId): bool
{
    global $pdo; // Piekļuve globālajam PDO savienojumam

    try {
        $pdo->beginTransaction();

        // Pabeigt darba laika uzskaiti šim mehāniķim
        $stmt = $pdo->prepare("
            UPDATE darba_laiks
            SET beigu_laiks = NOW(),
                stundu_skaits = TIMESTAMPDIFF(MINUTE, sakuma_laiks, NOW()) / 60.0
            WHERE uzdevuma_id = ? AND lietotaja_id = ? AND beigu_laiks IS NULL
        ");
        $stmt->execute([$taskId, $mechanicId]);

        // Atjaunot piešķīruma statusu uz 'Pabeigts' šim mehāniķim
        $stmt = $pdo->prepare("
            UPDATE uzdevumu_piešķīrumi
            SET statuss = 'Pabeigts', pabeigts = NOW()
            WHERE uzdevuma_id = ? AND mehāniķa_id = ?
        ");
        $stmt->execute([$taskId, $mechanicId]);

        // Pārbaudīt, vai visi mehāniķi ir pabeiguši uzdevumu
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM uzdevumu_piešķīrumi
            WHERE uzdevuma_id = ? AND statuss != 'Pabeigts' AND statuss != 'Noņemts'
        ");
        $stmt->execute([$taskId]);
        $activeAssignments = $stmt->fetchColumn();

        if ($activeAssignments == 0) {
            // Visi uzdevumi ir pabeigti, atjaunot galveno uzdevumu
            $stmt = $pdo->prepare("
                UPDATE uzdevumi
                SET statuss = 'Pabeigts',
                    beigu_laiks = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$taskId]);

            // Pievienot vēsturi par uzdevuma galveno pabeigšanu
            $stmt = $pdo->prepare("
                INSERT INTO uzdevumu_vesture
                (uzdevuma_id, iepriekšējais_statuss, jaunais_statuss, komentars, mainīja_id)
                VALUES (?, 'Procesā', 'Pabeigts', 'Visi mehāniķi pabeiguši uzdevumu', ?)
            ");
            $stmt->execute([$taskId, $mechanicId]);
        }

        // Iegūt uzdevuma informāciju paziņojumiem
        $taskStmt = $pdo->prepare("SELECT u.nosaukums, u.veids FROM uzdevumi u WHERE u.id = ?");
        $taskStmt->execute([$taskId]);
        $task = $taskStmt->fetch();
        $uzdevuma_tips = $task['veids'] === 'Regulārais' ? 'Regulārais uzdevums' : 'Uzdevums';

        // Iegūt mehāniķa vārdu
        $mechanicStmt = $pdo->prepare("SELECT CONCAT(vards, ' ', uzvards) as pilns_vards FROM lietotaji WHERE id = ?");
        $mechanicStmt->execute([$mechanicId]);
        $mechanicName = $mechanicStmt->fetchColumn();

        // Paziņot menedžerim/administratoram
        $stmt = $pdo->prepare("
            SELECT l.id, l.loma, CONCAT(l.vards, ' ', l.uzvards) as pilns_vards
            FROM lietotaji l
            WHERE l.loma IN ('Administrators', 'Menedžeris') AND l.statuss = 'Aktīvs'
        ");
        $stmt->execute();
        $managers = $stmt->fetchAll();

        foreach ($managers as $manager) {
            $notification_result = createNotification(
                $manager['id'],
                "$uzdevuma_tips pabeigts",
                "Mehāniķis $mechanicName ir pabeidzis savu daļu uzdevumā: {$task['nosaukums']}",
                'Statusa maiņa',
                'Uzdevums',
                $taskId
            );

            error_log("Paziņojums menedžerim {$manager['pilns_vards']} ({$manager['id']}) par uzdevumu $taskId izveidots: " . ($notification_result ? 'jā' : 'nē'));

            // Telegram paziņojums
            try {
                sendTaskTelegramNotification($manager['id'], $task['nosaukums'], $taskId, 'task_completed');
            } catch (Exception $e) {
                error_log("Telegram notification error in index.php: " . $e->getMessage());
            }
        }


        $pdo->commit();
        return true;

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Kļūda completeMultiMechanicTask (index.php): " . $e->getMessage());
        return false;
    }
}

// Apstrādāt POST darbības (regulāro uzdevumu darbības mehāniķiem)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && hasRole(ROLE_MECHANIC)) {
    $action = $_POST['action'] ?? '';

    if ($action === 'start_work' && isset($_POST['task_id'])) {
        $task_id = intval($_POST['task_id']);

        try {
            $pdo->beginTransaction();

            // Pārbaudīt vai uzdevums pieder lietotājam (gan vienam, gan vairākiem mehāniķiem)
            $stmt = $pdo->prepare("
                SELECT u.*, up.statuss as mans_statuss,
                       (SELECT COUNT(*) FROM darba_laiks WHERE uzdevuma_id = u.id AND lietotaja_id = ? AND beigu_laiks IS NULL) as aktīvs_darbs
                FROM uzdevumi u
                LEFT JOIN uzdevumu_piešķīrumi up ON u.id = up.uzdevuma_id AND up.mehāniķa_id = ?
                WHERE u.id = ? AND (u.piešķirts_id = ? OR EXISTS (
                    SELECT 1 FROM uzdevumu_piešķīrumi up2
                    WHERE up2.uzdevuma_id = u.id AND up2.mehāniķa_id = ? AND up2.statuss != 'Noņemts'
                ))
            ");
            $stmt->execute([$currentUser['id'], $currentUser['id'], $task_id, $currentUser['id'], $currentUser['id']]);
            $task = $stmt->fetch();

            if (!$task) {
                setFlashMessage('error', 'Uzdevums nav atrasts vai jums nav tiesību to sākt.');
            } elseif ($task['aktīvs_darbs'] > 0) {
                setFlashMessage('error', 'Jūs jau strādājat pie šī uzdevuma!');
            } elseif ($task['daudziem_mehāniķiem'] && $task['mans_statuss'] === 'Pabeigts') {
                 setFlashMessage('error', 'Jūs jau esat pabeidzis savu uzdevuma daļu.');
            } elseif (!in_array($task['statuss'], ['Jauns', 'Procesā']) && !$task['daudziem_mehāniķiem']) {
                setFlashMessage('error', 'Var sākt tikai jaunus vai procesā esošus uzdevumus.');
            } elseif ($task['daudziem_mehāniķiem'] && $task['mans_statuss'] === 'Noņemts') {
                 setFlashMessage('error', 'Jums nav piešķirts šis uzdevums.');
            } else {
                // Ja uzdevums ir vairākiem mehāniķiem, atjaunināt piešķīrumu statusu
                if ($task['daudziem_mehāniķiem']) {
                    $stmt = $pdo->prepare("
                        UPDATE uzdevumu_piešķīrumi
                        SET statuss = 'Sākts', sākts = NOW()
                        WHERE uzdevuma_id = ? AND mehāniķa_id = ?
                    ");
                    $stmt->execute([$task_id, $currentUser['id']]);
                }

                // Ja uzdevums ir jauns, mainīt uz "Procesā"
                if ($task['statuss'] === 'Jauns' && !$task['daudziem_mehāniķiem']) {
                    $stmt = $pdo->prepare("UPDATE uzdevumi SET statuss = 'Procesā', sakuma_laiks = NOW() WHERE id = ?");
                    $stmt->execute([$task_id]);

                    // Pievienot vēsturi
                    $stmt = $pdo->prepare("
                        INSERT INTO uzdevumu_vesture (uzdevuma_id, iepriekšējais_statuss, jaunais_statuss, komentars, mainīja_id)
                        VALUES (?, 'Jauns', 'Procesā', 'Darbs sākts no sākumlapas', ?)
                    ");
                    $stmt->execute([$task_id, $currentUser['id']]);
                }

                // Sākt darba laika uzskaiti
                $stmt = $pdo->prepare("
                    INSERT INTO darba_laiks (lietotaja_id, uzdevuma_id, sakuma_laiks)
                    VALUES (?, ?, NOW())
                ");
                $stmt->execute([$currentUser['id'], $task_id]);

                // Ja šis ir kritisks uzdevums, noņemt citiem mehāniķiem
                if ($task['prioritate'] === 'Kritiska' && $task['problemas_id']) {
                    removeCriticalTaskFromOtherMechanics($task_id, $currentUser['id']);
                }

                setFlashMessage('success', 'Uzdevuma darbs sākts!');
            }

            $pdo->commit();

        } catch (PDOException $e) {
            $pdo->rollBack();
            setFlashMessage('error', 'Kļūda sākot darbu: ' . $e->getMessage());
        }
    }

    if ($action === 'complete_task' && isset($_POST['task_id'])) {
        $task_id = intval($_POST['task_id']);
        $faktiskais_ilgums = $_POST['faktiskais_ilgums'] ?? null;
        $komentars = $_POST['komentars'] ?? null;

        try {
            // Iegūt uzdevuma informāciju
            $stmt = $pdo->prepare("
                SELECT u.*, up.statuss as mans_statuss,
                       (SELECT COUNT(*) FROM darba_laiks WHERE uzdevuma_id = u.id AND lietotaja_id = ? AND beigu_laiks IS NULL) as aktīvs_darbs
                FROM uzdevumi u
                LEFT JOIN uzdevumu_piešķīrumi up ON u.id = up.uzdevuma_id AND up.mehāniķa_id = ?
                WHERE u.id = ? AND (u.piešķirts_id = ? OR EXISTS (
                    SELECT 1 FROM uzdevumu_piešķīrumi up2
                    WHERE up2.uzdevuma_id = u.id AND up2.mehāniķa_id = ? AND up2.statuss != 'Noņemts'
                ))
            ");
            $stmt->execute([$currentUser['id'], $currentUser['id'], $task_id, $currentUser['id'], $currentUser['id']]);
            $task = $stmt->fetch();

            if (!$task) {
                setFlashMessage('error', 'Uzdevums nav atrasts vai jums nav tiesību to pabeigt.');
            } elseif ($task['daudziem_mehāniķiem'] && $task['mans_statuss'] === 'Pabeigts') {
                 setFlashMessage('error', 'Jūs jau esat pabeidzis savu uzdevuma daļu.');
            } elseif (!in_array($task['statuss'], ['Jauns', 'Procesā']) && !$task['daudziem_mehāniķiem']) {
                setFlashMessage('error', 'Var pabeigt tikai jaunus vai procesā esošus uzdevumus.');
            } elseif ($task['daudziem_mehāniķiem'] && $task['mans_statuss'] === 'Noņemts') {
                 setFlashMessage('error', 'Jums nav piešķirts šis uzdevums.');
            } else {
                // Izmantot vairāku mehāniķu pabeigšanas funkciju
                if ($task['daudziem_mehāniķiem']) {
                    // Pārbaudīt vai mehāniķis ir sācis darbu pie šī uzdevuma
                    $stmt = $pdo->prepare("
                        SELECT statuss FROM uzdevumu_piešķīrumi
                        WHERE uzdevuma_id = ? AND mehāniķa_id = ? AND statuss != 'Noņemts'
                    ");
                    $stmt->execute([$task_id, $currentUser['id']]);
                    $assignment_status = $stmt->fetchColumn();

                    if (!$assignment_status) {
                        setFlashMessage('error', 'Jums nav piešķīruma šim uzdevumam.');
                    } elseif ($assignment_status === 'Pabeigts') {
                        setFlashMessage('error', 'Jūs jau esat pabeidzis savu uzdevuma daļu.');
                    } else {
                        if (completeMultiMechanicTask($task_id, $currentUser['id'])) {
                            $uzdevuma_tips = $task['veids'] === 'Regulārais' ? 'Regulārais uzdevums' : 'Uzdevums';
                            setFlashMessage('success', "Jūsu uzdevuma daļa veiksmīgi pabeigta!");
                        } else {
                            setFlashMessage('error', 'Kļūda pabeidzot vairāku mehāniķu uzdevumu.');
                        }
                    }
                } else {
                    // Parastā loģika vienam mehāniķim
                    $pdo->beginTransaction();

                    // Pabeigt darba laika uzskaiti
                    $stmt = $pdo->prepare("
                        UPDATE darba_laiks
                        SET beigu_laiks = NOW(),
                            stundu_skaits = TIMESTAMPDIFF(MINUTE, sakuma_laiks, NOW()) / 60.0
                        WHERE uzdevuma_id = ? AND lietotaja_id = ? AND beigu_laiks IS NULL
                    ");
                    $stmt->execute([$task_id, $currentUser['id']]);

                    // Aprēķināt kopējo darba laiku
                    $stmt = $pdo->prepare("
                        SELECT SUM(stundu_skaits) as kopejais_laiks
                        FROM darba_laiks
                        WHERE uzdevuma_id = ? AND lietotaja_id = ?
                    ");
                    $stmt->execute([$task_id, $currentUser['id']]);
                    $total_time = $stmt->fetchColumn() ?: 0;

                    // Atjaunot uzdevuma statusu ar papildus informāciju
                    $final_time = null;
                    if (!empty($faktiskais_ilgums) && is_numeric($faktiskais_ilgums)) {
                        $final_time = floatval($faktiskais_ilgums);
                    } elseif ($total_time > 0) {
                        $final_time = $total_time;
                    }

                    $stmt = $pdo->prepare("
                        UPDATE uzdevumi
                        SET statuss = 'Pabeigts',
                            beigu_laiks = NOW(),
                            atbildes_komentars = ?,
                            faktiskais_ilgums = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$komentars, $final_time, $task_id]);

                    // Pievienot vēsturi
                    $uzdevuma_tips = $task['veids'] === 'Regulārais' ? 'Regulārais uzdevums' : 'Uzdevums';
                    $stmt = $pdo->prepare("
                        INSERT INTO uzdevumu_vesture
                        (uzdevuma_id, iepriekšējais_statuss, jaunais_statuss, komentars, mainīja_id)
                        VALUES (?, ?, 'Pabeigts', ?, ?)
                    ");
                    $stmt->execute([
                        $task_id,
                        $task['statuss'],
                        "$uzdevuma_tips pabeigts no sākumlapas. Komentārs: $komentars",
                        $currentUser['id']
                    ]);

                    // Paziņot menedžerim/administratoram par šī uzdevuma pabeigšanu
                    $taskStmt = $pdo->prepare("SELECT u.nosaukums, u.veids FROM uzdevumi u WHERE u.id = ?");
                    $taskStmt->execute([$task_id]);
                    $task_info = $taskStmt->fetch();
                    $uzdevuma_tips_notification = $task_info['veids'] === 'Regulārais' ? 'Regulārais uzdevums' : 'Uzdevums';


                    // Paziņot menedžerim/administratoram
                    $stmt = $pdo->prepare("
                        SELECT l.id, l.loma, CONCAT(l.vards, ' ', l.uzvards) as pilns_vards
                        FROM lietotaji l
                        WHERE l.loma IN ('Administrators', 'Menedžeris') AND l.statuss = 'Aktīvs'
                    ");
                    $stmt->execute();
                    $managers = $stmt->fetchAll();

                    foreach ($managers as $manager) {
                        $notification_result = createNotification(
                            $manager['id'],
                            "$uzdevuma_tips_notification pabeigts",
                            "Mehāniķis {$currentUser['vards']} {$currentUser['uzvards']} ir pabeidzis uzdevumu: {$task_info['nosaukums']}",
                            'Statusa maiņa',
                            'Uzdevums',
                            $task_id
                        );

                        error_log("Paziņojums menedžerim {$manager['pilns_vards']} ({$manager['id']}) par uzdevumu $task_id izveidots: " . ($notification_result ? 'jā' : 'nē'));

                        // Telegram paziņojums
                        try {
                            sendTaskTelegramNotification($manager['id'], $task_info['nosaukums'], $task_id, 'task_completed');
                        } catch (Exception $e) {
                            error_log("Telegram notification error in index.php: " . $e->getMessage());
                        }
                    }


                    $pdo->commit();
                    setFlashMessage('success', "$uzdevuma_tips pabeigts!");
                }
            }

        } catch (PDOException $e) {
            $pdo->rollBack();
            setFlashMessage('error', 'Kļūda pabeidzot uzdevumu: ' . $e->getMessage());
        }
    }
}

// Iegūt statistiku atkarībā no lietotāja lomas
$stats = [];

try {
    if (hasRole([ROLE_ADMIN, ROLE_MANAGER])) {
        // Administratora un menedžera statistika

        // Kopējie uzdevumi (ikdienas)
        $stmt = $pdo->query("SELECT COUNT(*) FROM uzdevumi WHERE veids = 'Ikdienas'");
        $stats['total_tasks'] = $stmt->fetchColumn();

        // Aktīvie uzdevumi (ikdienas)
        $stmt = $pdo->query("SELECT COUNT(*) FROM uzdevumi WHERE veids = 'Ikdienas' AND statuss IN ('Jauns', 'Procesā')");
        $stats['active_tasks'] = $stmt->fetchColumn();

        // Pabeigto uzdevumu šomēnes (ikdienas)
        $stmt = $pdo->query("
            SELECT COUNT(*) FROM uzdevumi
            WHERE veids = 'Ikdienas' AND statuss = 'Pabeigts'
            AND MONTH(beigu_laiks) = MONTH(NOW())
            AND YEAR(beigu_laiks) = YEAR(NOW())
        ");
        $stats['completed_this_month'] = $stmt->fetchColumn();

        // Kritiskās prioritātes uzdevumi (VISI - gan ikdienas, gan regulārie)
        $stmt = $pdo->query("SELECT COUNT(*) FROM uzdevumi WHERE prioritate = 'Kritiska' AND statuss IN ('Jauns', 'Procesā')");
        $stats['critical_tasks'] = $stmt->fetchColumn();

        // Kritiskās problēmas (visas kritiskās problēmas)
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM problemas WHERE prioritate = 'Kritiska'");
            $stats['critical_problems'] = $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error getting critical problems: " . $e->getMessage());
            $stats['critical_problems'] = 0;
        }

        // Jaunas problēmas - pārbaudīt ar precīzāku vaicājumu
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM problemas WHERE statuss = 'Jauna'");
            $stats['new_problems'] = $stmt->fetchColumn();
            
            // Ja nav jaunu problēmu, pārbaudīt vai vispār ir problēmas
            if ($stats['new_problems'] == 0) {
                $stmt = $pdo->query("SELECT COUNT(*) FROM problemas");
                $total_problems = $stmt->fetchColumn();
                if ($total_problems > 0) {
                    // Ir problēmas, bet nav ar statusu "Jauna"
                    $stats['new_problems'] = 0;
                }
            }
        } catch (PDOException $e) {
            error_log("Error getting new problems: " . $e->getMessage());
            $stats['new_problems'] = 0;
        }

        // Aktīvie mehāniķi
        $stmt = $pdo->query("SELECT COUNT(*) FROM lietotaji WHERE loma = 'Mehāniķis' AND statuss = 'Aktīvs'");
        $stats['active_mechanics'] = $stmt->fetchColumn();

        // Regulārie uzdevumi
        $stmt = $pdo->query("SELECT COUNT(*) FROM regularo_uzdevumu_sabloni WHERE aktīvs = 1");
        $stats['active_regular_templates'] = $stmt->fetchColumn();

        // Šodienas regulārie uzdevumi
        $stmt = $pdo->query("SELECT COUNT(*) FROM uzdevumi WHERE veids = 'Regulārais' AND DATE(izveidots) = CURDATE()");
        $stats['todays_regular_tasks'] = $stmt->fetchColumn();

        // Jaunākie uzdevumi (ikdienas)
        $stmt = $pdo->prepare("
            SELECT u.*, v.nosaukums as vietas_nosaukums, i.nosaukums as iekartas_nosaukums,
                   CASE
                       WHEN u.daudziem_mehāniķiem = 1 THEN (
                           SELECT GROUP_CONCAT(CONCAT(LEFT(lm.vards, 1), '. ', lm.uzvards) SEPARATOR ', ')
                           FROM uzdevumu_piešķīrumi up
                           JOIN lietotaji lm ON up.mehāniķa_id = lm.id
                           WHERE up.uzdevuma_id = u.id AND up.statuss != 'Noņemts'
                       )
                       WHEN u.piešķirts_id IS NOT NULL THEN CONCAT(l.vards, ' ', l.uzvards)
                       ELSE 'Nav piešķirts'
                   END as mehaniķa_vards
            FROM uzdevumi u
            LEFT JOIN vietas v ON u.vietas_id = v.id
            LEFT JOIN iekartas i ON u.iekartas_id = i.id
            LEFT JOIN lietotaji l ON u.piešķirts_id = l.id
            WHERE u.veids = 'Ikdienas'
            ORDER BY u.izveidots DESC
            LIMIT 5
        ");
        $stmt->execute();
        $latest_tasks = $stmt->fetchAll();

        // Jaunākie regulārie uzdevumi
        $stmt = $pdo->prepare("
            SELECT u.*, v.nosaukums as vietas_nosaukums, i.nosaukums as iekartas_nosaukums,
                   r.periodicitate,
                   CASE
                       WHEN u.daudziem_mehāniķiem = 1 THEN (
                           SELECT GROUP_CONCAT(CONCAT(LEFT(lm.vards, 1), '. ', lm.uzvards) SEPARATOR ', ')
                           FROM uzdevumu_piešķīrumi up
                           JOIN lietotaji lm ON up.mehāniķa_id = lm.id
                           WHERE up.uzdevuma_id = u.id AND up.statuss != 'Noņemts'
                       )
                       WHEN u.piešķirts_id IS NOT NULL THEN CONCAT(l.vards, ' ', l.uzvards)
                       ELSE 'Nav piešķirts'
                   END as mehaniķa_vards
            FROM uzdevumi u
            LEFT JOIN vietas v ON u.vietas_id = v.id
            LEFT JOIN iekartas i ON u.iekartas_id = i.id
            LEFT JOIN lietotaji l ON u.piešķirts_id = l.id
            LEFT JOIN regularo_uzdevumu_sabloni r ON u.regulara_uzdevuma_id = r.id
            WHERE u.veids = 'Regulārais'
            ORDER BY u.izveidots DESC
            LIMIT 5
        ");
        $stmt->execute();
        $latest_regular_tasks = $stmt->fetchAll();

        // Jaunākās problēmas
        $stmt = $pdo->prepare("
            SELECT p.*, v.nosaukums as vietas_nosaukums, i.nosaukums as iekartas_nosaukums,
                   CONCAT(l.vards, ' ', l.uzvards) as zinotaja_vards
            FROM problemas p
            LEFT JOIN vietas v ON p.vietas_id = v.id
            LEFT JOIN iekartas i ON p.iekartas_id = i.id
            LEFT JOIN lietotaji l ON p.zinotajs_id = l.id
            WHERE p.statuss = 'Jauna'
            ORDER BY p.izveidots DESC
            LIMIT 5
        ");
        $stmt->execute();
        $latest_problems = $stmt->fetchAll();

    } elseif (hasRole(ROLE_MECHANIC)) {
        // Mehāniķa statistika

        // Mani ikdienas uzdevumi
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM uzdevumi u
            WHERE (u.piešķirts_id = ? OR EXISTS (
                SELECT 1 FROM uzdevumu_piešķīrumi up
                WHERE up.uzdevuma_id = u.id AND up.mehāniķa_id = ? AND up.statuss != 'Noņemts'
            )) AND u.veids = 'Ikdienas'
        ");
        $stmt->execute([$currentUser['id'], $currentUser['id']]);
        $stats['my_total_tasks'] = $stmt->fetchColumn();

        // Mani aktīvie ikdienas uzdevumi
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM uzdevumi u
            WHERE (u.piešķirts_id = ? OR EXISTS (
                SELECT 1 FROM uzdevumu_piešķīrumi up
                WHERE up.uzdevuma_id = u.id AND up.mehāniķa_id = ? AND up.statuss != 'Noņemts'
            )) AND u.veids = 'Ikdienas' AND u.statuss IN ('Jauns', 'Procesā')
        ");
        $stmt->execute([$currentUser['id'], $currentUser['id']]);
        $stats['my_active_tasks'] = $stmt->fetchColumn();

        // Mani pabeigto ikdienas uzdevumi šomēnes
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM uzdevumi u
            WHERE (u.piešķirts_id = ? OR EXISTS (
                SELECT 1 FROM uzdevumu_piešķīrumi up
                WHERE up.uzdevuma_id = u.id AND up.mehāniķa_id = ? AND up.statuss != 'Noņemts'
            )) AND u.veids = 'Ikdienas' AND u.statuss = 'Pabeigts'
            AND MONTH(u.beigu_laiks) = MONTH(NOW())
            AND YEAR(u.beigu_laiks) = YEAR(NOW())
        ");
        $stmt->execute([$currentUser['id'], $currentUser['id']]);
        $stats['my_completed_this_month'] = $stmt->fetchColumn();

        // Mani regulārie uzdevumi
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM uzdevumi u
            WHERE (u.piešķirts_id = ? OR EXISTS (
                SELECT 1 FROM uzdevumu_piešķīrumi up
                WHERE up.uzdevuma_id = u.id AND up.mehāniķa_id = ? AND up.statuss != 'Noņemts'
            )) AND u.veids = 'Regulārais'
        ");
        $stmt->execute([$currentUser['id'], $currentUser['id']]);
        $stats['my_total_regular_tasks'] = $stmt->fetchColumn();

        // Mani aktīvie regulārie uzdevumi
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM uzdevumi u
            WHERE (u.piešķirts_id = ? OR EXISTS (
                SELECT 1 FROM uzdevumu_piešķīrumi up
                WHERE up.uzdevuma_id = u.id AND up.mehāniķa_id = ? AND up.statuss != 'Noņemts'
            )) AND u.veids = 'Regulārais' AND u.statuss IN ('Jauns', 'Procesā')
        ");
        $stmt->execute([$currentUser['id'], $currentUser['id']]);
        $stats['my_active_regular_tasks'] = $stmt->fetchColumn();

        // Nokavētie uzdevumi (VISI - gan ikdienas, gan regulārie)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM uzdevumi u
            WHERE (u.piešķirts_id = ? OR EXISTS (
                SELECT 1 FROM uzdevumu_piešķīrumi up
                WHERE up.uzdevuma_id = u.id AND up.mehāniķa_id = ? AND up.statuss != 'Noņemts'
            )) AND ((u.jabeidz_lidz IS NOT NULL AND u.jabeidz_lidz < NOW() AND u.statuss NOT IN ('Pabeigts', 'Atcelts'))
                 OR (u.statuss IN ('Jauns', 'Procesā') AND DATEDIFF(NOW(), u.izveidots) > 3))
        ");
        $stmt->execute([$currentUser['id'], $currentUser['id']]);
        $stats['my_overdue_tasks'] = $stmt->fetchColumn();

        // Mani jaunākie ikdienas uzdevumi ar darba statusu
        $stmt = $pdo->prepare("
            SELECT u.*, v.nosaukums as vietas_nosaukums, i.nosaukums as iekartas_nosaukums,
                   up.statuss as mans_statuss,
                   (SELECT COUNT(*) FROM darba_laiks WHERE uzdevuma_id = u.id AND lietotaja_id = ? AND beigu_laiks IS NULL) as aktīvs_darbs,
                   CASE
                       WHEN u.jabeidz_lidz IS NOT NULL AND u.jabeidz_lidz < NOW() AND u.statuss NOT IN ('Pabeigts', 'Atcelts') THEN 1
                       WHEN u.statuss IN ('Jauns', 'Procesā') AND DATEDIFF(NOW(), u.izveidots) > 3 THEN 1
                       ELSE 0
                   END as ir_nokavets,
                   CASE
                       WHEN u.prioritate = 'Kritiska' AND u.statuss NOT IN ('Pabeigts', 'Atcelts') THEN 1
                       ELSE 0
                   END as ir_kritisks_aktīvs
            FROM uzdevumi u
            LEFT JOIN vietas v ON u.vietas_id = v.id
            LEFT JOIN iekartas i ON u.iekartas_id = i.id
            LEFT JOIN uzdevumu_piešķīrumi up ON u.id = up.uzdevuma_id AND up.mehāniķa_id = ?
            WHERE (u.piešķirts_id = ? OR EXISTS (
                SELECT 1 FROM uzdevumu_piešķīrumi up2
                WHERE up2.uzdevuma_id = u.id AND up2.mehāniķa_id = ? AND up2.statuss != 'Noņemts'
            )) AND u.veids = 'Ikdienas'
            ORDER BY ir_kritisks_aktīvs DESC, ir_nokavets DESC, u.izveidots DESC
            LIMIT 5
        ");
        $stmt->execute([$currentUser['id'], $currentUser['id'], $currentUser['id'], $currentUser['id']]);
        $my_latest_tasks = $stmt->fetchAll();

        // Mani jaunākie regulārie uzdevumi ar darba statusu
        $stmt = $pdo->prepare("
            SELECT u.*, v.nosaukums as vietas_nosaukums, i.nosaukums as iekartas_nosaukums,
                   r.periodicitate,
                   up.statuss as mans_statuss,
                   (SELECT COUNT(*) FROM darba_laiks WHERE uzdevuma_id = u.id AND lietotaja_id = ? AND beigu_laiks IS NULL) as aktīvs_darbs,
                   CASE
                       WHEN u.jabeidz_lidz IS NOT NULL AND u.jabeidz_lidz < NOW() AND u.statuss NOT IN ('Pabeigts', 'Atcelts') THEN 1
                       WHEN u.statuss IN ('Jauns', 'Procesā') AND DATEDIFF(NOW(), u.izveidots) > 1 THEN 1
                       ELSE 0
                   END as ir_nokavets,
                   CASE
                       WHEN u.prioritate = 'Kritiska' AND u.statuss NOT IN ('Pabeigts', 'Atcelts') THEN 1
                       ELSE 0
                   END as ir_kritisks_aktīvs
            FROM uzdevumi u
            LEFT JOIN vietas v ON u.vietas_id = v.id
            LEFT JOIN iekartas i ON u.iekartas_id = i.id
            LEFT JOIN regularo_uzdevumu_sabloni r ON u.regulara_uzdevuma_id = r.id
            LEFT JOIN uzdevumu_piešķīrumi up ON u.id = up.uzdevuma_id AND up.mehāniķa_id = ?
            WHERE (u.piešķirts_id = ? OR EXISTS (
                SELECT 1 FROM uzdevumu_piešķīrumi up2
                WHERE up2.uzdevuma_id = u.id AND up2.mehāniķa_id = ? AND up2.statuss != 'Noņemts'
            )) AND u.veids = 'Regulārais'
            ORDER BY ir_kritisks_aktīvs DESC, ir_nokavets DESC, u.izveidots DESC
            LIMIT 5
        ");
        $stmt->execute([$currentUser['id'], $currentUser['id'], $currentUser['id'], $currentUser['id']]);
        $my_latest_regular_tasks = $stmt->fetchAll();

    } elseif (hasRole(ROLE_OPERATOR)) {
        // Operatora statistika

        // Manas ziņotās problēmas
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM problemas WHERE zinotajs_id = ?");
        $stmt->execute([$currentUser['id']]);
        $stats['my_total_problems'] = $stmt->fetchColumn();

        // Manas neatrisinātas problēmas
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM problemas WHERE zinotajs_id = ? AND statuss IN ('Jauna', 'Apskatīta')");
        $stmt->execute([$currentUser['id']]);
        $stats['my_pending_problems'] = $stmt->fetchColumn();

        // Manas atrisinātas problēmas šomēnes
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM problemas
            WHERE zinotajs_id = ? AND statuss = 'Pārvērsta uzdevumā'
            AND MONTH(atjaunots) = MONTH(NOW())
            AND YEAR(atjaunots) = YEAR(NOW())
        ");
        $stmt->execute([$currentUser['id']]);
        $stats['my_resolved_this_month'] = $stmt->fetchColumn();

        // Manas kritiskās problēmas (operators redz tikai savas)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM problemas WHERE zinotajs_id = ? AND prioritate = 'Kritiska'");
        $stmt->execute([$currentUser['id']]);
        $stats['critical_problems'] = $stmt->fetchColumn();

        // Manas jaunākās problēmas
        $stmt = $pdo->prepare("
            SELECT p.*, v.nosaukums as vietas_nosaukums, i.nosaukums as iekartas_nosaukums
            FROM problemas p
            LEFT JOIN vietas v ON p.vietas_id = v.id
            LEFT JOIN iekartas i ON p.iekartas_id = i.id
            WHERE p.zinotajs_id = ?
            ORDER BY p.izveidots DESC
            LIMIT 5
        ");
        $stmt->execute([$currentUser['id']]);
        $my_latest_problems = $stmt->fetchAll();
    }

} catch (PDOException $e) {
    error_log("Kļūda iegūstot statistiku: " . $e->getMessage());
}

include 'includes/header.php';
?>

<?php if (hasRole([ROLE_ADMIN, ROLE_MANAGER])): ?>
<!-- Statistikas kartes tikai administratoram un menedžerim -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-number"><?php echo $stats['total_tasks'] ?? 0; ?></div>
        <div class="stat-label">Ikdienas uzdevumi</div>
    </div>

    <div class="stat-card">
        <div class="stat-number"><?php echo $stats['active_tasks'] ?? 0; ?></div>
        <div class="stat-label">Aktīvie uzdevumi</div>
    </div>

    <div class="stat-card">
        <div class="stat-number"><?php echo $stats['completed_this_month'] ?? 0; ?></div>
        <div class="stat-label">Pabeigti šomēnes</div>
    </div>

    <div class="stat-card" style="border-left-color: var(--danger-color);">
        <div class="stat-number" style="color: var(--danger-color);"><?php echo $stats['critical_tasks'] ?? 0; ?></div>
        <div class="stat-label">Kritiskie uzdevumi</div>
    </div>

    <div class="stat-card" style="border-left-color: var(--danger-color);">
        <div class="stat-number" style="color: var(--danger-color);"><?php echo $stats['critical_problems'] ?? 0; ?></div>
        <div class="stat-label">Kritiskās problēmas (kopā)</div>
    </div>

    <div class="stat-card" style="border-left-color: var(--warning-color);">
        <div class="stat-number" style="color: var(--warning-color);"><?php echo $stats['new_problems'] ?? 0; ?></div>
        <div class="stat-label">Jaunas problēmas</div>
    </div>

    <div class="stat-card" style="border-left-color: var(--success-color);">
        <div class="stat-number" style="color: var(--success-color);"><?php echo $stats['active_mechanics'] ?? 0; ?></div>
        <div class="stat-label">Aktīvie mehāniķi</div>
    </div>

    <div class="stat-card" style="border-left-color: var(--info-color);">
        <div class="stat-number" style="color: var(--info-color);"><?php echo $stats['active_regular_templates'] ?? 0; ?></div>
        <div class="stat-label">Aktīvie regulārie šabloni</div>
    </div>

    <div class="stat-card" style="border-left-color: var(--primary-color);">
        <div class="stat-number" style="color: var(--primary-color);"><?php echo $stats['todays_regular_tasks'] ?? 0; ?></div>
        <div class="stat-label">Šodienas regulārie uzdevumi</div>
    </div>
</div>

<?php elseif (hasRole(ROLE_MECHANIC)): ?>
<!-- Mehāniķa ātras darbības -->
<div class="card">
    <div class="card-header">
        <h3>Ātras darbības</h3>
    </div>
    <div class="card-body">
        <div class="d-flex flex-wrap">
            <a href="my_tasks.php" class="btn btn-primary">Mani ikdienas uzdevumi</a>
            <a href="regular_tasks_mechanic.php" class="btn btn-info">Regulārie uzdevumi</a>
            <a href="completed_tasks.php" class="btn btn-success">Pabeigto uzdevumu vēsture</a>
        </div>
    </div>
</div>

<!-- Mehāniķa jaunākie uzdevumi augšā -->
<div class="row">
    <!-- Mehāniķa ikdienas uzdevumi -->
    <?php if (!empty($my_latest_tasks)): ?>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3>Mani jaunākie ikdienas uzdevumi</h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Nosaukums</th>
                                    <th>Vieta</th>
                                    <th>Prioritāte</th>
                                    <th>Statuss</th>
                                    <th>Termiņš</th>
                                    <th>Darbības</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($my_latest_tasks as $task): ?>
                                    <tr class="<?php echo $task['ir_nokavets'] ? 'table-danger' : ''; ?>">
                                        <td>
                                            <strong><?php echo htmlspecialchars($task['nosaukums']); ?></strong>
                                            <?php if ($task['ir_nokavets']): ?>
                                                <span class="badge badge-danger">NOKAVĒTS</span>
                                            <?php endif; ?>
                                            <?php if ($task['aktīvs_darbs']): ?>
                                                <span class="badge badge-warning">PROCESĀ</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($task['vietas_nosaukums'] ?? ''); ?></td>
                                        <td>
                                            <span class="priority-badge <?php echo getPriorityClass($task['prioritate']); ?>">
                                                <?php echo $task['prioritate']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo getStatusClass($task['mans_statuss'] ?: $task['statuss']); ?>">
                                                <?php echo $task['mans_statuss'] ?: $task['statuss']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($task['jabeidz_lidz']): ?>
                                                <small class="<?php echo $task['ir_nokavets'] ? 'text-danger' : ''; ?>">
                                                    <?php echo formatDate($task['jabeidz_lidz']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                                // Sākotnējais uzdevuma statuss
                                                $task_status = $task['statuss'];
                                                $my_assignment_status = null;
                                                $can_work = true;

                                                // Ja tas ir grupas uzdevums, pārbaudīt individuālo piešķīrumu
                                                if ($task['daudziem_mehāniķiem']) {
                                                    $stmt = $pdo->prepare("
                                                        SELECT statuss FROM uzdevumu_piešķīrumi
                                                        WHERE uzdevuma_id = ? AND mehāniķa_id = ? AND statuss != 'Noņemts'
                                                    ");
                                                    $stmt->execute([$task['id'], $currentUser['id']]);
                                                    $my_assignment_status = $stmt->fetchColumn();

                                                    if (!$my_assignment_status) {
                                                        $can_work = false; // Nav piešķirts
                                                    }
                                                }

                                                // Noteikt, kāds statuss ir aktīvs
                                                if ($task['daudziem_mehāniķiem'] && $my_assignment_status) {
                                                    $effective_status = $my_assignment_status;
                                                } else {
                                                    $effective_status = $task_status;
                                                }

                                                // Parādīt pogas atkarībā no statusa
                                                if (!$can_work): ?>
                                                    <span class="text-muted">Nav piešķirts</span>
                                                <?php elseif ($effective_status === 'Pabeigts'): ?>
                                                    <span class="text-success">✓ Pabeigts</span>
                                                <?php elseif (in_array($effective_status, ['Jauns', 'Piešķirts'])): ?>
                                                    <div class="btn-group">
                                                        <button onclick="startTaskWork(<?php echo $task['id']; ?>)" class="btn btn-sm btn-success">Sākt</button>
                                                        <button onclick="completeTaskWork(<?php echo $task['id']; ?>)" class="btn btn-sm btn-primary">Pabeigt</button>
                                                    </div>
                                                <?php elseif ($effective_status === 'Sākts' && !$task['aktīvs_darbs']): ?>
                                                    <div class="btn-group">
                                                        <button onclick="startTaskWork(<?php echo $task['id']; ?>)" class="btn btn-sm btn-warning">Turpināt</button>
                                                        <button onclick="completeTaskWork(<?php echo $task['id']; ?>)" class="btn btn-sm btn-success">Pabeigt</button>
                                                    </div>
                                                <?php elseif (in_array($effective_status, ['Sākts', 'Procesā'])): ?>
                                                    <button onclick="completeTaskWork(<?php echo $task['id']; ?>)" class="btn btn-sm btn-success">Pabeigt</button>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="my_tasks.php" class="btn btn-sm btn-primary">Skatīt visus ikdienas uzdevumus</a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Mehāniķa regulārie uzdevumi -->
    <?php if (!empty($my_latest_regular_tasks)): ?>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3>Mani jaunākie regulārie uzdevumi</h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Nosaukums</th>
                                    <th>Vieta</th>
                                    <th>Periodicitāte</th>
                                    <th>Statuss</th>
                                    <th>Darbības</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($my_latest_regular_tasks as $task): ?>
                                    <tr class="<?php echo $task['ir_nokavets'] ? 'table-danger' : ''; ?>">
                                        <td>
                                            <strong><?php echo htmlspecialchars($task['nosaukums']); ?></strong>
                                            <?php if ($task['ir_nokavets']): ?>
                                                <span class="badge badge-danger">NOKAVĒTS</span>
                                            <?php endif; ?>
                                            <?php if ($task['aktīvs_darbs']): ?>
                                                <span class="badge badge-warning">PROCESĀ</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($task['vietas_nosaukums'] ?? ''); ?></td>
                                        <td>
                                            <small class="text-muted"><?php echo $task['periodicitate']; ?></small>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo getStatusClass($task['mans_statuss'] ?: $task['statuss']); ?>">
                                                <?php echo $task['mans_statuss'] ?: $task['statuss']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                                // Sākotnējais uzdevuma statuss
                                                $task_status = $task['statuss'];
                                                $my_assignment_status = null;
                                                $can_work = true;

                                                // Ja tas ir grupas uzdevums, pārbaudīt individuālo piešķīrumu
                                                if ($task['daudziem_mehāniķiem']) {
                                                    $stmt = $pdo->prepare("
                                                        SELECT statuss FROM uzdevumu_piešķīrumi
                                                        WHERE uzdevuma_id = ? AND mehāniķa_id = ? AND statuss != 'Noņemts'
                                                    ");
                                                    $stmt->execute([$task['id'], $currentUser['id']]);
                                                    $my_assignment_status = $stmt->fetchColumn();

                                                    if (!$my_assignment_status) {
                                                        $can_work = false; // Nav piešķirts
                                                    }
                                                }

                                                // Noteikt, kāds statuss ir aktīvs
                                                if ($task['daudziem_mehāniķiem'] && $my_assignment_status) {
                                                    $effective_status = $my_assignment_status;
                                                } else {
                                                    $effective_status = $task_status;
                                                }

                                                // Parādīt pogas atkarībā no statusa
                                                if (!$can_work): ?>
                                                    <span class="text-muted">Nav piešķirts</span>
                                                <?php elseif ($effective_status === 'Pabeigts'): ?>
                                                    <span class="text-success">✓ Pabeigts</span>
                                                <?php elseif (in_array($effective_status, ['Jauns', 'Piešķirts'])): ?>
                                                    <div class="btn-group">
                                                        <button onclick="startTaskWork(<?php echo $task['id']; ?>)" class="btn btn-sm btn-success">Sākt</button>
                                                        <button onclick="completeTaskWork(<?php echo $task['id']; ?>)" class="btn btn-sm btn-primary">Pabeigt</button>
                                                    </div>
                                                <?php elseif ($effective_status === 'Sākts' && !$task['aktīvs_darbs']): ?>
                                                    <div class="btn-group">
                                                        <button onclick="startTaskWork(<?php echo $task['id']; ?>)" class="btn btn-sm btn-warning">Turpināt</button>
                                                        <button onclick="completeTaskWork(<?php echo $task['id']; ?>)" class="btn btn-sm btn-success">Pabeigt</button>
                                                    </div>
                                                <?php elseif (in_array($effective_status, ['Sākts', 'Procesā'])): ?>
                                                    <button onclick="completeTaskWork(<?php echo $task['id']; ?>)" class="btn btn-sm btn-success">Pabeigt</button>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="regular_tasks_mechanic.php" class="btn btn-sm btn-info">Skatīt visus regulāros uzdevumus</a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (hasRole([ROLE_ADMIN, ROLE_MANAGER, ROLE_OPERATOR])): ?>
<!-- Ātras darbības -->
<div class="card">
    <div class="card-header">
        <h3>Ātras darbības</h3>
    </div>
    <div class="card-body">
        <div class="d-flex flex-wrap">
            <?php if (hasRole([ROLE_ADMIN, ROLE_MANAGER])): ?>
                <a href="create_task.php" class="btn btn-primary">Izveidot uzdevumu</a>
                <a href="tasks.php" class="btn btn-secondary">Skatīt visus uzdevumus</a>
                <a href="regular_tasks.php" class="btn btn-info">Regulārie uzdevumi</a>
                <a href="problems.php" class="btn btn-warning">Skatīt problēmas</a>
                <?php if (hasRole(ROLE_ADMIN)): ?>
                    <a href="reports.php" class="btn btn-success">Atskaites</a>
                    <a href="users.php" class="btn btn-outline-primary">Pārvaldīt lietotājus</a>
                <?php endif; ?>
            <?php elseif (hasRole(ROLE_OPERATOR)): ?>
                <a href="report_problem.php" class="btn btn-danger">Ziņot problēmu</a>
                <a href="my_problems.php" class="btn btn-secondary">Manas problēmas</a>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (hasRole([ROLE_ADMIN, ROLE_MANAGER])): ?>
<!-- Jaunākie ieraksti administrators/menedžerim -->
<div class="row">
    <!-- Jaunākie ikdienas uzdevumi -->
    <?php if (!empty($latest_tasks)): ?>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3>Jaunākie ikdienas uzdevumi</h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Nosaukums</th>
                                    <th>Mehāniķis</th>
                                    <th>Prioritāte</th>
                                    <th>Statuss</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($latest_tasks as $task): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($task['nosaukums']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($task['vietas_nosaukums'] ?? ''); ?></small>
                                        </td>
                                        <td>
                                            <?php if ($task['daudziem_mehāniķiem']): ?>
                                                <span class="group-work-icon" title="Grupas darbs">👥</span>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($task['mehaniķa_vards'] ?? ''); ?>
                                        </td>
                                        <td>
                                            <span class="priority-badge <?php echo getPriorityClass($task['prioritate']); ?>">
                                                <?php echo $task['prioritate']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo getStatusClass($task['statuss']); ?>">
                                                <?php echo $task['statuss']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="tasks.php" class="btn btn-sm btn-primary">Skatīt visus uzdevumus</a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Jaunākie regulārie uzdevumi -->
    <?php if (!empty($latest_regular_tasks)): ?>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3>Jaunākie regulārie uzdevumi</h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Nosaukums</th>
                                    <th>Mehāniķis</th>
                                    <th>Periodicitāte</th>
                                    <th>Statuss</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($latest_regular_tasks as $task): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($task['nosaukums']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($task['vietas_nosaukums'] ?? ''); ?></small>
                                        </td>
                                        <td>
                                            <?php if ($task['daudziem_mehāniķiem']): ?>
                                                <span class="group-work-icon" title="Grupas darbs">👥</span>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($task['mehaniķa_vards'] ?? ''); ?>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?php echo $task['periodicitate']; ?></small>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo getStatusClass($task['statuss']); ?>">
                                                <?php echo $task['statuss']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="regular_tasks.php" class="btn btn-sm btn-info">Skatīt regulāros uzdevumus</a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Jaunākās problēmas -->
    <?php if (!empty($latest_problems)): ?>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3>Jaunākās problēmas</h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Nosaukums</th>
                                    <th>Ziņotājs</th>
                                    <th>Prioritāte</th>
                                    <th>Izveidots</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($latest_problems as $problem): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($problem['nosaukums']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($problem['vietas_nosaukums'] ?? ''); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($problem['zinotaja_vards']); ?></td>
                                        <td>
                                            <span class="priority-badge <?php echo getPriorityClass($problem['prioritate']); ?>">
                                                <?php echo $problem['prioritate']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small><?php echo formatDate($problem['izveidots']); ?></small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="problems.php" class="btn btn-sm btn-warning">Skatīt visas problēmas</a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php elseif (hasRole(ROLE_OPERATOR) && !empty($my_latest_problems)): ?>
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h3>Manas jaunākās problēmas</h3>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nosaukums</th>
                                <th>Vieta</th>
                                <th>Prioritāte</th>
                                <th>Statuss</th>
                                <th>Izveidots</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($my_latest_problems as $problem): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($problem['nosaukums']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($problem['vietas_nosaukums'] ?? ''); ?></td>
                                    <td>
                                        <span class="priority-badge <?php echo getPriorityClass($problem['prioritate']); ?>">
                                            <?php echo $problem['prioritate']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $problem['statuss'])); ?>">
                                            <?php echo $problem['statuss']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small><?php echo formatDate($problem['izveidots']); ?></small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer">
                <a href="my_problems.php" class="btn btn-sm btn-secondary">Skatīt visas manas problēmas</a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modālais logs uzdevuma pabeigšanai -->
<div id="completeTaskModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Pabeigt uzdevumu</h3>
            <button onclick="closeModal('completeTaskModal')" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <form id="completeTaskForm" method="POST">
                <input type="hidden" name="action" value="complete_task">
                <input type="hidden" name="task_id" id="completeTaskId">

                <div class="form-group">
                    <label for="faktiskais_ilgums" class="form-label">Faktiskais izpildes laiks (stundas)</label>
                    <input type="number" id="faktiskais_ilgums" name="faktiskais_ilgums" class="form-control" step="0.1" min="0">
                    <small class="form-text text-muted">Atstājiet tukšu, lai automātiski aprēķinātu no darba laika</small>
                </div>

                <div class="form-group">
                    <label for="komentars" class="form-label">Komentārs par paveikto darbu</label>
                    <textarea id="komentars" name="komentars" class="form-control" rows="4" placeholder="Aprakstiet paveikto darbu, izmantotie materiāli, problēmas, u.c."></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button onclick="closeModal('completeTaskModal')" class="btn btn-secondary">Atcelt</button>
            <button onclick="submitTaskCompletionForm()" class="btn btn-success">Pabeigt uzdevumu</button>
        </div>
    </div>
</div>

<script>
// Uzdevuma darba sākšana
function startTaskWork(taskId) {
    if (confirm('Vai vēlaties sākt darbu pie šī uzdevuma?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';

        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'start_work';

        const taskInput = document.createElement('input');
        taskInput.type = 'hidden';
        taskInput.name = 'task_id';
        taskInput.value = taskId;

        form.appendChild(actionInput);
        form.appendChild(taskInput);

        document.body.appendChild(form);
        form.submit();
    }
}

// Uzdevuma darba pabeigšana ar modālo logu
function completeTaskWork(taskId) {
    document.getElementById('completeTaskId').value = taskId;
    document.getElementById('faktiskais_ilgums').value = '';
    document.getElementById('komentars').value = '';
    openModal('completeTaskModal');
}

function submitTaskCompletionForm() {
    const form = document.getElementById('completeTaskForm');
    form.submit();
}

// Jauna funkcija uzdevuma pabeigšanai ar komentāru un laiku
function submitTaskCompletion(taskId, faktiskais_ilgums = '', komentars = '') {
    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';

    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'complete_task';

    const taskInput = document.createElement('input');
    taskInput.type = 'hidden';
    taskInput.name = 'task_id';
    taskInput.value = taskId;

    const timeInput = document.createElement('input');
    timeInput.type = 'hidden';
    timeInput.name = 'faktiskais_ilgums';
    timeInput.value = faktiskais_ilgums;

    const commentInput = document.createElement('input');
    commentInput.type = 'hidden';
    commentInput.name = 'komentars';
    commentInput.value = komentars;

    form.appendChild(actionInput);
    form.appendChild(taskInput);
    form.appendChild(timeInput);
    form.appendChild(commentInput);

    document.body.appendChild(form);
    form.submit();
}


// Modālo logu atvēršana un aizvēršana
function openModal(modalId) {
    document.getElementById(modalId).style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Aizvērt modālo logu, ja lietotājs noklikšķina ārpus tā
window.onclick = function(event) {
    const modal = document.getElementById('completeTaskModal');
    if (event.target == modal) {
        closeModal('completeTaskModal');
    }
}

// Vecā changeTaskStatus funkcija - tiek atstāta saderības dēļ, bet nu tā nedara neko
function changeTaskStatus(taskId, newStatus) {
    console.log('Izmantojiet startTaskWork() vai completeTaskWork() funkcijas');
}
</script>

<style>
/* Pievienojam stilu modālajam logam */
.modal {
    display: none; /* Noklusējuma slēpts */
    position: fixed; /* Fiksēta pozīcija */
    z-index: 1; /* Virs visa cita */
    left: 0;
    top: 0;
    width: 100%; /* Pilns platums */
    height: 100%; /* Pilns augstums */
    overflow: auto; /* Iespējot ritināšanu, ja nepieciešams */
    background-color: rgb(0,0,0); /* Fallback krāsa */
    background-color: rgba(0,0,0,0.4); /* Melns ar caurspīdīgumu */
}

.modal-content {
    background-color: #fefefe;
    margin: 15% auto; /* 15% no augšas un automātiski centrēts */
    padding: 20px;
    border: 1px solid #888;
    width: 80%; /* Platums */
    max-width: 600px; /* Maksimālais platums */
    border-radius: 8px;
    position: relative;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #e0e0e0;
    padding-bottom: 10px;
    margin-bottom: 20px;
}

.modal-title {
    margin: 0;
    font-size: 1.25em;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5em;
    cursor: pointer;
    color: #aaa;
}

.modal-close:hover {
    color: #777;
}

.modal-body {
    margin-bottom: 20px;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    border-top: 1px solid #e0e0e0;
    padding-top: 10px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
}

.form-group input[type="number"],
.form-group textarea {
    width: calc(100% - 22px); /* Korekcija padding un border dēļ */
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 4px;
}

.mt-3 {
    margin-top: 1rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: var(--spacing-lg);
    margin-bottom: var(--spacing-lg);
}

.stat-card {
    background-color: white;
    padding: var(--spacing-md);
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    border-left: 5px solid var(--primary-color);
    text-align: center;
}

.stat-number {
    font-size: 2em;
    font-weight: bold;
    color: var(--text-color);
}

.stat-label {
    font-size: 0.9em;
    color: var(--text-muted);
    margin-top: 5px;
}

/* Grupas ikonas stils */
.group-work-icon {
    margin-right: 5px;
    font-size: 0.9em;
    vertical-align: middle;
}

.row {
    display: flex;
    flex-wrap: wrap;
    gap: var(--spacing-lg);
}

.col-md-6 {
    flex: 1;
    min-width: 300px;
}

.col-md-12 {
    flex: 1;
    width: 100%;
}

.badge {
    display: inline-block;
    padding: 2px 6px;
    font-size: 11px;
    color: white;
    border-radius: 3px;
    margin-left: 5px;
}

.badge-danger {
    background: var(--danger-color);
}

.badge-warning {
    background: var(--warning-color);
}

.table-danger {
    background-color: rgba(220, 53, 69, 0.1);
}

/* Mazāks fonts mehāniķu vārdiem un lielāks platums */
.table td:nth-child(2) {
    font-size: 0.85em;
    color: #666;
    min-width: 180px;
    max-width: 220px;
    word-wrap: break-word;
}

/* Stils pogām modālajā logā */
.modal-footer .btn {
    margin-left: 10px;
}

/* Stils pogai "Pabeigts" grupu darbam */
.btn.disabled {
    opacity: 1;
    color: #6c757d;
    background-color: #e9ecef;
    border-color: #dee2e6;
}

@media (max-width: 768px) {
    .row {
        flex-direction: column;
    }
    .modal-content {
        width: 90%;
        margin: 20% auto;
    }
}
</style>

<?php include 'includes/footer.php'; ?>