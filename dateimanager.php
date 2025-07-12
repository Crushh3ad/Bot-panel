<?php
session_start();

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
$stmt = $db->prepare('SELECT * FROM bots WHERE id = :bot_id AND user_id = :user_id');
$stmt->bindValue(':bot_id', $bot_id, SQLITE3_INTEGER);
$stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
$result = $stmt->execute();
$bot = $result->fetchArray(SQLITE3_ASSOC);
if (!$bot) {
    header('Location: dashboard.php');
    exit;
}

$bot_dir = __DIR__ . "/bots/" . $bot_id . "/";
if (!is_dir($bot_dir)) {
    mkdir($bot_dir, 0775, true);
}

$msg = '';
$err = '';

// Datei-Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['uploadfile'])) {
    $file = $_FILES['uploadfile'];
    $allowed = ['py','js','json','env','txt','md','sh','yml','yaml','ini','cfg'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (in_array($ext, $allowed)) {
        $target = $bot_dir . basename($file['name']);
        if (move_uploaded_file($file['tmp_name'], $target)) {
            $msg = 'Datei erfolgreich hochgeladen!';
        } else {
            $err = 'Fehler beim Hochladen!';
        }
    } else {
        $err = 'Dateityp nicht erlaubt!';
    }
}

// Datei speichern (Bearbeiten)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['savefile'])) {
    $filename = basename($_POST['filename']);
    $filepath = $bot_dir . $filename;
    if (file_exists($filepath)) {
        file_put_contents($filepath, $_POST['filecontent']);
        $msg = 'Datei gespeichert!';
    } else {
        $err = 'Datei nicht gefunden!';
    }
}

// Datei löschen
if (isset($_GET['delete'])) {
    $filename = basename($_GET['delete']);
    $filepath = $bot_dir . $filename;
    if (file_exists($filepath)) {
        unlink($filepath);
        $msg = 'Datei gelöscht!';
    } else {
        $err = 'Datei nicht gefunden!';
    }
}

// Datei zum Bearbeiten öffnen
$editfile = null;
$editcontent = '';
if (isset($_GET['edit'])) {
    $editfile = basename($_GET['edit']);
    $filepath = $bot_dir . $editfile;
    if (file_exists($filepath)) {
        $editcontent = file_get_contents($filepath);
    } else {
        $err = 'Datei nicht gefunden!';
    }
}

// Dateien auflisten
$files = array_values(array_filter(scandir($bot_dir), function($f) use ($bot_dir) {
    return is_file($bot_dir . $f);
}));

// Logfile anzeigen
$logcontent = '';
if (isset($_GET['showlog'])) {
    $logfile = $bot_dir . 'output.log';
    if (file_exists($logfile)) {
        $logcontent = file_get_contents($logfile);
    } else {
        $logcontent = 'Noch keine Log-Ausgabe.';
    }
}

// --- Automatische Info-Erkennung ---
$main_file = '';
$language = '';
$meta = [];
foreach ($files as $f) {
    $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
    if (in_array($ext, ['py','js','json','env','txt','md','sh','yml','yaml','ini','cfg'])) {
        // Sprache erkennen
        if ($ext === 'py') $language = 'Python';
        if ($ext === 'js') $language = 'Node.js';
        if ($ext === 'sh') $language = 'Shell Script';
        if ($ext === 'json') $language = 'JSON';
        if ($ext === 'yml' || $ext === 'yaml') $language = 'YAML';
        if ($ext === 'ini' || $ext === 'cfg') $language = 'Config';
        // Hauptdatei raten
        if (in_array($f, ['bot.py','main.py','index.js','app.js'])) $main_file = $f;
        // Metadaten auslesen (erste Zeilen als Kommentar)
        $content = file_get_contents($bot_dir . $f);
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            if (preg_match('/^# ?(.*)/', $line, $m) || preg_match('/^\/\/ ?(.*)/', $line, $m)) {
                $meta[] = trim($m[1]);
            }
            if (count($meta) > 5) break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dateimanager - <?php echo htmlspecialchars($bot['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .filemanager-container { background: rgba(255,255,255,0.97); border-radius: 20px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); margin: 20px 0; }
        .file-list { font-family: 'Courier New', monospace; }
        .file-actions a, .file-actions form { display: inline-block; margin-right: 5px; }
        textarea { font-family: 'Fira Mono', 'Courier New', monospace; font-size: 1rem; }
    </style>
</head>
<body>
<div class="container-fluid fade-in">
    <div class="row mb-4">
        <div class="col-12">
            <div class="filemanager-container p-4 shadow-accent" style="backdrop-filter: blur(14px); box-shadow: 0 8px 40px #A259FF33, 0 1.5px 8px #5D50FE22; border-radius: 28px; border: none;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-0 text-accent" style="letter-spacing:1px;"><i class="fas fa-folder-open"></i> Dateien verwalten - <?php echo htmlspecialchars($bot['name']); ?></h2>
                        <p class="text-muted mb-0">Bot-ID: <?php echo $bot_id; ?> | Kategorie: <?php echo htmlspecialchars($bot['category']); ?></p>
                    </div>
                    <div>
                        <a href="dashboard.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Zurück</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card p-4 shadow-accent fade-in" style="backdrop-filter: blur(10px); border-radius: 22px; border: none; box-shadow: 0 4px 24px #A259FF22;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5>Dateien im Bot-Ordner</h5>
                        <!-- Bot-Infos anzeigen -->
                        <div class="mb-3">
                            <div class="alert alert-secondary">
                                <strong>Sprache:</strong> <?php echo $language ? $language : 'Unbekannt'; ?><br>
                                <strong>Hauptdatei:</strong> <?php echo $main_file ? htmlspecialchars($main_file) : 'Nicht erkannt'; ?><br>
                                <?php if (!empty($meta)): ?>
                                    <strong>Metadaten:</strong><br>
                                    <ul class="mb-0">
                                        <?php foreach ($meta as $m): ?><li><?php echo htmlspecialchars($m); ?></li><?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="mb-3">
                            <a href="?bot_id=<?php echo $bot_id; ?>&showlog=1" class="btn btn-outline-dark btn-sm"><i class="fas fa-file-alt"></i> Logfile anzeigen</a>
                        </div>
                        <?php if (isset($_GET['showlog'])): ?>
                            <div class="mb-3">
                                <h6>Logfile-Ausgabe:</h6>
                                <pre style="background:#222;color:#0f0;padding:10px;border-radius:8px;max-height:300px;overflow:auto;"><?php echo htmlspecialchars($logcontent); ?></pre>
                                <a href="?bot_id=<?php echo $bot_id; ?>" class="btn btn-secondary btn-sm">Schließen</a>
                            </div>
                        <?php endif; ?>
                        <ul class="list-group file-list mb-3">
                            <?php if (empty($files)): ?>
                                <li class="list-group-item">Keine Dateien vorhanden.</li>
                            <?php else: foreach ($files as $f): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-file-code"></i> <?php echo htmlspecialchars($f); ?></span>
                                    <span class="file-actions">
                                        <a href="?bot_id=<?php echo $bot_id; ?>&edit=<?php echo urlencode($f); ?>" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i> Bearbeiten</a>
                                        <a href="?bot_id=<?php echo $bot_id; ?>&delete=<?php echo urlencode($f); ?>" class="btn btn-sm btn-danger" onclick="return confirm('Datei wirklich löschen?')"><i class="fas fa-trash"></i></a>
                                    </span>
                                </li>
                            <?php endforeach; endif; ?>
                        </ul>
                        <form method="POST" enctype="multipart/form-data" class="mb-3">
                            <div class="input-group">
                                <input type="file" name="uploadfile" class="form-control" required>
                                <button class="btn btn-success" type="submit"><i class="fas fa-upload"></i> Hochladen</button>
                            </div>
                            <small class="text-muted">Erlaubte Dateitypen: py, js, json, env, txt, md, sh, yml, yaml, ini, cfg</small>
                        </form>
                    </div>
                    <div class="col-lg-6">
                        <?php if ($editfile): ?>
                            <h5>Datei bearbeiten: <span class="text-primary"><?php echo htmlspecialchars($editfile); ?></span></h5>
                            <form method="POST">
                                <input type="hidden" name="filename" value="<?php echo htmlspecialchars($editfile); ?>">
                                <textarea name="filecontent" rows="18" class="form-control mb-2"><?php echo htmlspecialchars($editcontent); ?></textarea>
                                <button class="btn btn-primary" type="submit" name="savefile"><i class="fas fa-save"></i> Speichern</button>
                                <a href="?bot_id=<?php echo $bot_id; ?>" class="btn btn-secondary">Abbrechen</a>
                            </form>
                        <?php else: ?>
                            <h5>Datei bearbeiten</h5>
                            <div class="alert alert-info">Wähle eine Datei zum Bearbeiten aus.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 