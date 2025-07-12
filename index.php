<?php
session_start();

// Datenbankverbindung
$db = new SQLite3('bots.db');
// 2FA-Spalte hinzufügen (falls noch nicht vorhanden)
@$db->exec('ALTER TABLE users ADD COLUMN totp_secret TEXT');

// Tabelle erstellen falls nicht vorhanden
$db->exec('CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    email TEXT UNIQUE NOT NULL,
    is_admin INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)');

$db->exec('CREATE TABLE IF NOT EXISTS bots (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    token TEXT NOT NULL,
    category TEXT NOT NULL,
    status TEXT DEFAULT "stopped",
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id)
)');

$error = '';
$success = '';

if ($_POST) {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'login') {
            $username = $_POST['username'];
            $password = $_POST['password'];
            
            $stmt = $db->prepare('SELECT * FROM users WHERE username = :username');
            $stmt->bindValue(':username', $username, SQLITE3_TEXT);
            $result = $stmt->execute();
            $user = $result->fetchArray(SQLITE3_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['is_admin'] = $user['is_admin'];
                // force_pw_change prüfen
                if (!empty($user['force_pw_change']) && $user['force_pw_change'] == 1) {
                    header('Location: user_panel.php?force_pw_change=1');
                    exit;
                }
                // 2FA für Admins
                if ($user['is_admin']) {
                    if (empty($user['totp_secret'])) {
                        // 2FA-Setup anzeigen (QR-Code, Secret speichern)
                        header('Location: setup_2fa.php');
                        exit;
                    } else {
                        // 2FA-Code abfragen
                        $_SESSION['2fa_user_id'] = $user['id'];
                        header('Location: verify_2fa.php');
                        exit;
                    }
                }
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Falsche Anmeldedaten!';
            }
        } elseif ($_POST['action'] == 'register') {
            $username = $_POST['username'];
            $email = $_POST['email'];
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            
            try {
                $stmt = $db->prepare('INSERT INTO users (username, email, password) VALUES (:username, :email, :password)');
                $stmt->bindValue(':username', $username, SQLITE3_TEXT);
                $stmt->bindValue(':email', $email, SQLITE3_TEXT);
                $stmt->bindValue(':password', $password, SQLITE3_TEXT);
                $stmt->execute();
                $success = 'Registrierung erfolgreich! Du kannst dich jetzt anmelden.';
            } catch (Exception $e) {
                $error = 'Benutzername oder E-Mail bereits vorhanden!';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discord Bot Hosting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="style_dark_lila.css" rel="stylesheet">
</head>
<body>
    <div class="container fade-in">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-6 col-lg-5">
                <div class="auth-container p-5 shadow-accent" style="backdrop-filter: blur(18px); box-shadow: 0 8px 40px #A259FF33, 0 1.5px 8px #5D50FE22; border-radius: 28px; border: none; position: relative; overflow: hidden;">
                    <div class="text-center mb-4">
                        <i class="fab fa-discord fa-3x text-accent mb-3" style="animation: iconPop 1.2s cubic-bezier(.4,0,.2,1);"></i>
                        <h2 class="fw-bold text-accent" style="letter-spacing:1px;">Discord Bot Hosting</h2>
                        <p class="text-muted">Verwalte deine Discord Bots professionell</p>
                    </div>
                    <?php if ($error): ?>
                        <div class="alert alert-danger fade-in"><?php echo $error; ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success fade-in"><?php echo $success; ?></div>
                    <?php endif; ?>
                    <ul class="nav nav-tabs mb-3" id="authTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="login-tab" data-bs-toggle="tab" data-bs-target="#login" type="button" role="tab">Anmelden</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="register-tab" data-bs-toggle="tab" data-bs-target="#register" type="button" role="tab">Registrieren</button>
                        </li>
                    </ul>
                    <div class="tab-content mt-4" id="authTabsContent">
                        <!-- Login Tab -->
                        <div class="tab-pane fade show active" id="login" role="tabpanel">
                            <form method="POST" autocomplete="off">
                                <input type="hidden" name="action" value="login">
                                <div class="mb-4">
                                    <label for="login-username" class="form-label">Benutzername</label>
                                    <input type="text" class="form-control form-control-lg" id="login-username" name="username" required>
                                </div>
                                <div class="mb-4">
                                    <label for="login-password" class="form-label">Passwort</label>
                                    <input type="password" class="form-control form-control-lg" id="login-password" name="password" required>
                                </div>
                                <button type="submit" class="btn btn-primary btn-lg w-100" style="box-shadow:0 2px 16px #A259FF22;">Anmelden</button>
                            </form>
                        </div>
                        <!-- Register Tab -->
                        <div class="tab-pane fade" id="register" role="tabpanel">
                            <form method="POST" autocomplete="off">
                                <input type="hidden" name="action" value="register">
                                <div class="mb-4">
                                    <label for="register-username" class="form-label">Benutzername</label>
                                    <input type="text" class="form-control form-control-lg" id="register-username" name="username" required>
                                </div>
                                <div class="mb-4">
                                    <label for="register-email" class="form-label">E-Mail</label>
                                    <input type="email" class="form-control form-control-lg" id="register-email" name="email" required>
                                </div>
                                <div class="mb-4">
                                    <label for="register-password" class="form-label">Passwort</label>
                                    <input type="password" class="form-control form-control-lg" id="register-password" name="password" required>
                                </div>
                                <button type="submit" class="btn btn-primary btn-lg w-100" style="box-shadow:0 2px 16px #A259FF22;">Registrieren</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <style>
        @keyframes iconPop { 0% {transform:scale(0.7) rotate(-10deg); opacity:0;} 60% {transform:scale(1.15) rotate(8deg);} 100% {transform:none; opacity:1;} }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 