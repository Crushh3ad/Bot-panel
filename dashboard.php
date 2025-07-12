<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Überprüfe Login
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$db = new SQLite3('bots.db');
// Tabelle für Bot-Sharing/Team-Bots anlegen (falls noch nicht vorhanden)
$db->exec('CREATE TABLE IF NOT EXISTS bot_users (
    bot_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    PRIMARY KEY (bot_id, user_id),
    FOREIGN KEY (bot_id) REFERENCES bots(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
)');

// Bot hinzufügen
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'add_bot') {
    $name = $_POST['name'];
    $token = $_POST['token'];
    $category = $_POST['category'];
    // Prüfe Datei-Upload
    if (!isset($_FILES['botfiles']) || empty($_FILES['botfiles']['name'][0])) {
        $add_error = 'Mindestens eine Datei muss hochgeladen werden!';
    } else {
        $stmt = $db->prepare('INSERT INTO bots (user_id, name, token, category) VALUES (:user_id, :name, :token, :category)');
        $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $stmt->bindValue(':token', $token, SQLITE3_TEXT);
        $stmt->bindValue(':category', $category, SQLITE3_TEXT);
        $stmt->execute();
        $bot_id = $db->lastInsertRowID();
        $bot_dir = __DIR__ . "/bots/" . $bot_id . "/";
        if (!is_dir($bot_dir)) mkdir($bot_dir, 0775, true);
        // Dateien speichern
        foreach ($_FILES['botfiles']['tmp_name'] as $i => $tmp_name) {
            $filename = basename($_FILES['botfiles']['name'][$i]);
            move_uploaded_file($tmp_name, $bot_dir . $filename);
        }
        header('Location: dashboard.php');
        exit;
    }
}

// Bot löschen
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'delete_bot') {
    $bot_id = $_POST['bot_id'];
    $stmt = $db->prepare('DELETE FROM bots WHERE id = :bot_id AND user_id = :user_id');
    $stmt->bindValue(':bot_id', $bot_id, SQLITE3_INTEGER);
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $stmt->execute();
    
    header('Location: dashboard.php');
    exit;
}

// Bot Status ändern
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'toggle_bot') {
    $bot_id = $_POST['bot_id'];
    $new_status = $_POST['new_status'];

    // Hole Bot-Infos
    $stmt = $db->prepare('SELECT * FROM bots WHERE id = :bot_id AND user_id = :user_id');
    $stmt->bindValue(':bot_id', $bot_id, SQLITE3_INTEGER);
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $bot = $result->fetchArray(SQLITE3_ASSOC);
    $bot_dir = __DIR__ . "/bots/" . $bot_id . "/";
    $pid = $bot['pid'] ?? null;
    $main_file = '';
    $language = '';
    // Hauptdatei & Sprache erkennen
    if (is_dir($bot_dir)) {
        $files = scandir($bot_dir);
        foreach ($files as $f) {
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            if (in_array($ext, ['py','js','sh'])) {
                if (in_array($f, ['bot.py','main.py','index.js','app.js','start.sh'])) {
                    $main_file = $f;
                    if ($ext === 'py') $language = 'python';
                    if ($ext === 'js') $language = 'node';
                    if ($ext === 'sh') $language = 'bash';
                    break;
                }
            }
        }
    }
    $logfile = $bot_dir . 'output.log';
    if ($new_status == 'running' && $main_file && $language) {
        // Automatische Abhängigkeits-Installation
        $install_output = "";
        if ($language == 'python') {
            $main_path = $bot_dir . $main_file;
            $requirements_path = $bot_dir . 'requirements.txt';
            // Wenn requirements.txt existiert, installiere alles daraus
            if (file_exists($requirements_path)) {
                $cmd = "pip3 install --break-system-packages -r '" . $requirements_path . "' 2>&1";
                $install_output .= shell_exec($cmd);
            }
            $imports = [];
            if (file_exists($main_path)) {
                $lines = file($main_path);
                foreach ($lines as $line) {
                    if (preg_match('/^import +([a-zA-Z0-9_]+)/', $line, $m)) {
                        $imports[] = $m[1];
                    }
                    if (preg_match('/^from +([a-zA-Z0-9_]+)/', $line, $m)) {
                        $imports[] = $m[1];
                    }
                }
            }
            $imports = array_unique($imports);
            // Mapping für bekannte Module
            $mapping = [
                'discord' => 'discord.py',
                'PIL' => 'pillow',
                'cv2' => 'opencv-python',
                'yaml' => 'pyyaml',
                'Crypto' => 'pycryptodome',
                'skimage' => 'scikit-image',
                'sklearn' => 'scikit-learn',
                'Image' => 'pillow',
                'bs4' => 'beautifulsoup4',
                'lxml' => 'lxml',
                'matplotlib' => 'matplotlib',
                'numpy' => 'numpy',
                'pandas' => 'pandas',
                'requests' => 'requests',
                'flask' => 'flask',
                'selenium' => 'selenium',
                'tqdm' => 'tqdm',
                'torch' => 'torch',
                'cv' => 'opencv-python',
                'openai' => 'openai',
                'dotenv' => 'python-dotenv',
                'psutil' => 'psutil',
                'pyyaml' => 'pyyaml',
                'websockets' => 'websockets',
                'aiohttp' => 'aiohttp',
                'asyncio' => 'asyncio',
                'jinja2' => 'jinja2',
                'sqlalchemy' => 'sqlalchemy',
                'pytest' => 'pytest',
                'pytest_asyncio' => 'pytest-asyncio',
            ];
            $pip_packages = [];
            foreach ($imports as $imp) {
                // Verwende Mapping wenn verfügbar, sonst den Original-Namen
                $pip_packages[] = $mapping[$imp] ?? $imp;
            }
            if (!empty($pip_packages)) {
                $cmd = "pip3 install --break-system-packages " . implode(' ', array_map('escapeshellarg', $pip_packages)) . " 2>&1";
                $install_output .= shell_exec($cmd);
                
                // Prüfe alle Imports und versuche unbekannte Module zu installieren
                foreach ($imports as $imp) {
                    $check = shell_exec("python3 -c 'import $imp' 2>&1");
                    if (strpos($check, 'ModuleNotFoundError') !== false) {
                        // Versuche das unbekannte Modul direkt zu installieren
                        $install_cmd = "pip3 install --break-system-packages " . escapeshellarg($imp) . " 2>&1";
                        $install_result = shell_exec($install_cmd);
                        $install_output .= "[Installation von '$imp']\n" . $install_result . "\n";
                        
                        // Prüfe nochmal nach der Installation
                        $check_after = shell_exec("python3 -c 'import $imp' 2>&1");
                        if (strpos($check_after, 'ModuleNotFoundError') !== false) {
                            $install_output .= "[Fehler] Modul '$imp' konnte nicht installiert werden!\n";
                        } else {
                            $install_output .= "[Erfolg] Modul '$imp' erfolgreich installiert!\n";
                        }
                    } else {
                        $install_output .= "[OK] Modul '$imp' bereits verfügbar\n";
                    }
                }
            }
        } elseif ($language == 'node') {
            $main_path = $bot_dir . $main_file;
            $requires = [];
            if (file_exists($main_path)) {
                $lines = file($main_path);
                foreach ($lines as $line) {
                    if (preg_match("/require\(['\"]([a-zA-Z0-9_\-]+)['\"]\)/", $line, $m)) {
                        $requires[] = $m[1];
                    }
                }
            }
            $requires = array_unique($requires);
            if (!empty($requires)) {
                $cmd = "npm install " . implode(' ', array_map('escapeshellarg', $requires)) . " 2>&1";
                $install_output .= shell_exec($cmd);
            }
        }
        // Installation-Log ins Logfile schreiben
        if ($install_output) {
            file_put_contents($logfile, "[Abhängigkeits-Installation]\n" . $install_output . "\n", FILE_APPEND);
        }
        // Starte echten Prozess
        if ($language == 'python') {
            $cmd = "nohup setsid python3 '$bot_dir$main_file' > '$logfile' 2>&1 & echo $!";
        } elseif ($language == 'node') {
            $cmd = "nohup setsid node '$bot_dir$main_file' > '$logfile' 2>&1 & echo $!";
        } elseif ($language == 'bash') {
            $cmd = "nohup setsid bash '$bot_dir$main_file' > '$logfile' 2>&1 & echo $!";
        } else {
            $cmd = '';
        }
        if ($cmd) {
            $output = shell_exec($cmd);
            $pid = intval(trim($output));
            $stmt = $db->prepare('UPDATE bots SET status = :status, pid = :pid WHERE id = :bot_id AND user_id = :user_id');
            $stmt->bindValue(':status', $new_status, SQLITE3_TEXT);
            $stmt->bindValue(':pid', $pid, SQLITE3_INTEGER);
            $stmt->bindValue(':bot_id', $bot_id, SQLITE3_INTEGER);
            $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
            $stmt->execute();
        }
    } elseif ($new_status == 'stopped' && $pid) {
        // Stoppe Prozessgruppe
        shell_exec('kill -TERM -' . intval($pid));
        $stmt = $db->prepare('UPDATE bots SET status = :status, pid = NULL WHERE id = :bot_id AND user_id = :user_id');
        $stmt->bindValue(':status', $new_status, SQLITE3_TEXT);
        $stmt->bindValue(':bot_id', $bot_id, SQLITE3_INTEGER);
        $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
        $stmt->execute();
    } else {
        // Nur Status ändern (Fallback)
        $stmt = $db->prepare('UPDATE bots SET status = :status WHERE id = :bot_id AND user_id = :user_id');
        $stmt->bindValue(':status', $new_status, SQLITE3_TEXT);
        $stmt->bindValue(':bot_id', $bot_id, SQLITE3_INTEGER);
        $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
        $stmt->execute();
    }
    header('Location: dashboard.php');
    exit;
}

// Bot starten
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'start_bot') {
    $bot_id = $_POST['bot_id'];
    // Hole Bot-Infos
    $stmt = $db->prepare('SELECT * FROM bots WHERE id = :bot_id AND user_id = :user_id');
    $stmt->bindValue(':bot_id', $bot_id, SQLITE3_INTEGER);
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $bot = $result->fetchArray(SQLITE3_ASSOC);
    $bot_dir = __DIR__ . "/bots/" . $bot_id . "/";
    $main_file = '';
    $language = '';
    if (is_dir($bot_dir)) {
        $files = scandir($bot_dir);
        foreach ($files as $f) {
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            if (in_array($ext, ['py','js','sh'])) {
                if (in_array($f, ['bot.py','main.py','index.js','app.js','start.sh'])) {
                    $main_file = $f;
                    if ($ext === 'py') $language = 'python';
                    if ($ext === 'js') $language = 'node';
                    if ($ext === 'sh') $language = 'bash';
                    break;
                }
            }
        }
    }
    $logfile = $bot_dir . 'output.log';
    if ($main_file && $language) {
        // Starte echten Prozess (wie toggle_bot)
        if ($language == 'python') {
            $cmd = "nohup setsid python3 '$bot_dir$main_file' > '$logfile' 2>&1 & echo $!";
        } elseif ($language == 'node') {
            $cmd = "nohup setsid node '$bot_dir$main_file' > '$logfile' 2>&1 & echo $!";
        } elseif ($language == 'bash') {
            $cmd = "nohup setsid bash '$bot_dir$main_file' > '$logfile' 2>&1 & echo $!";
        } else {
            $cmd = '';
        }
        if ($cmd) {
            $output = shell_exec($cmd);
            $pid = intval(trim($output));
            $stmt = $db->prepare('UPDATE bots SET status = :status, pid = :pid WHERE id = :bot_id AND user_id = :user_id');
            $stmt->bindValue(':status', 'running', SQLITE3_TEXT);
            $stmt->bindValue(':pid', $pid, SQLITE3_INTEGER);
            $stmt->bindValue(':bot_id', $bot_id, SQLITE3_INTEGER);
            $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
            $stmt->execute();
        }
    }
    header('Location: dashboard.php');
    exit;
}
// Bot stoppen
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'stop_bot') {
    $bot_id = $_POST['bot_id'];
    // Hole Bot-Infos
    $stmt = $db->prepare('SELECT * FROM bots WHERE id = :bot_id AND user_id = :user_id');
    $stmt->bindValue(':bot_id', $bot_id, SQLITE3_INTEGER);
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $bot = $result->fetchArray(SQLITE3_ASSOC);
    $pid = $bot['pid'] ?? null;
    if ($pid) {
        shell_exec('kill -TERM -' . intval($pid));
        $stmt = $db->prepare('UPDATE bots SET status = :status, pid = NULL WHERE id = :bot_id AND user_id = :user_id');
        $stmt->bindValue(':status', 'stopped', SQLITE3_TEXT);
        $stmt->bindValue(':bot_id', $bot_id, SQLITE3_INTEGER);
        $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
        $stmt->execute();
    }
    header('Location: dashboard.php');
    exit;
}

// Bots abrufen (Besitzer oder Team-Mitglied)
$stmt = $db->prepare('SELECT DISTINCT bots.* FROM bots LEFT JOIN bot_users ON bots.id = bot_users.bot_id WHERE bots.user_id = :uid OR bot_users.user_id = :uid ORDER BY bots.created_at DESC');
$stmt->bindValue(':uid', $_SESSION['user_id'], SQLITE3_INTEGER);
$result = $stmt->execute();
$bots = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $bots[] = $row;
}

// Benutzer abrufen (für Team-Verwaltung)
$stmt = $db->prepare('SELECT id, username FROM users WHERE id != :uid ORDER BY username ASC');
$stmt->bindValue(':uid', $_SESSION['user_id'], SQLITE3_INTEGER);
$result = $stmt->execute();
$users = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $users[] = $row;
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Discord Bot Hosting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="style_dark_lila.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid fade-in">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="dashboard-container p-4 shadow-accent" style="backdrop-filter: blur(14px); box-shadow: 0 8px 40px #A259FF33, 0 1.5px 8px #5D50FE22; border-radius: 28px; border: none;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-0 text-accent" style="letter-spacing:1px;"><i class="fab fa-discord"></i> Discord Bot Hosting</h2>
                            <p class="text-muted mb-0">Willkommen, <?php echo htmlspecialchars($_SESSION['username']); ?>!</p>
                        </div>
                        <div>
                            <?php if ($_SESSION['is_admin']): ?>
                                <a href="admin.php" class="btn btn-outline-warning btn-custom me-2"> <i class="fas fa-shield-alt"></i> Admin Panel </a>
                            <?php endif; ?>
                            <a href="user_panel.php" class="btn btn-outline-info btn-custom me-2"> <i class="fas fa-user-cog"></i> User Panel </a>
                            <a href="logout.php" class="btn btn-outline-danger btn-custom"> <i class="fas fa-sign-out-alt"></i> Abmelden </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mb-4">
            <div class="col-12 text-end">
                <button class="btn btn-primary btn-lg" data-bs-toggle="collapse" data-bs-target="#addBotForm" aria-expanded="false" aria-controls="addBotForm">
                    <i class="fas fa-plus"></i> Bot hinzufügen
                </button>
            </div>
        </div>
        <div class="row mb-4">
            <div class="col-12">
                <div class="collapse" id="addBotForm">
                    <div class="dashboard-container p-4 shadow-accent fade-in" style="backdrop-filter: blur(10px); border-radius: 22px; border: none; box-shadow: 0 4px 24px #A259FF22;">
                        <h4 class="mb-3 text-accent"><i class="fas fa-plus-circle"></i> Neuen Bot anlegen</h4>
                        <form method="POST" enctype="multipart/form-data" class="row g-3">
                            <input type="hidden" name="action" value="add_bot">
                            <div class="col-md-4">
                                <label class="form-label">Bot Name</label>
                                <input type="text" class="form-control form-control-lg" name="name" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Discord Token</label>
                                <input type="password" class="form-control form-control-lg" name="token" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Kategorie</label>
                                <select class="form-select form-select-lg" name="category" required>
                                    <option value="">Kategorie wählen</option>
                                    <option value="Moderation">Moderation</option>
                                    <option value="Music">Music</option>
                                    <option value="Fun">Fun</option>
                                    <option value="Utility">Utility</option>
                                    <option value="Games">Games</option>
                                    <option value="Custom">Custom</option>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Bot-Dateien hochladen <span class="text-danger">*</span></label>
                                <input type="file" name="botfiles[]" class="form-control" multiple required>
                                <small class="text-muted">Mindestens eine Datei, z.B. main.py, index.js, requirements.txt, ...</small>
                            </div>
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-primary btn-lg w-100">
                                    <i class="fas fa-plus"></i> Bot anlegen
                                </button>
                            </div>
                            <?php if (isset($add_error)): ?>
                                <div class="col-12"><div class="alert alert-danger fade-in"><?php echo $add_error; ?></div></div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <!-- Dashboard Cards/Widgets -->
        <div class="row g-4">
            <?php foreach ($bots as $bot): ?>
            <div class="col-md-6 col-lg-4">
                <div class="bot-card shadow-accent fade-in" style="backdrop-filter: blur(10px); border-radius: 22px; border: none; box-shadow: 0 4px 24px #A259FF22; position: relative; overflow: hidden;">
                    <div class="p-4">
                        <div class="d-flex align-items-center mb-3">
                            <span class="category-badge bg-accent me-2"> <?php echo htmlspecialchars($bot['category']); ?> </span>
                            <span class="badge <?php echo $bot['status'] == 'running' ? 'status-running' : 'status-stopped'; ?> ms-2"> <i class="fas fa-circle"></i> <?php echo ucfirst($bot['status']); ?> </span>
                        </div>
                        <h4 class="mb-2 text-accent" style="font-weight:700; letter-spacing:0.5px;"><i class="fab fa-discord"></i> <?php echo htmlspecialchars($bot['name']); ?></h4>
                        <div class="mb-3 text-muted" style="font-size:0.98rem;">Bot-ID: <?php echo $bot['id']; ?></div>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <a href="terminal.php?bot_id=<?php echo $bot['id']; ?>" class="btn btn-accent btn-custom"><i class="fas fa-terminal"></i> Terminal</a>
                            <a href="config.php?bot_id=<?php echo $bot['id']; ?>" class="btn btn-outline-primary btn-custom"><i class="fas fa-cog"></i> Konfiguration</a>
                            <a href="dateimanager.php?bot_id=<?php echo $bot['id']; ?>" class="btn btn-outline-secondary btn-custom"><i class="fas fa-folder-open"></i> Dateien</a>
                            <form method="POST" style="display:inline;margin-left:0.5em;">
                                <input type="hidden" name="bot_id" value="<?php echo $bot['id']; ?>">
                                <?php if ($bot['status'] == 'running'): ?>
                                    <button type="submit" name="action" value="stop_bot" class="btn btn-danger btn-custom"><i class="fas fa-stop"></i> Stoppen</button>
                                <?php else: ?>
                                    <button type="submit" name="action" value="start_bot" class="btn btn-success btn-custom"><i class="fas fa-play"></i> Starten</button>
                                <?php endif; ?>
                            </form>
                            <button type="button" class="btn btn-outline-info btn-custom" data-bs-toggle="modal" data-bs-target="#teamModal<?php echo $bot['id']; ?>"><i class="fas fa-users"></i> Team verwalten</button>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Team-Modal für diesen Bot -->
            <div class="modal fade" id="teamModal<?php echo $bot['id']; ?>" tabindex="-1" aria-labelledby="teamModalLabel<?php echo $bot['id']; ?>" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" style="background:#23203A;color:#fff;border-radius:14px;">
                  <div class="modal-header" style="border-bottom:1px solid #444;">
                    <h5 class="modal-title" id="teamModalLabel<?php echo $bot['id']; ?>">Team verwalten für Bot <b><?php echo htmlspecialchars($bot['name']); ?></b></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="filter:invert(1);"></button>
                  </div>
                  <div class="modal-body">
                    <form method="POST" class="mb-3 d-flex gap-2 align-items-end">
                      <input type="hidden" name="bot_id" value="<?php echo $bot['id']; ?>">
                      <select name="user_id" class="form-select" style="max-width:220px;">
                        <option value="">User wählen...</option>
                        <?php foreach ($users as $user): ?>
                          <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['username']); ?></option>
                        <?php endforeach; ?>
                      </select>
                      <button type="submit" name="action" value="add_team_member" class="btn btn-success btn-sm"><i class="fas fa-plus"></i> Hinzufügen</button>
                    </form>
                    <div><b>Team-Mitglieder:</b></div>
                    <ul class="list-group list-group-flush" style="background:transparent;">
                      <?php
                        $team = [];
                        $res = $db->query('SELECT users.id, users.username FROM bot_users JOIN users ON bot_users.user_id = users.id WHERE bot_users.bot_id = ' . intval($bot['id']));
                        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
                          $team[] = $row;
                        }
                      ?>
                      <?php if (empty($team)): ?>
                        <li class="list-group-item bg-transparent text-muted">Kein Team-Mitglied</li>
                      <?php else: ?>
                        <?php foreach ($team as $member): ?>
                          <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($member['username']); ?></span>
                            <form method="POST" style="display:inline;">
                              <input type="hidden" name="bot_id" value="<?php echo $bot['id']; ?>">
                              <input type="hidden" name="user_id" value="<?php echo $member['id']; ?>">
                              <button type="submit" name="action" value="remove_team_member" class="btn btn-danger btn-sm"><i class="fas fa-times"></i></button>
                            </form>
                          </li>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </ul>
                  </div>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 