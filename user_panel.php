<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
require_once __DIR__ . '/vendor/autoload.php';
use OTPHP\TOTP;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Writer;

$db = new SQLite3('bots.db');
$user_id = $_SESSION['user_id'];
$user = $db->querySingle('SELECT * FROM users WHERE id = ' . intval($user_id), true);
if (!$user) { die('User not found'); }

// CSRF-Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('<div style="color:red;font-weight:bold">CSRF-Token ungültig. Bitte Seite neu laden.</div>');
    }
}

// force_pw_change prüfen
if (!empty($user['force_pw_change']) && $user['force_pw_change'] == 1) {
    $force_pw_change = true;
} else {
    $force_pw_change = false;
}
if (isset($_GET['force_pw_change'])) {
    $force_pw_change = true;
}

// Name/E-Mail/Passwort ändern
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $new_name = trim($_POST['username']);
    $new_email = trim($_POST['email']);
    $set_password = !empty($_POST['password']);
    try {
        if ($set_password) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $db->prepare('UPDATE users SET username = :username, email = :email, password = :password, force_pw_change = 0 WHERE id = :id');
            $stmt->bindValue(':password', $password, SQLITE3_TEXT);
        } else {
            $stmt = $db->prepare('UPDATE users SET username = :username, email = :email WHERE id = :id');
        }
        $stmt->bindValue(':username', $new_name, SQLITE3_TEXT);
        $stmt->bindValue(':email', $new_email, SQLITE3_TEXT);
        $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
        $stmt->execute();
        $_SESSION['username'] = $new_name;
        $user = $db->querySingle('SELECT * FROM users WHERE id = ' . intval($user_id), true);
        $profile_success = 'Profil erfolgreich aktualisiert!';
        // Nach erfolgreicher Passwortänderung Seite neu laden, damit force_pw_change entfällt
        if ($set_password && $force_pw_change) {
            header('Location: dashboard.php');
            exit;
        }
    } catch (Exception $e) {
        $profile_error = 'Fehler: Benutzername oder E-Mail existiert bereits!';
    }
}

// 2FA aktivieren
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'enable_2fa') {
    $totp = TOTP::create();
    $totp->setLabel($user['username']);
    $totp->setIssuer('Discord Bot Hosting');
    $secret = $totp->getSecret();
    if (isset($_POST['totp_code'])) {
        $totp_check = TOTP::create($secret);
        // Debug-Ausgabe:
        echo "<div style='color:yellow;background:#222;padding:10px;margin-bottom:10px'>";
        echo "Secret: " . htmlspecialchars($secret) . "<br>";
        echo "App-Code (eingegeben): " . htmlspecialchars($_POST['totp_code']) . "<br>";
        echo "Server-Code (aktuell): " . $totp_check->now() . "<br>";
        echo "Server-Zeit: " . date('Y-m-d H:i:s') . " (UTC)<br>";
        echo "</div>";
        if ($totp_check->verify($_POST['totp_code'])) {
            $stmt = $db->prepare('UPDATE users SET totp_secret = :secret WHERE id = :id');
            $stmt->bindValue(':secret', $secret, SQLITE3_TEXT);
            $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
            $stmt->execute();
            $user['totp_secret'] = $secret;
            $fa_success = '2FA erfolgreich aktiviert!';
        } else {
            $fa_error = 'Falscher Code!';
        }
    } else {
        // Zeige QR-Code
        $qrUri = $totp->getProvisioningUri();
        $renderer = new ImageRenderer(new RendererStyle(200), new SvgImageBackEnd());
        $writer = new Writer($renderer);
        $qrCode = $writer->writeString($qrUri);
        $show_2fa_setup = true;
    }
}
// 2FA deaktivieren
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'disable_2fa') {
    $stmt = $db->prepare('UPDATE users SET totp_secret = NULL WHERE id = :id');
    $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
    $stmt->execute();
    $user['totp_secret'] = null;
    $fa_success = '2FA wurde deaktiviert!';
}
ob_start();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>User Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style_dark_lila.css" rel="stylesheet">
</head>
<body>
<div class="container py-5 fade-in">
    <div class="row justify-content-center align-items-center min-vh-100">
        <div class="col-md-6 col-lg-5">
            <div class="auth-container p-5 shadow-accent" style="backdrop-filter: blur(14px); box-shadow: 0 8px 40px #A259FF33, 0 1.5px 8px #5D50FE22; border-radius: 28px; border: none; position: relative; overflow: hidden;">
                <?php if ($force_pw_change): ?>
                    <div class="alert alert-warning text-center fade-in" style="font-size:1.15rem;"><b>Wichtig:</b> Du musst dein Passwort ändern, bevor du fortfahren kannst.</div>
                <?php endif; ?>
                <h3 class="mb-4 text-center text-accent" style="font-weight:700;letter-spacing:0.5px;">Passwort ändern</h3>
                <?php if (!empty($profile_success)): ?><div class="alert alert-success text-center fade-in"><?php echo $profile_success; ?></div><?php endif; ?>
                <?php if (!empty($profile_error)): ?><div class="alert alert-danger text-center fade-in"><?php echo $profile_error; ?></div><?php endif; ?>
                <form method="POST" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="mb-4">
                        <label class="form-label">Benutzername</label>
                        <input type="text" class="form-control form-control-lg" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required readonly tabindex="-1">
                    </div>
                    <div class="mb-4">
                        <label class="form-label">E-Mail</label>
                        <input type="email" class="form-control form-control-lg" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required readonly tabindex="-1">
                    </div>
                    <div class="mb-4">
                        <label class="form-label" style="font-weight:600;">Neues Passwort <span class="text-danger">*</span></label>
                        <input type="password" class="form-control form-control-lg" name="password" required autofocus style="font-size:1.2rem;">
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg w-100" style="box-shadow:0 2px 16px #A259FF22;">Passwort jetzt ändern &rarr;</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php if (!$force_pw_change): ?>
<div class="container py-5 fade-in">
    <div class="row justify-content-center">
        <div class="col-md-7">
            <div class="card p-4 shadow-accent" style="backdrop-filter: blur(10px); border-radius: 22px; border: none; box-shadow: 0 4px 24px #A259FF22;">
                <h3 class="mb-3 text-accent">2-Faktor-Authentifizierung</h3>
                <?php if (!empty($fa_success)): ?><div class="alert alert-success fade-in"><?php echo $fa_success; ?></div><?php endif; ?>
                <?php if (!empty($fa_error)): ?><div class="alert alert-danger fade-in"><?php echo $fa_error; ?></div><?php endif; ?>
                <?php if (!empty($user['totp_secret'])): ?>
                    <div class="mb-3 text-success">2FA ist <b>aktiviert</b>.</div>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="disable_2fa">
                        <button type="submit" class="btn btn-danger">2FA deaktivieren</button>
                    </form>
                <?php elseif (!empty($show_2fa_setup)): ?>
                    <div class="mb-3 text-center">
                        <div><?php echo $qrCode; ?></div>
                        <div class="mt-2 small">Scanne den QR-Code mit Google Authenticator oder einer kompatiblen App.</div>
                        <div class="mt-2"><b>Secret:</b> <?php echo htmlspecialchars($secret); ?></div>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="enable_2fa">
                        <input type="hidden" name="totp_code" id="totp_code">
                        <input type="hidden" name="totp_secret" value="<?php echo htmlspecialchars($secret); ?>">
                        <div class="mb-3">
                            <label class="form-label">Bestätigungs-Code aus der App</label>
                            <input type="text" class="form-control" name="totp_code" required autocomplete="one-time-code">
                        </div>
                        <button type="submit" class="btn btn-success w-100">2FA aktivieren</button>
                    </form>
                <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="enable_2fa">
                        <button type="submit" class="btn btn-success">2FA aktivieren</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
</body>
</html> 