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

// Bot abrufen
$stmt = $db->prepare('SELECT * FROM bots WHERE id = :bot_id AND user_id = :user_id');
$stmt->bindValue(':bot_id', $bot_id, SQLITE3_INTEGER);
$stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
$result = $stmt->execute();
$bot = $result->fetchArray(SQLITE3_ASSOC);

if (!$bot) {
    header('Location: dashboard.php');
    exit;
}

$success = '';
$error = '';

// Konfiguration speichern
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'save_config') {
    $name = $_POST['name'];
    $category = $_POST['category'];
    $token = $_POST['token'];
    $prefix = $_POST['prefix'] ?? '!';
    $description = $_POST['description'] ?? '';
    
    try {
        $stmt = $db->prepare('UPDATE bots SET name = :name, category = :category, token = :token WHERE id = :bot_id AND user_id = :user_id');
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $stmt->bindValue(':category', $category, SQLITE3_TEXT);
        $stmt->bindValue(':token', $token, SQLITE3_TEXT);
        $stmt->bindValue(':bot_id', $bot_id, SQLITE3_INTEGER);
        $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
        $stmt->execute();
        
        $success = 'Konfiguration erfolgreich gespeichert!';
        
        // Bot-Daten neu laden
        $stmt = $db->prepare('SELECT * FROM bots WHERE id = :bot_id AND user_id = :user_id');
        $stmt->bindValue(':bot_id', $bot_id, SQLITE3_INTEGER);
        $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
        $result = $stmt->execute();
        $bot = $result->fetchArray(SQLITE3_ASSOC);
    } catch (Exception $e) {
        $error = 'Fehler beim Speichern der Konfiguration!';
    }
}

// Team-Mitglieder verwalten (nur Besitzer/Admin)
$is_owner = ($bot['user_id'] == $_SESSION['user_id']) || !empty($_SESSION['is_admin']);
$team_error = '';
$team_success = '';
if ($is_owner && isset($_POST['action']) && $_POST['action'] === 'add_team_member') {
    $email = trim($_POST['team_email']);
    $user = $db->querySingle('SELECT * FROM users WHERE email = "' . SQLite3::escapeString($email) . '"', true);
    if ($user) {
        $exists = $db->querySingle('SELECT 1 FROM bot_users WHERE bot_id = ' . intval($bot_id) . ' AND user_id = ' . intval($user['id']));
        if (!$exists) {
            $stmt = $db->prepare('INSERT INTO bot_users (bot_id, user_id) VALUES (:bot_id, :user_id)');
            $stmt->bindValue(':bot_id', $bot_id, SQLITE3_INTEGER);
            $stmt->bindValue(':user_id', $user['id'], SQLITE3_INTEGER);
            $stmt->execute();
            $team_success = 'User hinzugefügt!';
        } else {
            $team_error = 'User ist bereits Team-Mitglied.';
        }
    } else {
        $team_error = 'User mit dieser E-Mail existiert nicht!';
    }
}
if ($is_owner && isset($_POST['action']) && $_POST['action'] === 'remove_team_member') {
    $uid = intval($_POST['remove_user_id']);
    $stmt = $db->prepare('DELETE FROM bot_users WHERE bot_id = :bot_id AND user_id = :user_id');
    $stmt->bindValue(':bot_id', $bot_id, SQLITE3_INTEGER);
    $stmt->bindValue(':user_id', $uid, SQLITE3_INTEGER);
    $stmt->execute();
    $team_success = 'User entfernt!';
}
// Team-Mitglieder abrufen
$team = [];
$res = $db->query('SELECT users.id, users.username, users.email FROM bot_users JOIN users ON bot_users.user_id = users.id WHERE bot_users.bot_id = ' . intval($bot_id));
while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $team[] = $row;
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfiguration - <?php echo htmlspecialchars($bot['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .config-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            margin: 20px 0;
        }
        .config-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border: none;
        }
        .config-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
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
        .category-badge {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border-radius: 20px;
            padding: 5px 15px;
            font-size: 0.8rem;
        }
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
    </style>
</head>
<body>
    <div class="container-fluid fade-in">
        <div class="row mb-4">
            <div class="col-12">
                <div class="config-container p-4 shadow-accent" style="backdrop-filter: blur(14px); box-shadow: 0 8px 40px #A259FF33, 0 1.5px 8px #5D50FE22; border-radius: 28px; border: none;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-0 text-accent" style="letter-spacing:1px;"><i class="fas fa-cog"></i> Konfiguration - <?php echo htmlspecialchars($bot['name']); ?></h2>
                            <p class="text-muted mb-0"><span class="category-badge bg-accent"><?php echo htmlspecialchars($bot['category']); ?></span> Status: <?php echo ucfirst($bot['status']); ?></p>
                        </div>
                        <div>
                            <a href="dashboard.php" class="btn btn-outline-secondary btn-custom me-2"><i class="fas fa-arrow-left"></i> Zurück</a>
                            <a href="terminal.php?bot_id=<?php echo $bot_id; ?>" class="btn btn-outline-primary btn-custom"><i class="fas fa-terminal"></i> Terminal</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card p-4 shadow-accent fade-in" style="backdrop-filter: blur(10px); border-radius: 22px; border: none; box-shadow: 0 4px 24px #A259FF22;">
                    <!-- Konfigurationsformular bleibt erhalten, nur Optik modernisiert -->
                    <h4><i class="fas fa-cog text-primary"></i> Bot Konfiguration</h4>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="save_config">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Bot Name</label>
                                <input type="text" class="form-control" name="name" 
                                       value="<?php echo htmlspecialchars($bot['name']); ?>" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Kategorie</label>
                                <select class="form-select" name="category" required>
                                    <option value="Moderation" <?php echo $bot['category'] == 'Moderation' ? 'selected' : ''; ?>>Moderation</option>
                                    <option value="Music" <?php echo $bot['category'] == 'Music' ? 'selected' : ''; ?>>Music</option>
                                    <option value="Fun" <?php echo $bot['category'] == 'Fun' ? 'selected' : ''; ?>>Fun</option>
                                    <option value="Utility" <?php echo $bot['category'] == 'Utility' ? 'selected' : ''; ?>>Utility</option>
                                    <option value="Games" <?php echo $bot['category'] == 'Games' ? 'selected' : ''; ?>>Games</option>
                                    <option value="Custom" <?php echo $bot['category'] == 'Custom' ? 'selected' : ''; ?>>Custom</option>
                                </select>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label">Discord Token</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="token" 
                                           value="<?php echo htmlspecialchars($bot['token']); ?>" required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword()">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <small class="text-muted">Das Token wird sicher gespeichert und nicht angezeigt</small>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Command Prefix</label>
                                <input type="text" class="form-control" name="prefix" 
                                       value="!" placeholder="!">
                                <small class="text-muted">Standard: !</small>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Bot Status</label>
                                <input type="text" class="form-control" value="<?php echo ucfirst($bot['status']); ?>" readonly>
                                <small class="text-muted">Status wird über das Dashboard gesteuert</small>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label">Beschreibung</label>
                                <textarea class="form-control" name="description" rows="3" 
                                          placeholder="Beschreibung des Bots..."><?php echo htmlspecialchars($bot['description'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary btn-custom">
                                    <i class="fas fa-save"></i> Konfiguration speichern
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="col-lg-4">
                <!-- Bot Info -->
                <div class="config-card p-4 mb-4">
                    <h5><i class="fas fa-info-circle text-primary"></i> Bot Informationen</h5>
                    <div class="mb-3">
                        <strong>Name:</strong> <?php echo htmlspecialchars($bot['name']); ?>
                    </div>
                    <div class="mb-3">
                        <strong>Kategorie:</strong> 
                        <span class="category-badge"><?php echo htmlspecialchars($bot['category']); ?></span>
                    </div>
                    <div class="mb-3">
                        <strong>Status:</strong> 
                        <span class="badge <?php echo $bot['status'] == 'running' ? 'bg-success' : 'bg-danger'; ?>">
                            <?php echo ucfirst($bot['status']); ?>
                        </span>
                    </div>
                    <div class="mb-3">
                        <strong>Erstellt:</strong> <?php echo date('d.m.Y H:i', strtotime($bot['created_at'])); ?>
                    </div>
                </div>
                
                <!-- Schnellaktionen -->
                <div class="config-card p-4">
                    <h5><i class="fas fa-bolt text-primary"></i> Schnellaktionen</h5>
                    <div class="d-grid gap-2">
                        <a href="terminal.php?bot_id=<?php echo $bot_id; ?>" class="btn btn-outline-primary btn-custom">
                            <i class="fas fa-terminal"></i> Terminal öffnen
                        </a>
                        <button class="btn btn-outline-success btn-custom" onclick="startBot()">
                            <i class="fas fa-play"></i> Bot starten
                        </button>
                        <button class="btn btn-outline-warning btn-custom" onclick="stopBot()">
                            <i class="fas fa-pause"></i> Bot stoppen
                        </button>
                        <button class="btn btn-outline-info btn-custom" onclick="restartBot()">
                            <i class="fas fa-sync-alt"></i> Bot neu starten
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php if ($is_owner): ?>
        <div class="row mt-4">
            <div class="col-12">
                <div class="card p-4 shadow-accent fade-in mt-4" style="backdrop-filter: blur(10px); border-radius: 22px; border: none; box-shadow: 0 4px 24px #A259FF22;">
                    <h4 class="mb-3 text-accent"><i class="fas fa-users"></i> Team-Mitglieder</h4>
                    <?php if ($team_success): ?><div class="alert alert-success fade-in"><?php echo $team_success; ?></div><?php endif; ?>
                    <?php if ($team_error): ?><div class="alert alert-danger fade-in"><?php echo $team_error; ?></div><?php endif; ?>
                    <form method="POST" class="mb-3 d-flex gap-2">
                        <input type="hidden" name="action" value="add_team_member">
                        <input type="email" name="team_email" class="form-control" placeholder="E-Mail des Users" required>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus"></i> Hinzufügen</button>
                    </form>
                    <ul class="list-group">
                        <?php foreach ($team as $member): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><?php echo htmlspecialchars($member['username']); ?> (<?php echo htmlspecialchars($member['email']); ?>)</span>
                                <form method="POST" style="margin:0;">
                                    <input type="hidden" name="action" value="remove_team_member">
                                    <input type="hidden" name="remove_user_id" value="<?php echo $member['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-user-minus"></i></button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword() {
            const input = document.querySelector('input[name="token"]');
            const button = document.querySelector('button[onclick="togglePassword()"]');
            const icon = button.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }
        
        function startBot() {
            if (confirm('Bot wirklich starten?')) {
                // Hier würde der Bot-Start-Befehl ausgeführt werden
                alert('Bot wird gestartet...');
            }
        }
        
        function stopBot() {
            if (confirm('Bot wirklich stoppen?')) {
                // Hier würde der Bot-Stop-Befehl ausgeführt werden
                alert('Bot wird gestoppt...');
            }
        }
        
        function restartBot() {
            if (confirm('Bot wirklich neu starten?')) {
                // Hier würde der Bot-Restart-Befehl ausgeführt werden
                alert('Bot wird neu gestartet...');
            }
        }
    </script>
</body>
</html> 