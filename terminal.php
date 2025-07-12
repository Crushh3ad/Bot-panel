<?php
session_start();

// Überprüfe Login
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$bot_id = $_GET['bot_id'] ?? null;
if (!$bot_id) {
    header('Location: dashboard.php');
    exit;
}

$db = new SQLite3('bots.db');
// Team-Zugriff prüfen: Besitzer oder Team-Mitglied
$stmt = $db->prepare('SELECT 1 FROM bots LEFT JOIN bot_users ON bots.id = bot_users.bot_id WHERE bots.id = :bot_id AND (bots.user_id = :uid OR bot_users.user_id = :uid)');
$stmt->bindValue(':bot_id', $bot_id, SQLITE3_INTEGER);
$stmt->bindValue(':uid', $_SESSION['user_id'], SQLITE3_INTEGER);
$has_access = $stmt->execute()->fetchArray()[0] ?? false;
if (!$has_access) {
    header('Location: dashboard.php');
    exit;
}

// Bot abrufen (Besitzer oder Team-Mitglied)
$stmt = $db->prepare('SELECT bots.* FROM bots LEFT JOIN bot_users ON bots.id = bot_users.bot_id WHERE bots.id = :bot_id AND (bots.user_id = :uid OR bot_users.user_id = :uid)');
$stmt->bindValue(':bot_id', $bot_id, SQLITE3_INTEGER);
$stmt->bindValue(':uid', $_SESSION['user_id'], SQLITE3_INTEGER);
$result = $stmt->execute();
$bot = $result->fetchArray(SQLITE3_ASSOC);

if (!$bot) {
    header('Location: dashboard.php');
    exit;
}

$bot_dir = __DIR__ . "/bots/" . $bot_id . "/";
$logfile = $bot_dir . 'output.log';

// AJAX: Logfile anzeigen
if (isset($_GET['ajax']) && $_GET['ajax'] === 'log') {
    if (file_exists($logfile)) {
        echo htmlspecialchars(file_get_contents($logfile));
    } else {
        echo "Noch keine Log-Ausgabe.";
    }
    exit;
}

// AJAX: Terminal-Befehle
if (isset($_POST['terminal_command'])) {
    $cmd = strtolower(trim($_POST['terminal_command']));
    $response = "";
    
    // Erlaubte Befehle definieren
    $allowed_commands = ['start', 'stop', 'restart', 'status', 'logs', 'clear', 'help'];
    
    // Prüfe ob Befehl erlaubt ist
    if (!in_array($cmd, $allowed_commands)) {
        $response = "FEHLER: Befehl '$cmd' ist nicht erlaubt!\n";
        $response .= "Erlaubte Befehle: " . implode(', ', $allowed_commands) . "\n";
        echo nl2br(htmlspecialchars($response));
        exit;
    }
    
    if ($cmd === 'start') {
        // Starte Bot wie im Dashboard
        $main_file = '';
        $language = '';
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
        if ($main_file && $language) {
            // Installiere requirements.txt falls vorhanden
            $requirements_path = $bot_dir . 'requirements.txt';
            if (file_exists($requirements_path)) {
                shell_exec("pip3 install --break-system-packages -r '" . $requirements_path . "' 2>&1");
            }
            // Starte Prozess
            if ($language == 'python') {
                $cmdline = "nohup setsid python3 '$bot_dir$main_file' > '$logfile' 2>&1 & echo $!";
            } elseif ($language == 'node') {
                $cmdline = "nohup setsid node '$bot_dir$main_file' > '$logfile' 2>&1 & echo $!";
            } elseif ($language == 'bash') {
                $cmdline = "nohup setsid bash '$bot_dir$main_file' > '$logfile' 2>&1 & echo $!";
            } else {
                $cmdline = '';
            }
            if ($cmdline) {
                $output = shell_exec($cmdline);
                $pid = intval(trim($output));
                $stmt = $db->prepare('UPDATE bots SET status = :status, pid = :pid WHERE id = :bot_id AND user_id = :user_id');
                $stmt->bindValue(':status', 'running', SQLITE3_TEXT);
                $stmt->bindValue(':pid', $pid, SQLITE3_INTEGER);
                $stmt->bindValue(':bot_id', $bot_id, SQLITE3_INTEGER);
                $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
                $stmt->execute();
                $response = "Bot wird gestartet...\n";
            } else {
                $response = "Fehler: Keine Startdatei gefunden!\n";
            }
        } else {
            $response = "Fehler: Keine Startdatei gefunden!\n";
        }
    } elseif ($cmd === 'stop') {
        // Stoppe Prozessgruppe
        $pid = $bot['pid'] ?? null;
        if ($pid) {
            shell_exec('kill -TERM -' . intval($pid));
            $stmt = $db->prepare('UPDATE bots SET status = :status, pid = NULL WHERE id = :bot_id AND user_id = :user_id');
            $stmt->bindValue(':status', 'stopped', SQLITE3_TEXT);
            $stmt->bindValue(':bot_id', $bot_id, SQLITE3_INTEGER);
            $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
            $stmt->execute();
            $response = "Bot wird gestoppt...\n";
        } else {
            $response = "Bot ist nicht gestartet.\n";
        }
    } elseif ($cmd === 'restart') {
        // Stoppe und starte neu
        $pid = $bot['pid'] ?? null;
        if ($pid) {
            shell_exec('kill -TERM -' . intval($pid));
            $stmt = $db->prepare('UPDATE bots SET status = :status, pid = NULL WHERE id = :bot_id AND user_id = :user_id');
            $stmt->bindValue(':status', 'stopped', SQLITE3_TEXT);
            $stmt->bindValue(':bot_id', $bot_id, SQLITE3_INTEGER);
            $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
            $stmt->execute();
            sleep(1);
        }
        // Starte wie oben
        $main_file = '';
        $language = '';
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
        if ($main_file && $language) {
            $requirements_path = $bot_dir . 'requirements.txt';
            if (file_exists($requirements_path)) {
                shell_exec("pip3 install --break-system-packages -r '" . $requirements_path . "' 2>&1");
            }
            if ($language == 'python') {
                $cmdline = "nohup setsid python3 '$bot_dir$main_file' > '$logfile' 2>&1 & echo $!";
            } elseif ($language == 'node') {
                $cmdline = "nohup setsid node '$bot_dir$main_file' > '$logfile' 2>&1 & echo $!";
            } elseif ($language == 'bash') {
                $cmdline = "nohup setsid bash '$bot_dir$main_file' > '$logfile' 2>&1 & echo $!";
            } else {
                $cmdline = '';
            }
            if ($cmdline) {
                $output = shell_exec($cmdline);
                $pid = intval(trim($output));
                $stmt = $db->prepare('UPDATE bots SET status = :status, pid = :pid WHERE id = :bot_id AND user_id = :user_id');
                $stmt->bindValue(':status', 'running', SQLITE3_TEXT);
                $stmt->bindValue(':pid', $pid, SQLITE3_INTEGER);
                $stmt->bindValue(':bot_id', $bot_id, SQLITE3_INTEGER);
                $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
                $stmt->execute();
                $response .= "Bot wird neu gestartet...\n";
            } else {
                $response .= "Fehler: Keine Startdatei gefunden!\n";
            }
        } else {
            $response .= "Fehler: Keine Startdatei gefunden!\n";
        }
    } elseif ($cmd === 'status') {
        $response = "Status: " . ucfirst($bot['status']) . "\nPID: " . ($bot['pid'] ?? '---') . "\n";
    } elseif ($cmd === 'logs') {
        if (file_exists($logfile)) {
            $response = file_get_contents($logfile);
        } else {
            $response = "Noch keine Log-Ausgabe.";
        }
    } elseif ($cmd === 'clear') {
        file_put_contents($logfile, '');
        $response = "Logfile gelöscht.\n";
    } elseif ($cmd === 'help') {
        $response = "=== Verfügbare Befehle ===\n";
        $response .= "start   - Startet den Bot-Prozess\n";
        $response .= "stop    - Stoppt den Bot-Prozess\n";
        $response .= "restart - Startet den Bot neu\n";
        $response .= "status  - Zeigt aktuellen Status und PID\n";
        $response .= "logs    - Zeigt den aktuellen Logfile-Inhalt\n";
        $response .= "clear   - Löscht das Logfile\n";
        $response .= "help    - Zeigt diese Hilfe an\n";
    }
    echo nl2br(htmlspecialchars($response));
    exit;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terminal - <?php echo htmlspecialchars($bot['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .terminal-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            margin: 20px 0;
        }
        .terminal {
            background: #1e1e1e;
            color: #00ff00;
            font-family: 'Courier New', monospace;
            border-radius: 10px;
            padding: 15px;
            height: 500px;
            overflow-y: auto;
            border: 2px solid #333;
        }
        .terminal-input {
            background: #1e1e1e;
            color: #00ff00;
            border: none;
            outline: none;
            font-family: 'Courier New', monospace;
            width: 100%;
            padding: 10px;
            border-radius: 5px;
        }
        .btn-custom {
            border-radius: 10px;
            padding: 8px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-custom:hover {
            transform: translateY(-2px);
        }
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        .status-running {
            background: #28a745;
            animation: pulse 2s infinite;
        }
        .status-stopped {
            background: #dc3545;
        }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
    </style>
</head>
<body>
    <div class="container-fluid fade-in">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="terminal-container p-4 shadow-accent" style="backdrop-filter: blur(14px); box-shadow: 0 8px 40px #A259FF33, 0 1.5px 8px #5D50FE22; border-radius: 28px; border: none;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-0 text-accent" style="letter-spacing:1px;"><i class="fas fa-terminal"></i> Terminal - <?php echo htmlspecialchars($bot['name']); ?></h2>
                            <p class="text-muted mb-0"><span class="status-indicator <?php echo $bot['status'] == 'running' ? 'status-running' : 'status-stopped'; ?>"></span> Status: <?php echo ucfirst($bot['status']); ?> | Kategorie: <?php echo htmlspecialchars($bot['category']); ?></p>
                        </div>
                        <div>
                            <a href="dashboard.php" class="btn btn-outline-secondary btn-custom me-2"><i class="fas fa-arrow-left"></i> Zurück</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Terminal Output -->
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card p-4 shadow-accent fade-in" style="backdrop-filter: blur(10px); border-radius: 22px; border: none; box-shadow: 0 4px 24px #A259FF22;">
                    <div id="terminal-output" class="terminal mb-3" style="height:350px; background:#18122B; color:#fff; font-size:1.1rem; border-radius:14px; border:1.5px solid #23203A; padding:18px; overflow-y:auto;"></div>
                    <div class="input-group">
                        <input type="text" id="terminal-input" class="form-control form-control-lg" placeholder="Befehl eingeben..." autocomplete="off" style="background:#23203A; color:#fff; border-radius:12px 0 0 12px;">
                        <button onclick="executeCommand()" class="btn btn-primary btn-lg" style="border-radius:0 12px 12px 0;"><i class="fas fa-paper-plane"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const terminalOutput = document.getElementById('terminal-output');
        const terminalInput = document.getElementById('terminal-input');
        const botId = <?php echo $bot_id; ?>;
        
        // Live-Loganzeige alle 2 Sekunden (ENTFERNT)
        // function reloadLog() {
        //     fetch('terminal.php?bot_id=' + botId + '&ajax=log')
        //         .then(r => r.text())
        //         .then(txt => {
        //             terminalOutput.innerHTML = txt;
        //             terminalOutput.scrollTop = terminalOutput.scrollHeight;
        //         });
        // }
        // setInterval(reloadLog, 2000);
        // reloadLog();
        
        // Terminal Input Handler
        terminalInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                executeCommand();
            }
        });
        
        function executeCommand() {
            const command = terminalInput.value.trim();
            if (!command) return;
            // Zeige eigenen Befehl im Terminal an
            terminalOutput.innerHTML += '\n> ' + command;
            fetch('terminal.php?bot_id=' + botId, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'terminal_command=' + encodeURIComponent(command)
            })
            .then(r => r.text())
            .then(txt => {
                terminalOutput.innerHTML += '\n' + txt;
                terminalOutput.scrollTop = terminalOutput.scrollHeight;
            });
            terminalInput.value = '';
        }
        
        // Auto-Focus auf Input
        terminalInput.focus();
    </script>
</body>
</html> 