<?php
session_start();
require_once __DIR__ . '/vendor/autoload.php';
use OTPHP\TOTP;

if (!isset($_SESSION['2fa_user_id'])) {
    header('Location: index.php');
    exit;
}

$db = new SQLite3('bots.db');
$user_id = $_SESSION['2fa_user_id'];
$user = $db->querySingle('SELECT * FROM users WHERE id = ' . intval($user_id), true);
if (!$user || empty($user['totp_secret'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['totp_code'])) {
    $totp = TOTP::create($user['totp_secret']);
    if ($totp->verify($_POST['totp_code'])) {
        // 2FA erfolgreich, setze Session und leite weiter
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['is_admin'] = $user['is_admin'];
        unset($_SESSION['2fa_user_id']);
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Falscher Code!';
    }
}
?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>2FA Code eingeben</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>body{background:#181c24;color:#e9ecef;}</style>
</head>
<body>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card p-4" style="background:#232a36; color:#e9ecef; border-radius:18px;">
                <h3 class="mb-3">2FA Code eingeben</h3>
                <?php if (!empty($error)): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Code aus der Authenticator-App</label>
                        <input type="text" class="form-control" name="totp_code" required autocomplete="one-time-code">
                    </div>
                    <button type="submit" class="btn btn-success w-100">Anmelden</button>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html> 