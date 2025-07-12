<?php
session_start();
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
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

if (!empty($user['totp_secret'])) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['totp_secret'], $_POST['totp_code'])) {
    $totp = TOTP::create($_POST['totp_secret']);
    if ($totp->verify($_POST['totp_code'])) {
        $stmt = $db->prepare('UPDATE users SET totp_secret = :secret WHERE id = :id');
        $stmt->bindValue(':secret', $_POST['totp_secret'], SQLITE3_TEXT);
        $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
        $stmt->execute();
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Falscher Code!';
    }
} else {
    $totp = TOTP::create();
    $totp->setLabel($user['username']);
    $totp->setIssuer('Discord Bot Hosting');
    $secret = $totp->getSecret();
    $qrUri = $totp->getProvisioningUri();
    $renderer = new ImageRenderer(new RendererStyle(200), new SvgImageBackEnd());
    $writer = new Writer($renderer);
    $qrCode = $writer->writeString($qrUri);
}
?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>2FA einrichten</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>body{background:#181c24;color:#e9ecef;}</style>
</head>
<body>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card p-4" style="background:#232a36; color:#e9ecef; border-radius:18px;">
                <h3 class="mb-3">2FA einrichten</h3>
                <?php if (!empty($error)): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
                <form method="POST">
                    <input type="hidden" name="totp_secret" value="<?php echo isset($secret) ? htmlspecialchars($secret) : ''; ?>">
                    <div class="mb-3 text-center">
                        <div><?php echo isset($qrCode) ? $qrCode : ''; ?></div>
                        <div class="mt-2 small">Scanne den QR-Code mit Google Authenticator oder einer kompatiblen App.</div>
                        <div class="mt-2"><b>Secret:</b> <?php echo isset($secret) ? htmlspecialchars($secret) : ''; ?></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Bestätigungs-Code aus der App</label>
                        <input type="text" class="form-control" name="totp_code" required autocomplete="one-time-code">
                    </div>
                    <button type="submit" class="btn btn-success w-100">2FA aktivieren</button>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html> 