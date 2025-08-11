<?php
require_once 'config.php';

// Pārbaudīt atļaujas
requireRole(ROLE_OPERATOR);

$pageTitle = 'Ziņot problēmu';
$pageHeader = 'Jauna problēma';

$currentUser = getCurrentUser();
$errors = [];
$success = false;

// Iegūt operatora noklusētās vērtības
$default_vietas_id = null;
$default_iekartas_id = null;
if ($currentUser && $currentUser['loma'] === 'Operators') {
    $default_vietas_id = $currentUser['nokluseta_vietas_id'];
    $default_iekartas_id = $currentUser['noklusetas_iekartas_id'];
}

// Iegūt nepieciešamos datus formām
try {
    // Vietas
    $stmt = $pdo->query("SELECT id, nosaukums FROM vietas WHERE aktīvs = 1 ORDER BY nosaukums");
    $vietas = $stmt->fetchAll();

    // Iekārtas
    $stmt = $pdo->query("SELECT id, nosaukums, vietas_id FROM iekartas WHERE aktīvs = 1 ORDER BY nosaukums");
    $iekartas = $stmt->fetchAll();

} catch (PDOException $e) {
    $errors[] = "Kļūda ielādējot datus: " . $e->getMessage();
}

// Apstrādāt formas iesniegšanu
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nosaukums = sanitizeInput($_POST['nosaukums'] ?? '');
    $apraksts = sanitizeInput($_POST['apraksts'] ?? '');
    $vietas_id = intval($_POST['vietas_id'] ?? 0);
    $iekartas_id = intval($_POST['iekartas_id'] ?? 0);
    $prioritate = sanitizeInput($_POST['prioritate'] ?? 'Vidēja');
    $sarezgitibas_pakape = sanitizeInput($_POST['sarezgitibas_pakape'] ?? 'Vidēja');
    $aptuvenais_ilgums = floatval($_POST['aptuvenais_ilgums'] ?? 0);

    // Validācija
    if (empty($nosaukums)) {
        $errors[] = "Problēmas nosaukums ir obligāts.";
    }

    if (empty($apraksts)) {
        $errors[] = "Problēmas apraksts ir obligāts.";
    }

    if (!in_array($prioritate, ['Zema', 'Vidēja', 'Augsta', 'Kritiska'])) {
        $errors[] = "Nederīga prioritāte.";
    }

    if (!in_array($sarezgitibas_pakape, ['Vienkārša', 'Vidēja', 'Sarežģīta', 'Ļoti sarežģīta'])) {
        $errors[] = "Nederīga sarežģītības pakāpe.";
    }

    // Ja nav kļūdu, izveidot problēmu
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Izveidot problēmu
            $stmt = $pdo->prepare("
                INSERT INTO problemas 
                (nosaukums, apraksts, vietas_id, iekartas_id, prioritate, sarezgitibas_pakape, 
                 aptuvenais_ilgums, zinotajs_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $nosaukums,
                $apraksts,
                $vietas_id ?: null,
                $iekartas_id ?: null,
                $prioritate,
                $sarezgitibas_pakape,
                $aptuvenais_ilgums ?: null,
                $currentUser['id']
            ]);

            $problemas_id = $pdo->lastInsertId();

            // Apstrādāt failu augšupielādi
            if (!empty($_FILES['faili']['name'][0])) {
                if (!is_dir(UPLOAD_DIR)) {
                    mkdir(UPLOAD_DIR, 0755, true);
                }

                for ($i = 0; $i < count($_FILES['faili']['name']); $i++) {
                    if ($_FILES['faili']['error'][$i] === UPLOAD_ERR_OK) {
                        $file = [
                            'name' => $_FILES['faili']['name'][$i],
                            'type' => $_FILES['faili']['type'][$i],
                            'tmp_name' => $_FILES['faili']['tmp_name'][$i],
                            'error' => $_FILES['faili']['error'][$i],
                            'size' => $_FILES['faili']['size'][$i]
                        ];

                        try {
                            $fileInfo = uploadFile($file);

                            $stmt = $pdo->prepare("
                                INSERT INTO faili 
                                (originalais_nosaukums, saglabatais_nosaukums, faila_cels, faila_tips, faila_izmers, tips, saistitas_id, augšupielādēja_id)
                                VALUES (?, ?, ?, ?, ?, 'Problēma', ?, ?)
                            ");

                            $stmt->execute([
                                $fileInfo['originalais_nosaukums'],
                                $fileInfo['saglabatais_nosaukums'],
                                $fileInfo['faila_cels'],
                                $fileInfo['faila_tips'],
                                $fileInfo['faila_izmers'],
                                $problemas_id,
                                $currentUser['id']
                            ]);
                        } catch (Exception $e) {
                            error_log("Faila augšupielādes kļūda: " . $e->getMessage());
                        }
                    }
                }
            }

            // Izveidot paziņojumus menedžeriem un administratoriem
            $stmt = $pdo->query("
                SELECT id FROM lietotaji 
                WHERE loma IN ('Administrators', 'Menedžeris') 
                AND statuss = 'Aktīvs'
            ");
            $managers = $stmt->fetchAll();

            foreach ($managers as $manager) {
                createNotification(
                    $manager['id'],
                    'Jauna problēma ziņota',
                    "Operators {$currentUser['vards']} {$currentUser['uzvards']} ir ziņojis jaunu problēmu: $nosaukums",
                    'Jauna problēma',
                    'Problēma',
                    $problemas_id
                );
            }

            $pdo->commit();

            // Ja problēma ir kritiska, automātiski pārvērst uzdevumā un piešķirt mehāniķiem
            if ($prioritate === 'Kritiska') {
                // Sagatavot problēmas datus uzdevuma izveidošanai
                $problemData = [
                    'nosaukums' => $nosaukums,
                    'apraksts' => $apraksts,
                    'vietas_id' => $vietas_id,
                    'iekartas_id' => $iekartas_id,
                    'aptuvenais_ilgums' => $aptuvenais_ilgums
                ];

                // Izveidot kritiskos uzdevumus visiem strādājošajiem mehāniķiem
                $createdTasks = createCriticalTaskFromProblem($problemas_id, $problemData);

                if ($createdTasks && is_array($createdTasks)) {
                    // Paziņot menedžeriem un administratoriem par automātiski izveidotajiem uzdevumiem
                    $stmt = $pdo->query("
                        SELECT id FROM lietotaji 
                        WHERE loma IN ('Menedžeris', 'Administrators') 
                        AND statuss = 'Aktīvs'
                    ");
                    $managers = $stmt->fetchAll();

                    foreach ($managers as $manager) {
                        createNotification(
                            $manager['id'],
                            'KRITISKA PROBLĒMA AUTOMĀTISKI PĀRVĒRSTA',
                            "Kritiska problēma '$nosaukums' automātiski pārvērsta uzdevumā un piešķirta " . count($createdTasks) . " mehāniķiem. Ražošana ir apturēta!",
                            'Kritiska problēma',
                            'Problēma',
                            $problemas_id
                        );
                    }
                } else {
                    // Ja neizdevās izveidot uzdevumus, paziņot kā iepriekš
                    $stmt = $pdo->query("
                        SELECT id FROM lietotaji 
                        WHERE loma = 'Mehāniķis' 
                        AND statuss = 'Aktīvs'
                    ");
                    $mechanics = $stmt->fetchAll();

                    foreach ($mechanics as $mechanic) {
                        createNotification(
                            $mechanic['id'],
                            'KRITISKA PROBLĒMA!',
                            "Jauna kritiska problēma: $nosaukums. Ražošana var būt apturēta!",
                            'Kritiska problēma',
                            'Problēma',
                            $problemas_id
                        );
                    }

                    // Paziņot menedžeriem
                    $stmt = $pdo->query("
                        SELECT id FROM lietotaji 
                        WHERE loma IN ('Menedžeris', 'Administrators') 
                        AND statuss = 'Aktīvs'
                    ");
                    $managers = $stmt->fetchAll();

                    foreach ($managers as $manager) {
                        createNotification(
                            $manager['id'],
                            'KRITISKA PROBLĒMA ZIŅOTA!',
                            "Operators ziņoja kritisku problēmu: $nosaukums. UZMANĪBU: Automātiska uzdevumu izveide neizdevās!",
                            'Kritiska problēma',
                            'Problēma',
                            $problemas_id
                        );
                    }
                }
            }

            // Push un Telegram paziņojumi pēc transakcijas pabeigšanas
            try {
                sendProblemPushNotification($problemas_id, $nosaukums);
                sendProblemTelegramNotification($problemas_id, $nosaukums);
            } catch (Exception $e) {
                error_log("Push/Telegram notification error: " . $e->getMessage());
            }

            setFlashMessage('success', 'Problēma veiksmīgi ziņota!');        
            redirect('my_problems.php');

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = "Kļūda ziņojot problēmu: " . $e->getMessage();
            error_log("Problēmas ziņošanas kļūda: " . $e->getMessage());
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = "Sistēmas kļūda: " . $e->getMessage();
            error_log("Sistēmas kļūda: " . $e->getMessage());
        }
    }
}

include 'includes/header.php';
?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3>Problēmas informācija</h3>
        <small class="text-muted">Lūdzu, aprakstiet problēmu pēc iespējas detalizētāk</small>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data" id="problemForm">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

            <div class="row">
                <div class="col-md-8">
                    <div class="form-group">
                        <label for="nosaukums" class="form-label">Problēmas nosaukums *</label>
                        <input 
                            type="text" 
                            id="nosaukums" 
                            name="nosaukums" 
                            class="form-control" 
                            required 
                            maxlength="200"
                            placeholder="Īss problēmas apraksts"
                            value="<?php echo htmlspecialchars($_POST['nosaukums'] ?? ''); ?>"
                        >
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group">
                        <label for="prioritate" class="form-label">Prioritāte *</label>
                        <select id="prioritate" name="prioritate" class="form-control" required>
                            <option value="Zema" <?php echo ($_POST['prioritate'] ?? 'Vidēja') === 'Zema' ? 'selected' : ''; ?>>Zema - var gaidīt</option>
                            <option value="Vidēja" <?php echo ($_POST['prioritate'] ?? 'Vidēja') === 'Vidēja' ? 'selected' : ''; ?>>Vidēja - jārisina šodien</option>
                            <option value="Augsta" <?php echo ($_POST['prioritate'] ?? '') === 'Augsta' ? 'selected' : ''; ?>>Augsta - jārisina steidzami</option>
                            <option value="Kritiska" <?php echo ($_POST['prioritate'] ?? '') === 'Kritiska' ? 'selected' : ''; ?>>Kritiska - apturēta ražošana</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="apraksts" class="form-label">Detalizēts problēmas apraksts *</label>
                <textarea 
                    id="apraksts" 
                    name="apraksts" 
                    class="form-control" 
                    rows="5" 
                    required
                    placeholder="Aprakstiet problēmu, kad tā radās, kādas darbības veicāt pirms tam, kādas ir sekas, u.c."
                ><?php echo htmlspecialchars($_POST['apraksts'] ?? ''); ?></textarea>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="vietas_id" class="form-label">Vieta *</label>
                        <select id="vietas_id" name="vietas_id" class="form-control" required onchange="updateIekartas()">
                            <option value="">Izvēlieties vietu</option>
                            <?php foreach ($vietas as $vieta): ?>
                                <option value="<?php echo $vieta['id']; ?>" 
                                    <?php 
                                    $selected_vieta = $_POST['vietas_id'] ?? $default_vietas_id;
                                    echo $selected_vieta == $vieta['id'] ? 'selected' : ''; 
                                    ?>>
                                    <?php echo htmlspecialchars($vieta['nosaukums']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <label for="iekartas_id" class="form-label">Iekārta</label>
                        <div class="searchable-select-container">
                            <input 
                                type="text" 
                                id="iekartas_search" 
                                class="form-control searchable-input" 
                                placeholder="Meklēt iekārtu vai izvēlieties no saraksta..."
                                autocomplete="off"
                            >
                            <select id="iekartas_id" name="iekartas_id" class="form-control searchable-select">
                                <option value="">Izvēlieties iekārtu</option>
                                <?php foreach ($iekartas as $iekarta): ?>
                                    <option value="<?php echo $iekarta['id']; ?>" 
                                        data-vieta="<?php echo $iekarta['vietas_id']; ?>"
                                        data-name="<?php echo htmlspecialchars(strtolower(trim($iekarta['nosaukums']))); ?>"
                                        <?php 
                                        $selected_iekarta = $_POST['iekartas_id'] ?? $default_iekartas_id;
                                        echo $selected_iekarta == $iekarta['id'] ? 'selected' : ''; 
                                        ?>>
                                        <?php echo htmlspecialchars(trim($iekarta['nosaukums'])); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <small class="form-text text-muted">Izvēlieties konkrēto iekārtu, ja problēma saistīta ar to</small>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="sarezgitibas_pakape" class="form-label">Sarežģītības pakāpe</label>
                        <select id="sarezgitibas_pakape" name="sarezgitibas_pakape" class="form-control">
                            <option value="Vienkārša" <?php echo ($_POST['sarezgitibas_pakape'] ?? 'Vidēja') === 'Vienkārša' ? 'selected' : ''; ?>>Vienkārša - mazs remonts</option>
                            <option value="Vidēja" <?php echo ($_POST['sarezgitibas_pakape'] ?? 'Vidēja') === 'Vidēja' ? 'selected' : ''; ?>>Vidēja - standarta remonts</option>
                            <option value="Sarežģīta" <?php echo ($_POST['sarezgitibas_pakape'] ?? '') === 'Sarežģīta' ? 'selected' : ''; ?>>Sarežģīta - nepieciešams speciālists</option>
                            <option value="Ļoti sarežģīta" <?php echo ($_POST['sarezgitibas_pakape'] ?? '') === 'Ļoti sarežģīta' ? 'selected' : ''; ?>>Ļoti sarežģīta - ārējais serviss</option>
                        </select>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <label for="aptuvenais_ilgums" class="form-label">Aptuvenais atrisināšanas ilgums (stundas)</label>
                        <input 
                            type="number" 
                            id="aptuvenais_ilgums" 
                            name="aptuvenais_ilgums" 
                            class="form-control" 
                            step="0.5" 
                            min="0" 
                            max="1000"
                            placeholder="Jūsu novērtējums"
                            value="<?php echo $_POST['aptuvenais_ilgums'] ?? ''; ?>"
                        >
                        <small class="form-text text-muted">Jūsu novērtējums, cik ilgi varētu aizņemt problēmas risināšana</small>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="faili" class="form-label">Pievienot attēlus</label>
                <input 
                    type="file" 
                    id="faili" 
                    name="faili[]" 
                    class="form-control" 
                    multiple 
                    accept=".jpg,.jpeg,.png,.gif"
                >
                <small class="form-text text-muted">
                    Pievienojiet fotoattēlus, kas parāda problēmu. Atļautie formāti: JPG, PNG, GIF. Maksimālais izmērs: <?php echo round(MAX_FILE_SIZE / 1024 / 1024); ?>MB
                </small>
            </div>

            <div class="alert alert-info">
                <strong>Padoms:</strong> Jo detalizētāku informāciju sniegsiet, jo ātrāk un efektīvāk varēs atrisināt problēmu. 
                Iekļaujiet informāciju par:
                <ul class="mb-0 mt-2">
                    <li>Kad problēma parādījās pirmoreiz</li>
                    <li>Kādas darbības veicāt pirms problēmas rašanās</li>
                    <li>Vai ir kļūdu ziņojumi vai brīdinājumi</li>
                    <li>Vai problēma atkārtojas regulāri</li>
                    <li>Kāda ir problēmas ietekme uz darbu</li>
                </ul>
            </div>

            <div class="form-group">
                <div class="d-flex justify-content-between">
                    <div>
                        <button type="submit" class="btn btn-danger">Ziņot problēmu</button>
                        <a href="my_problems.php" class="btn btn-secondary">Atcelt</a>
                    </div>
                    <small class="text-muted align-self-center">
                        * - obligātie lauki
                    </small>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Palīdzības sadaļa -->
<div class="card mt-4">
    <div class="card-header">
        <h4>📞 Ārkārtas situācijā</h4>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <div class="emergency-contact">
                    <strong>🚨 Kritiska situācija</strong>
                    <p>Ja radusies kritiska situācija, kas apdraud drošību vai apturējusi ražošanu:</p>
                    <p><strong>Zvaniet: +371 1234-5678</strong></p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="emergency-contact">
                    <strong>🔧 Tehniskā palīdzība</strong>
                    <p>Tehniskās palīdzības dienests:</p>
                    <p><strong>Iekšējais: 123</strong></p>
                    <p><strong>Mobilais: +371 2345-6789</strong></p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="emergency-contact">
                    <strong>👷 Darba drošība</strong>
                    <p>Darba drošības speciālists:</p>
                    <p><strong>Iekšējais: 456</strong></p>
                    <p><strong>E-pasts: safety@avoti.lv</strong></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Iekartu filtrēšana pēc vietas
function updateIekartas() {
    const vietasSelect = document.getElementById('vietas_id');
    const iekartasSelect = document.getElementById('iekartas_id');
    const iekartasSearch = document.getElementById('iekartas_search');
    const selectedVieta = vietasSelect.value;

    // Rādīt visas opcijas
    Array.from(iekartasSelect.options).forEach(option => {
        if (option.value === '') {
            option.style.display = 'block';
            return;
        }

        const iekartaVieta = option.getAttribute('data-vieta');
        if (!selectedVieta || iekartaVieta === selectedVieta) {
            option.style.display = 'block';
        } else {
            option.style.display = 'none';
        }
    });

    // Atiestatīt izvēli, ja pašreizējā iekārta nepieder izvēlētajai vietai
    if (selectedVieta && iekartasSelect.value) {
        const selectedOption = iekartasSelect.options[iekartasSelect.selectedIndex];
        const selectedIekartaVieta = selectedOption.getAttribute('data-vieta');
        if (selectedIekartaVieta !== selectedVieta) {
            iekartasSelect.value = '';
            iekartasSearch.value = '';
        }
    }

    // Atjaunot meklēšanas filtrāciju
    filterIekartas();
}

// Iekārtu meklēšanas funkcionalitāte
function filterIekartas() {
    const searchInput = document.getElementById('iekartas_search');
    const iekartasSelect = document.getElementById('iekartas_id');
    const vietasSelect = document.getElementById('vietas_id');
    const searchText = searchInput.value.toLowerCase();
    const selectedVieta = vietasSelect.value;

    let hasVisibleOptions = false;

    Array.from(iekartasSelect.options).forEach(option => {
        if (option.value === '') {
            option.style.display = 'block';
            return;
        }

        const iekartaName = option.getAttribute('data-name') || '';
        const iekartaVieta = option.getAttribute('data-vieta');

        // Pārbaudīt vai atbilst vietas filtram
        const vietaMatch = !selectedVieta || iekartaVieta === selectedVieta;

        // Pārbaudīt vai atbilst meklēšanas tekstam
        const searchMatch = !searchText || iekartaName.includes(searchText);

        if (vietaMatch && searchMatch) {
            option.style.display = 'block';
            hasVisibleOptions = true;
        } else {
            option.style.display = 'none';
        }
    });

    // Ja nav redzamu opciju, rādīt ziņojumu
    if (!hasVisibleOptions && searchText) {
        // Pievienot pagaidu opciju ar ziņojumu
        const noResultsOption = iekartasSelect.querySelector('.no-results');
        if (!noResultsOption) {
            const option = document.createElement('option');
            option.className = 'no-results';
            option.disabled = true;
            option.textContent = 'Nav atrasta iekārta ar šādu nosaukumu';
            iekartasSelect.appendChild(option);
        }
        iekartasSelect.querySelector('.no-results').style.display = 'block';
    } else {
        // Noņemt "nav rezultātu" opciju
        const noResultsOption = iekartasSelect.querySelector('.no-results');
        if (noResultsOption) {
            noResultsOption.style.display = 'none';
        }
    }
}

// Inicializēt iekartu meklēšanu
function initSearchableSelect() {
    const searchInput = document.getElementById('iekartas_search');
    const iekartasSelect = document.getElementById('iekartas_id');

    if (!searchInput || !iekartasSelect) return;

    // Meklēšanas ievades notikums
    searchInput.addEventListener('input', function() {
        filterIekartas();

        // Ja ir tikai viena redzama opcija (bez tukšās), automātiski izvēlēties to
        const visibleOptions = Array.from(iekartasSelect.options).filter(option => 
            option.style.display !== 'none' && option.value !== ''
        );

        if (visibleOptions.length === 1) {
            iekartasSelect.value = visibleOptions[0].value;
        }
    });

    // Kad izvēlas no select, atjaunot meklēšanas lauku
    iekartasSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption.value) {
            searchInput.value = selectedOption.textContent.trim();
        } else {
            searchInput.value = '';
        }
    });

    // Kad fokusē meklēšanas lauku, parādīt visas opcijas
    searchInput.addEventListener('focus', function() {
        iekartasSelect.classList.add('show');
        iekartasSelect.size = Math.min(8, iekartasSelect.options.length);
        filterIekartas(); // Atjaunot filtrāciju
    });

    // Kad zaudē fokusu no meklēšanas lauka, paslēpt opcijas (ar aizkavi)
    searchInput.addEventListener('blur', function(e) {
        setTimeout(() => {
            // Pārbaudīt vai fokuss nav pārslēgts uz select elementu
            if (document.activeElement !== iekartasSelect) {
                iekartasSelect.classList.remove('show');
                iekartasSelect.size = 1;
            }
        }, 150);
    });

    // Kad fokusē select, neslēpt to
    iekartasSelect.addEventListener('focus', function() {
        iekartasSelect.classList.add('show');
    });

    // Kad zaudē fokusu no select, paslēpt opcijas
    iekartasSelect.addEventListener('blur', function() {
        setTimeout(() => {
            if (document.activeElement !== searchInput) {
                iekartasSelect.classList.remove('show');
                iekartasSelect.size = 1;
            }
        }, 150);
    });

    // Peles klikšķis uz select opcijas
    iekartasSelect.addEventListener('click', function(e) {
        if (this.value) {
            const selectedOption = this.options[this.selectedIndex];
            searchInput.value = selectedOption.textContent.trim();
            iekartasSelect.classList.remove('show');
            iekartasSelect.size = 1;
        }
    });

    // Klaviatūras navigācija
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            iekartasSelect.focus();
            if (iekartasSelect.selectedIndex < iekartasSelect.options.length - 1) {
                iekartasSelect.selectedIndex++;
            }
        } else if (e.key === 'Enter') {
            e.preventDefault();
            const visibleOptions = Array.from(iekartasSelect.options).filter(option => 
                option.style.display !== 'none' && option.value !== ''
            );
            if (visibleOptions.length === 1) {
                iekartasSelect.value = visibleOptions[0].value;
                searchInput.value = visibleOptions[0].textContent.trim();
            }
        }
    });
}

// Formas validācija
document.getElementById('problemForm').addEventListener('submit', function(e) {
    const nosaukums = document.getElementById('nosaukums').value.trim();
    const apraksts = document.getElementById('apraksts').value.trim();
    const vieta = document.getElementById('vietas_id').value;

    if (!nosaukums || !apraksts || !vieta) {
        e.preventDefault();
        alert('Lūdzu aizpildiet visus obligātos laukus (ar * atzīmētos).');
        return;
    }

    if (apraksts.length < 10) {
        e.preventDefault();
        alert('Lūdzu ievadiet detalizētāku problēmas aprakstu (vismaz 10 rakstzīmes).');
        return;
    }

    // Pārbaudīt failu izmēru
    const fileInput = document.getElementById('faili');
    if (fileInput.files.length > 0) {
        for (let i = 0; i < fileInput.files.length; i++) {
            if (fileInput.files[i].size > <?php echo MAX_FILE_SIZE; ?>) {
                e.preventDefault();
                alert('Attēls "' + fileInput.files[i].name + '" ir pārāk liels. Maksimālais izmērs: <?php echo round(MAX_FILE_SIZE / 1024 / 1024); ?>MB');
                return;
            }
        }
    }
});

// Inicializēt iekartu filtrēšanu
document.addEventListener('DOMContentLoaded', function() {
    updateIekartas();
    initSearchableSelect();

    // Uzstādīt noklusēto iekārtas nosaukumu meklēšanas laukā
    const iekartasSelect = document.getElementById('iekartas_id');
    const searchInput = document.getElementById('iekartas_search');
    if (iekartasSelect.value && searchInput) {
        const selectedOption = iekartasSelect.options[iekartasSelect.selectedIndex];
        if (selectedOption.value) {
            searchInput.value = selectedOption.textContent;
        }
    }

    // Prioritātes maiņas brīdinājums
    document.getElementById('prioritate').addEventListener('change', function() {
        if (this.value === 'Kritiska') {
            if (!confirm('⚠️ UZMANĪBU!\n\nVai tiešām runa ir par kritisku problēmu, kas APTURĒJUSI ražošanu?\n\n• Automātiski tiks izveidots uzdevums VISIEM pašlaik strādājošajiem mehāniķiem\n• Problēma tiks atzīmēta kā "Pārvērsta uzdevumā"\n• Tiklīdz kāds mehāniķis sāks darbu, uzdevums tiks noņemts pārējiem\n\nVai turpināt?')) {
                this.value = 'Augsta';
            } else {
                // Papildu apstiprinājums
                if (!confirm('Vai esat pilnīgi pārliecināts, ka ražošana ir apturēta un nepieciešama tūlītēja iejaukšanās?')) {
                    this.value = 'Augsta';
                }
            }
        }
    });
});

// Teksta ievades palīdzība
document.getElementById('apraksts').addEventListener('input', function() {
    const length = this.value.length;
    const minLength = 10;

    if (length < minLength) {
        this.style.borderColor = '#e74c3c';
    } else if (length < 50) {
        this.style.borderColor = '#f39c12';
    } else {
        this.style.borderColor = '#27ae60';
    }
});
</script>

<style>
.emergency-contact {
    text-align: center;
    padding: var(--spacing-md);
    background: var(--gray-100);
    border-radius: var(--border-radius);
    margin-bottom: var(--spacing-md);
}

.emergency-contact strong {
    color: var(--danger-color);
    font-size: 1.1rem;
    display: block;
    margin-bottom: var(--spacing-sm);
}

.emergency-contact p {
    margin-bottom: var(--spacing-xs);
    color: var(--gray-700);
}

.row {
    display: flex;
    flex-wrap: wrap;
    gap: var(--spacing-md);
    margin-bottom: var(--spacing-md);
}

.col-md-4 {
    flex: 1;
    min-width: 200px;
}

.col-md-6 {
    flex: 1;
    min-width: 250px;
}

.col-md-8 {
    flex: 2;
    min-width: 300px;
}

@media (max-width: 768px) {
    .row {
        flex-direction: column;
    }

    .col-md-4,
    .col-md-6,
    .col-md-8 {
        width: 100%;
        flex: none;
    }

    .emergency-contact {
        margin-bottom: var(--spacing-sm);
    }
}

/* Formas uzlabojumi */
#apraksts {
    transition: border-color 0.3s ease;
}

.form-text {
    font-style: italic;
}

/* Meklējamā select stila */
.searchable-select-container {
    position: relative;
}

.searchable-input {
    position: relative;
    z-index: 2;
}

.searchable-select {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    z-index: 1000;
    max-height: 200px;
    overflow-y: auto;
    background: white;
    border: 1px solid #ddd;
    border-top: none;
    display: none;
    cursor: pointer;
}

.searchable-select.show {
    display: block;
}

.searchable-input:focus + .searchable-select {
    display: block;
}

.searchable-select option {
    padding: 8px 12px;
    text-align: left;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.searchable-select option:hover {
    background-color: #f5f5f5;
}

/* Responsīvais dizains mobilajām ierīcēm */
@media (max-width: 480px) {
    .card-body {
        padding: var(--spacing-md);
    }

    .emergency-contact {
        padding: var(--spacing-sm);
    }

    .emergency-contact strong {
        font-size: 1rem;
    }

    .searchable-select {
        max-height: 150px;
    }
}
</style>

<?php include 'includes/footer.php'; ?>