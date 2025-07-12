<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php-error.log');
session_start();
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header('Location: index.php');
    exit;
}
// CSRF-Token generieren
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
// CSRF-Token prüfen
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('<div style="color:red;font-weight:bold">CSRF-Token ungültig. Bitte Seite neu laden.</div>');
    }
}

$db = new SQLite3('bots.db');

// Einnahmen-Tabelle anlegen, falls nicht vorhanden
$result = $db->exec('CREATE TABLE IF NOT EXISTS payments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    amount REAL NOT NULL,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
)');
if (!$result) {
    die('<div style="color:red;background:#222;padding:10px;">SQLite-Fehler: ' . $db->lastErrorMsg() . '</div>');
}
// Einnahme hinzufügen
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'add_payment') {
    $amount = floatval($_POST['amount']);
    $description = trim($_POST['description']);
    $user_id = !empty($_POST['payment_user_id']) ? intval($_POST['payment_user_id']) : null;
    $stmt = $db->prepare('INSERT INTO payments (user_id, amount, description) VALUES (:user_id, :amount, :description)');
    if ($user_id) {
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    } else {
        $stmt->bindValue(':user_id', null, SQLITE3_NULL);
    }
    $stmt->bindValue(':amount', $amount, SQLITE3_FLOAT);
    $stmt->bindValue(':description', $description, SQLITE3_TEXT);
    $stmt->execute();
    $payment_success = 'Einnahme erfolgreich hinzugefügt!';
}

// Einnahme löschen
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'delete_payment') {
    $payment_id = intval($_POST['payment_id']);
    $stmt = $db->prepare('DELETE FROM payments WHERE id = :id');
    $stmt->bindValue(':id', $payment_id, SQLITE3_INTEGER);
    $stmt->execute();
    $payment_delete_success = 'Einnahme gelöscht!';
}

// Einnahmen-Statistiken
$total_income = $db->querySingle('SELECT IFNULL(SUM(amount),0) FROM payments');
$income_30d = $db->querySingle('SELECT IFNULL(SUM(amount),0) FROM payments WHERE created_at >= DATE("now", "-30 days")');
// Einnahmen-Verlauf (letzte 14 Tage)
$income_labels = [];
$income_data = [];
for ($i = 13; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $income_labels[] = date('d.m.', strtotime($date));
    $sum = $db->querySingle("SELECT IFNULL(SUM(amount),0) FROM payments WHERE DATE(created_at) = '$date'");
    $income_data[] = $sum ? round($sum,2) : 0;
}
// Letzte Einnahmen
$payments = [];
$res = $db->query('SELECT p.*, u.username FROM payments p LEFT JOIN users u ON p.user_id = u.id ORDER BY p.created_at DESC LIMIT 20');
while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $payments[] = $row;
}
// Alle User für Auswahl im Einnahmen-Formular
$user_options = [];
$res = $db->query('SELECT id, username FROM users ORDER BY username');
while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $user_options[] = $row;
}

// Benutzer bearbeiten
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'edit_user') {
    $user_id = intval($_POST['edit_user_id']);
    $username = trim($_POST['edit_username']);
    $email = trim($_POST['edit_email']);
    $is_admin = isset($_POST['edit_is_admin']) ? 1 : 0;
    $set_password = !empty($_POST['edit_password']);
    try {
        if ($set_password) {
            $password = password_hash($_POST['edit_password'], PASSWORD_DEFAULT);
            $stmt = $db->prepare('UPDATE users SET username = :username, email = :email, password = :password, is_admin = :is_admin WHERE id = :id');
            $stmt->bindValue(':password', $password, SQLITE3_TEXT);
        } else {
            $stmt = $db->prepare('UPDATE users SET username = :username, email = :email, is_admin = :is_admin WHERE id = :id');
        }
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $stmt->bindValue(':is_admin', $is_admin, SQLITE3_INTEGER);
        $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
        $stmt->execute();
        $user_edit_success = 'Benutzer erfolgreich bearbeitet!';
    } catch (Exception $e) {
        $user_edit_error = 'Fehler: Benutzername oder E-Mail existiert bereits!';
    }
}

// Benutzer anlegen
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'create_user') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $is_admin = isset($_POST['is_admin']) ? 1 : 0;
    try {
        $stmt = $db->prepare('INSERT INTO users (username, email, password, is_admin) VALUES (:username, :email, :password, :is_admin)');
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $stmt->bindValue(':password', $password, SQLITE3_TEXT);
        $stmt->bindValue(':is_admin', $is_admin, SQLITE3_INTEGER);
        $stmt->execute();
        $user_success = 'Benutzer erfolgreich angelegt!';
    } catch (Exception $e) {
        $user_error = 'Fehler: Benutzername oder E-Mail existiert bereits!';
    }
}

// Admin-Aktionen
if ($_POST && isset($_POST['action'])) {
    if ($_POST['action'] == 'delete_user') {
        $user_id = $_POST['user_id'];
        $stmt = $db->prepare('DELETE FROM bots WHERE user_id = :user_id');
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $stmt->execute();
        $stmt = $db->prepare('DELETE FROM users WHERE id = :user_id');
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $stmt->execute();
        header('Location: admin.php');
        exit;
    } elseif ($_POST['action'] == 'toggle_admin') {
        $user_id = $_POST['user_id'];
        $new_admin_status = $_POST['new_admin_status'];
        $stmt = $db->prepare('UPDATE users SET is_admin = :is_admin WHERE id = :user_id');
        $stmt->bindValue(':is_admin', $new_admin_status, SQLITE3_INTEGER);
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $stmt->execute();
        header('Location: admin.php');
        exit;
    } elseif ($_POST['action'] == 'delete_bot') {
        $bot_id = $_POST['bot_id'];
        $stmt = $db->prepare('DELETE FROM bots WHERE id = :bot_id');
        $stmt->bindValue(':bot_id', $bot_id, SQLITE3_INTEGER);
        $stmt->execute();
        header('Location: admin.php');
        exit;
    }
}

// Admin-Log-Tabelle anlegen, falls nicht vorhanden
$db->exec('CREATE TABLE IF NOT EXISTS admin_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    action TEXT NOT NULL,
    target_id INTEGER,
    target_type TEXT,
    ip TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)');

function log_admin_action($db, $user_id, $action, $target_id = null, $target_type = null) {
    $stmt = $db->prepare('INSERT INTO admin_log (user_id, action, target_id, target_type, ip) VALUES (:user_id, :action, :target_id, :target_type, :ip)');
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $stmt->bindValue(':action', $action, SQLITE3_TEXT);
    if ($target_id !== null) {
        $stmt->bindValue(':target_id', $target_id, SQLITE3_INTEGER);
    } else {
        $stmt->bindValue(':target_id', null, SQLITE3_NULL);
    }
    $stmt->bindValue(':target_type', $target_type, SQLITE3_TEXT);
    $stmt->bindValue(':ip', $_SERVER['REMOTE_ADDR'], SQLITE3_TEXT);
    $stmt->execute();
}

// Logging für kritische Aktionen
if ($_POST && isset($_POST['action'])) {
    if ($_POST['action'] == 'delete_user') {
        log_admin_action($db, $_SESSION['user_id'], 'delete_user', $_POST['user_id'], 'user');
    } elseif ($_POST['action'] == 'toggle_admin') {
        log_admin_action($db, $_SESSION['user_id'], 'toggle_admin', $_POST['user_id'], 'user');
    } elseif ($_POST['action'] == 'delete_bot') {
        log_admin_action($db, $_SESSION['user_id'], 'delete_bot', $_POST['bot_id'], 'bot');
    } elseif ($_POST['action'] == 'edit_user') {
        log_admin_action($db, $_SESSION['user_id'], 'edit_user', $_POST['edit_user_id'], 'user');
    } elseif ($_POST['action'] == 'create_user') {
        // User wird erst nach dem Insert bekannt, daher nach dem Insert loggen (siehe unten)
    } elseif ($_POST['action'] == 'add_payment') {
        log_admin_action($db, $_SESSION['user_id'], 'add_payment', null, 'payment');
    } elseif ($_POST['action'] == 'delete_payment') {
        log_admin_action($db, $_SESSION['user_id'], 'delete_payment', $_POST['payment_id'], 'payment');
    }
}
// Nach User-Insert loggen
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'create_user' && isset($stmt) && $stmt !== false) {
    $new_user_id = $db->lastInsertRowID();
    log_admin_action($db, $_SESSION['user_id'], 'create_user', $new_user_id, 'user');
}
// Admin-Login/Logout Logging
if (isset($_GET['admin_login_success'])) {
    log_admin_action($db, $_SESSION['user_id'], 'admin_login', $_SESSION['user_id'], 'user');
}
if (isset($_GET['admin_logout'])) {
    log_admin_action($db, $_SESSION['user_id'], 'admin_logout', $_SESSION['user_id'], 'user');
}
// Letzte Admin-Logs abrufen
$admin_logs = [];
$res = $db->query('SELECT l.*, u.username FROM admin_log l LEFT JOIN users u ON l.user_id = u.id ORDER BY l.created_at DESC LIMIT 20');
while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $admin_logs[] = $row;
}

// Statistiken
$total_users = $db->querySingle('SELECT COUNT(*) FROM users');
$total_bots = $db->querySingle('SELECT COUNT(*) FROM bots');
$running_bots = $db->querySingle('SELECT COUNT(*) FROM bots WHERE status = "running"');
$total_admins = $db->querySingle('SELECT COUNT(*) FROM users WHERE is_admin = 1');
$bots_per_category = [];
$res = $db->query('SELECT category, COUNT(*) as cnt FROM bots GROUP BY category');
while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $bots_per_category[$row['category']] = $row['cnt'];
}
// User-Wachstum (letzte 14 Tage)
$user_growth_labels = [];
$user_growth_data = [];
for ($i = 13; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $user_growth_labels[] = date('d.m.', strtotime($date));
    $cnt = $db->querySingle("SELECT COUNT(*) FROM users WHERE DATE(created_at) = '$date'");
    $user_growth_data[] = $cnt;
}
// Bot-Status-Verteilung
$bot_status_labels = ['running', 'stopped'];
$bot_status_data = [
    $db->querySingle('SELECT COUNT(*) FROM bots WHERE status = "running"'),
    $db->querySingle('SELECT COUNT(*) FROM bots WHERE status = "stopped"'),
];
// Alle User
$users = [];
$result = $db->query('SELECT * FROM users ORDER BY created_at DESC');
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $users[] = $row;
}
// Alle Bots
$bots = [];
$result = $db->query('SELECT b.*, u.username FROM bots b JOIN users u ON b.user_id = u.id ORDER BY b.created_at DESC');
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $bots[] = $row;
}

// Spalte force_pw_change hinzufügen (falls nicht vorhanden)
@$db->exec('ALTER TABLE users ADD COLUMN force_pw_change INTEGER DEFAULT 0');

// Passwort zurücksetzen
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'reset_password') {
    $reset_user_id = intval($_POST['reset_user_id']);
    $new_pw = bin2hex(random_bytes(4)) . rand(100,999); // z.B. 8 Zeichen + 3 Ziffern
    $pw_hash = password_hash($new_pw, PASSWORD_DEFAULT);
    $stmt = $db->prepare('UPDATE users SET password = :pw, force_pw_change = 1 WHERE id = :id');
    $stmt->bindValue(':pw', $pw_hash, SQLITE3_TEXT);
    $stmt->bindValue(':id', $reset_user_id, SQLITE3_INTEGER);
    $stmt->execute();
    $pw_reset_success = 'Neues Passwort für User-ID '.$reset_user_id.': <b>' . htmlspecialchars($new_pw) . '</b>';
}

// Backup-System: Backup erstellen und wiederherstellen
if (isset($_POST['action']) && $_POST['action'] === 'create_backup') {
    $backup_dir = __DIR__ . '/backups/';
    if (!is_dir($backup_dir)) mkdir($backup_dir, 0775, true);
    $backup_file = $backup_dir . 'backup_' . date('Ymd_His') . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($backup_file, ZipArchive::CREATE) === TRUE) {
        // SQLite DB
        $zip->addFile(__DIR__ . '/bots.db', 'bots.db');
        // Bot-Dateien
        $bot_dirs = glob(__DIR__ . '/bots/*', GLOB_ONLYDIR);
        foreach ($bot_dirs as $dir) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
            foreach ($files as $file) {
                if (!$file->isDir()) {
                    $localPath = substr($file->getPathname(), strlen(__DIR__) + 1);
                    $zip->addFile($file->getPathname(), $localPath);
                }
            }
        }
        $zip->close();
        $backup_success = basename($backup_file);
    } else {
        $backup_error = 'Backup konnte nicht erstellt werden!';
    }
}
if (isset($_GET['download_backup'])) {
    $file = __DIR__ . '/backups/' . basename($_GET['download_backup']);
    if (file_exists($file)) {
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        readfile($file);
        exit;
    }
}
if (isset($_POST['action']) && $_POST['action'] === 'restore_backup' && isset($_FILES['restorefile'])) {
    $file = $_FILES['restorefile']['tmp_name'];
    $zip = new ZipArchive();
    if ($zip->open($file) === TRUE) {
        $zip->extractTo(__DIR__);
        $zip->close();
        $restore_success = 'Backup erfolgreich wiederhergestellt!';
    } else {
        $restore_error = 'Backup konnte nicht entpackt werden!';
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Discord Bot Hosting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="admin_style.css" rel="stylesheet">
</head>
<body>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-accent" style="letter-spacing:1px;"><i class="fas fa-shield-alt"></i> Admin Dashboard</h2>
        </div>
        <div>
            <a href="dashboard.php" class="btn btn-outline-light btn-custom me-2"> <i class="fas fa-arrow-left"></i> Dashboard </a>
            <a href="logout.php" class="btn btn-outline-danger btn-custom"> <i class="fas fa-sign-out-alt"></i> Abmelden </a>
        </div>
    </div>
    <!-- Kompakte Statistik-Widgets in einer Zeile -->
    <div class="row dashboard-section g-2 mb-3" style="display:flex;gap:0.7rem;align-items:stretch;justify-content:center;">
      <div class="col-auto p-0" style="flex:1 1 120px;min-width:120px;max-width:170px;">
        <div class="dashboard-widget shadow-accent" style="padding:0.7rem 0.6rem;min-width:0;backdrop-filter:blur(6px);border-radius:12px;box-shadow:0 2px 8px #A259FF22;">
          <div class="icon" style="font-size:1.2rem;"><i class="fas fa-euro-sign text-success"></i></div>
          <div class="value" style="font-size:1.1rem;font-weight:600;"><?php echo number_format($total_income, 2, ',', '.'); ?> €</div>
          <div class="label" style="font-size:0.85rem;">Gesamteinnahmen</div>
        </div>
      </div>
      <div class="col-auto p-0" style="flex:1 1 120px;min-width:120px;max-width:170px;">
        <div class="dashboard-widget shadow-accent" style="padding:0.7rem 0.6rem;min-width:0;backdrop-filter:blur(6px);border-radius:12px;box-shadow:0 2px 8px #A259FF22;">
          <div class="icon" style="font-size:1.2rem;"><i class="fas fa-calendar text-info"></i></div>
          <div class="value" style="font-size:1.1rem;font-weight:600;"><?php echo number_format($income_30d, 2, ',', '.'); ?> €</div>
          <div class="label" style="font-size:0.85rem;">Einnahmen (30 Tage)</div>
        </div>
      </div>
      <div class="col-auto p-0" style="flex:1 1 120px;min-width:120px;max-width:170px;">
        <div class="dashboard-widget shadow-accent" style="padding:0.7rem 0.6rem;min-width:0;backdrop-filter:blur(6px);border-radius:12px;box-shadow:0 2px 8px #A259FF22;">
          <div class="icon" style="font-size:1.2rem;"><i class="fas fa-users text-info"></i></div>
          <div class="value" style="font-size:1.1rem;font-weight:600;"><?php echo $total_users; ?></div>
          <div class="label" style="font-size:0.85rem;">Benutzer</div>
        </div>
      </div>
      <div class="col-auto p-0" style="flex:1 1 120px;min-width:120px;max-width:170px;">
        <div class="dashboard-widget shadow-accent" style="padding:0.7rem 0.6rem;min-width:0;backdrop-filter:blur(6px);border-radius:12px;box-shadow:0 2px 8px #A259FF22;">
          <div class="icon" style="font-size:1.2rem;"><i class="fas fa-robot text-success"></i></div>
          <div class="value" style="font-size:1.1rem;font-weight:600;"><?php echo $total_bots; ?></div>
          <div class="label" style="font-size:0.85rem;">Bots</div>
        </div>
      </div>
      <div class="col-auto p-0" style="flex:1 1 120px;min-width:120px;max-width:170px;">
        <div class="dashboard-widget shadow-accent" style="padding:0.7rem 0.6rem;min-width:0;backdrop-filter:blur(6px);border-radius:12px;box-shadow:0 2px 8px #A259FF22;">
          <div class="icon" style="font-size:1.2rem;"><i class="fas fa-user-shield text-warning"></i></div>
          <div class="value" style="font-size:1.1rem;font-weight:600;"><?php echo $total_admins; ?></div>
          <div class="label" style="font-size:0.85rem;">Admins</div>
        </div>
      </div>
    </div>
    <!-- Charts-Section: Einnahmen-Chart nimmt volle Breite bis zu den Kreisen ein -->
<div class="admin-charts-row" style="display:flex;gap:1.2rem;justify-content:stretch;align-items:stretch;flex-wrap:nowrap;margin-bottom:2.2rem;">
  <div class="admin-chart-card" style="background:linear-gradient(120deg,#23203A 60%,#2d2550 100%);box-shadow:0 4px 24px #A259FF33;border-radius:22px;padding:18px 18px 8px 18px;flex:1 1 0;min-width:0;display:flex;align-items:center;">
    <canvas id="incomeChart" height="220" style="width:100%;height:220px;"></canvas>
  </div>
  <div style="display:flex;flex-direction:column;gap:1.2rem;flex:0 0 220px;min-width:180px;align-items:center;justify-content:center;">
    <div class="admin-chart-card" style="background:linear-gradient(120deg,#23203A 60%,#2d2550 100%);box-shadow:0 4px 24px #A259FF33;border-radius:22px;padding:12px 8px 8px 8px;max-width:220px;width:100%;display:flex;align-items:center;justify-content:center;">
      <canvas id="botStatusChart" width="180" height="180" style="width:180px;height:180px;"></canvas>
    </div>
    <div class="admin-chart-card" style="background:linear-gradient(120deg,#23203A 60%,#2d2550 100%);box-shadow:0 4px 24px #A259FF33;border-radius:22px;padding:12px 8px 8px 8px;max-width:220px;width:100%;display:flex;align-items:center;justify-content:center;">
      <canvas id="botCategoryChart" width="180" height="180" style="width:180px;height:180px;"></canvas>
    </div>
  </div>
</div>
    <!-- Benutzerübersicht -->
    <div class="admin-section-card">
        <div class="admin-section-title"><i class="fas fa-users"></i> Benutzerübersicht</div>
        <div class="table-responsive">
            <table class="table admin-table">
                <thead>
                    <tr><th>Name</th><th>E-Mail</th><th>Rolle</th><th>2FA</th><th>Aktionen</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="5" class="text-center text-muted">Keine Benutzer vorhanden oder Fehler beim Laden!</td></tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo $user['is_admin'] ? '<span class="admin-badge">Admin</span>' : '<span class="user-badge">User</span>'; ?></td>
                                <td><?php echo $user['totp_secret'] ? '<span class="otp-badge active">Aktiv</span>' : '<span class="otp-badge">Inaktiv</span>'; ?></td>
                                <td>
                                  <button class="btn btn-info btn-admin-action" data-bs-toggle="modal" data-bs-target="#editUserModal<?php echo $user['id']; ?>"><i class="fas fa-edit"></i></button>
                                  <!-- Modal für User-Bearbeitung -->
                                  <div class="modal fade" id="editUserModal<?php echo $user['id']; ?>" tabindex="-1" aria-labelledby="editUserModalLabel<?php echo $user['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                      <div class="modal-content" style="background:#23203A;color:#fff;border-radius:14px;">
                                        <form method="POST">
                                          <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                          <input type="hidden" name="action" value="edit_user">
                                          <input type="hidden" name="edit_user_id" value="<?php echo $user['id']; ?>">
                                          <div class="modal-header" style="border-bottom:1px solid #444;">
                                            <h5 class="modal-title" id="editUserModalLabel<?php echo $user['id']; ?>">Benutzer bearbeiten</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="filter:invert(1);"></button>
                                          </div>
                                          <div class="modal-body">
                                            <div class="mb-2">
                                              <label class="admin-form-label">Name</label>
                                              <input type="text" class="form-control admin-form-control" name="edit_username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                                            </div>
                                            <div class="mb-2">
                                              <label class="admin-form-label">E-Mail</label>
                                              <input type="email" class="form-control admin-form-control" name="edit_email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                            </div>
                                            <div class="mb-2">
                                              <label class="admin-form-label">Rolle</label>
                                              <select class="form-select admin-form-control" name="edit_is_admin">
                                                <option value="0" <?php if(!$user['is_admin']) echo 'selected'; ?>>User</option>
                                                <option value="1" <?php if($user['is_admin']) echo 'selected'; ?>>Admin</option>
                                              </select>
                                            </div>
                                            <div class="mb-2">
                                              <label class="admin-form-label">2FA-Status</label>
                                              <div class="form-control admin-form-control" style="background:#23203A;color:#fff;" readonly>
                                                <?php echo !empty(
                                                  $user['totp_secret']) ? 'Aktiv' : 'Inaktiv'; ?>
                                              </div>
                                            </div>
                                            <div class="mb-2">
                                              <label class="admin-form-label">Neues Passwort (optional)</label>
                                              <input type="password" class="form-control admin-form-control" name="edit_password" placeholder="Nur ausfüllen, wenn ändern">
                                            </div>
                                          </div>
                                          <div class="modal-footer" style="border-top:1px solid #444;">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                                            <button type="submit" class="btn btn-primary">Speichern</button>
                                          </div>
                                        </form>
                                      </div>
                                    </div>
                                  </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- Weitere Cards für Bots, Einnahmen, Audit-Log, etc. im gleichen Stil ... -->
    <script>
    // Benutzer-Auswahl: Felder automatisch füllen
    document.addEventListener('DOMContentLoaded', function() {
        const select = document.getElementById('editUserSelect');
        const username = document.getElementById('edit_username');
        const email = document.getElementById('edit_email');
        const is_admin = document.getElementById('edit_is_admin');
        const user_id = document.getElementById('edit_user_id');
        select.addEventListener('change', function() {
            if (!select.value) {
                username.value = '';
                email.value = '';
                is_admin.checked = false;
                user_id.value = '';
                return;
            }
            const user = JSON.parse(select.value);
            username.value = user.username;
            email.value = user.email;
            is_admin.checked = user.is_admin == 1;
            user_id.value = user.id;
        });
    });
    </script>
    <!-- Einnahmen hinzufügen & Tabelle -->
    <div class="admin-section-card">
        <div class="admin-section-title"><i class="fas fa-euro-sign"></i> Einnahmen</div>
        <div class="table-responsive">
            <table class="table admin-table">
                <thead>
                    <tr><th>Betrag</th><th>Beschreibung</th><th>Benutzer</th><th>Datum</th><th></th></tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr><td colspan="5" class="text-center text-muted">Keine Einnahmen vorhanden oder Fehler beim Laden!</td></tr>
                    <?php else: ?>
                        <?php foreach ($payments as $p): ?>
                            <tr>
                                <td><?php echo number_format($p['amount'], 2, ',', '.'); ?> €</td>
                                <td><?php echo htmlspecialchars($p['description']); ?></td>
                                <td><?php echo $p['username'] ? htmlspecialchars($p['username']) : '<span class="text-muted">-</span>'; ?></td>
                                <td><?php echo date('d.m.Y H:i', strtotime($p['created_at'])); ?></td>
                                <td><!-- Aktionen --></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- Benutzerkonten-Übersicht für Admins -->
    <div class="admin-section-card">
        <div class="admin-section-title"><i class="fas fa-users text-info"></i> Benutzerkonten Übersicht</div>
        <?php if (!empty($pw_reset_success)): ?><div class="alert alert-success"><?php echo $pw_reset_success; ?></div><?php endif; ?>
        <div class="table-responsive">
            <table class="table admin-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>E-Mail</th>
                        <th>2FA</th>
                        <th>Rolle</th>
                        <th>Registriert</th>
                        <th>Aktion</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <?php if (!empty($user['totp_secret'])): ?>
                                    <span class="text-success"><i class="fas fa-check-circle"></i> Aktiv</span>
                                <?php else: ?>
                                    <span class="text-danger"><i class="fas fa-times-circle"></i> Inaktiv</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($user['is_admin']): ?>
                                    <span class="admin-badge">Admin</span>
                                <?php else: ?>
                                    <span class="user-badge">User</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?></td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <input type="hidden" name="action" value="reset_password">
                                    <input type="hidden" name="reset_user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" class="btn btn-outline-warning btn-sm" onclick="return confirm('Passwort wirklich zurücksetzen?')">
                                        <i class="fas fa-key"></i> Passwort zurücksetzen
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- Alle Bots Übersicht (kompakt) -->
    <div class="admin-section-card">
      <div class="admin-section-title"><i class="fas fa-robot"></i> Alle Bots</div>
      <div class="table-responsive">
        <table class="table admin-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>User</th>
              <th>Kategorie</th>
              <th>Status</th>
              <th>Erstellt</th>
              <th>Aktion</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($bots)): ?>
              <tr><td colspan="6" class="text-center text-muted">Keine Bots vorhanden.</td></tr>
            <?php else: ?>
              <?php foreach ($bots as $bot): ?>
                <tr>
                  <td><?php echo htmlspecialchars($bot['name']); ?></td>
                  <td><?php echo htmlspecialchars($bot['username']); ?></td>
                  <td><?php echo htmlspecialchars($bot['category']); ?></td>
                  <td><?php echo htmlspecialchars($bot['status']); ?></td>
                  <td><?php echo date('d.m.Y H:i', strtotime($bot['created_at'])); ?></td>
                  <td>
                    <form method="POST" style="display:inline;">
                      <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                      <input type="hidden" name="action" value="delete_bot">
                      <input type="hidden" name="bot_id" value="<?php echo $bot['id']; ?>">
                      <button type="submit" class="btn btn-danger btn-admin-action" onclick="return confirm('Bot wirklich löschen?')">
                        <i class="fas fa-trash"></i>
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <!-- Restliche Cards für User/Bot-Übersicht, Verwaltung, etc. folgen im Flat-Design -->
    <div class="admin-section-card">
        <div class="admin-section-title"><i class="fas fa-database"></i> Backup-System</div>
        <?php if (!empty($backup_success)): ?><div class="alert alert-success fade-in">Backup erstellt: <a href="?download_backup=<?php echo $backup_success; ?>" class="text-accent">Backup herunterladen</a></div><?php endif; ?>
        <?php if (!empty($backup_error)): ?><div class="alert alert-danger fade-in"><?php echo $backup_error; ?></div><?php endif; ?>
        <?php if (!empty($restore_success)): ?><div class="alert alert-success fade-in"><?php echo $restore_success; ?></div><?php endif; ?>
        <?php if (!empty($restore_error)): ?><div class="alert alert-danger fade-in"><?php echo $restore_error; ?></div><?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-download"></i> Backup erstellen</button>
        </form>
        <form method="POST" enctype="multipart/form-data" class="mt-3">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <div class="mb-2"><input type="file" name="restorefile" accept=".zip" required class="form-control"></div>
            <button type="submit" class="btn btn-accent"><i class="fas fa-upload"></i> Backup wiederherstellen</button>
        </form>
    </div>
    <div class="admin-section-card">
        <div class="admin-section-title"><i class="fas fa-history"></i> Admin-Log</div>
        <div class="table-responsive">
            <table class="table admin-table">
                <thead><tr><th>Zeit</th><th>Admin</th><th>Aktion</th><th>Ziel</th><th>IP</th></tr></thead>
                <tbody>
                    <?php if (empty($admin_logs)): ?>
                        <tr><td colspan="5" class="text-center text-muted">Keine Log-Einträge vorhanden oder Fehler beim Laden!</td></tr>
                    <?php else: ?>
                        <?php foreach ($admin_logs as $log): ?>
                            <tr>
                                <td><?php echo date('d.m.Y H:i', strtotime($log['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($log['username']); ?></td>
                                <td><?php echo htmlspecialchars($log['action']); ?></td>
                                <td><?php echo htmlspecialchars($log['target_type'] . ' #' . $log['target_id']); ?></td>
                                <td><?php echo htmlspecialchars($log['ip']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<!-- Stelle sicher, dass Bootstrap JS für Modals eingebunden ist -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Einnahmen Chart
const incomeCtx = document.getElementById('incomeChart').getContext('2d');
new Chart(incomeCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($income_labels); ?>,
        datasets: [{
            label: '',
            data: <?php echo json_encode($income_data); ?>,
            backgroundColor: (ctx) => {
                const gradient = ctx.chart.ctx.createLinearGradient(0, 0, 0, 200);
                gradient.addColorStop(0, 'rgba(162,89,255,0.25)');
                gradient.addColorStop(1, 'rgba(93,80,254,0.10)');
                return gradient;
            },
            borderColor: '#A259FF',
            borderWidth: 3.5,
            tension: 0.45,
            fill: true,
            pointRadius: 4.5,
            pointBackgroundColor: '#4ADE80',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointHoverRadius: 7,
            pointHoverBackgroundColor: '#fff',
            pointHoverBorderColor: '#A259FF',
            pointHoverBorderWidth: 3,
        }]
    },
    options: {
        maintainAspectRatio: false,
        responsive: false,
        plugins: {
            legend: { display: false },
            title: {
                display: true,
                text: '📈 Einnahmen (€)',
                color: '#fff',
                font: { size: 20, weight: 'bold', family: 'Segoe UI, Arial' },
                padding: { top: 10, bottom: 10 }
            },
            tooltip: {
                backgroundColor: '#23203A',
                titleColor: '#A259FF',
                bodyColor: '#fff',
                borderColor: '#A259FF',
                borderWidth: 1.5,
                padding: 12,
                caretSize: 7,
                cornerRadius: 8,
                displayColors: false
            }
        },
        elements: {
            line: { borderWidth: 3.5, borderColor: '#A259FF' },
            point: { borderWidth: 2, borderColor: '#fff' }
        },
        scales: {
            x: {
                grid: { color: 'rgba(162,89,255,0.08)' },
                ticks: { color: '#bdbde6', font: { size: 12, weight: 'bold' } }
            },
            y: {
                grid: { color: 'rgba(162,89,255,0.08)' },
                ticks: { color: '#bdbde6', font: { size: 12, weight: 'bold' } },
                beginAtZero: true
            }
        },
        animation: {
            duration: 1200,
            easing: 'easeOutQuart'
        }
    }
});
// Bot-Status Chart
const botStatusCtx = document.getElementById('botStatusChart').getContext('2d');
new Chart(botStatusCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode($bot_status_labels); ?>,
        datasets: [{
            data: <?php echo json_encode($bot_status_data); ?>,
            backgroundColor: ['#4ADE80', '#F87171'],
            borderWidth: 2,
            borderColor: '#fff',
            cutout: '70%',
            radius: '70%'
        }]
    },
    options: {
        maintainAspectRatio: false,
        responsive: false,
        plugins: { legend: { labels: { color: '#fff', font: { size: 8 } } } },
    }
});
// Bots nach Kategorie Chart
const botCategoryCtx = document.getElementById('botCategoryChart').getContext('2d');
new Chart(botCategoryCtx, {
    type: 'pie',
    data: {
        labels: <?php echo json_encode(array_keys($bots_per_category)); ?>,
        datasets: [{
            data: <?php echo json_encode(array_values($bots_per_category)); ?>,
            backgroundColor: ['#667eea', '#764ba2', '#20c997', '#fd7e14', '#6f42c1', '#e83e8c', '#17a2b8'],
            borderWidth: 2,
            borderColor: '#fff',
            radius: '70%'
        }]
    },
    options: {
        maintainAspectRatio: false,
        responsive: false,
        plugins: { legend: { labels: { color: '#fff', font: { size: 8 } } } }
    }
});
</script>
<style>
@media (max-width: 1100px) {
  .admin-charts-row { flex-direction: column; align-items: center; gap: 1.5rem; }
  .admin-chart-card { max-width: 98vw !important; }
}
</style>
</body>
</html> 