<?php
// Komplettes Setup-Skript für Discord Bot Hosting
// Führt alle notwendigen Schritte aus

echo "<h1>Discord Bot Hosting - Setup</h1>";

try {
    // 1. Datenbank erstellen
    echo "<h2>1. Erstelle Datenbank...</h2>";
    $db = new SQLite3('bots.db');
    echo "✅ Datenbank erstellt<br>";
    
    // 2. Tabellen erstellen
    echo "<h2>2. Erstelle Tabellen...</h2>";
    
    // Users Tabelle
    $db->exec('CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        email TEXT UNIQUE NOT NULL,
        is_admin INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');
    echo "✅ Users Tabelle erstellt<br>";
    
    // Bots Tabelle
    $db->exec('CREATE TABLE IF NOT EXISTS bots (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        name TEXT NOT NULL,
        token TEXT NOT NULL,
        category TEXT NOT NULL,
        status TEXT DEFAULT "stopped",
        pid INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users (id)
    )');
    echo "✅ Bots Tabelle erstellt<br>";
    
    // 3. Verzeichnisse erstellen
    echo "<h2>3. Erstelle Verzeichnisse...</h2>";
    $dirs = ['bots'];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
            echo "✅ Verzeichnis '$dir' erstellt<br>";
        } else {
            echo "✅ Verzeichnis '$dir' existiert bereits<br>";
        }
    }
    
    // 4. Admin erstellen
    echo "<h2>4. Erstelle Administrator...</h2>";
    
    // Prüfe ob bereits ein Admin existiert
    $admin_count = $db->querySingle('SELECT COUNT(*) FROM users WHERE is_admin = 1');
    
    if ($admin_count == 0) {
        $username = 'admin';
        $email = 'admin@example.com';
        $password = password_hash('admin123', PASSWORD_DEFAULT);
        
        $stmt = $db->prepare('INSERT INTO users (username, email, password, is_admin) VALUES (:username, :email, :password, 1)');
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $stmt->bindValue(':password', $password, SQLITE3_TEXT);
        $stmt->execute();
        
        echo "✅ Administrator erstellt<br>";
        echo "<strong>Login-Daten:</strong><br>";
        echo "Benutzername: admin<br>";
        echo "Passwort: admin123<br>";
        echo "E-Mail: admin@example.com<br>";
    } else {
        echo "✅ Administrator existiert bereits<br>";
    }
    
    // 5. Berechtigungen prüfen
    echo "<h2>5. Prüfe Berechtigungen...</h2>";
    
    if (is_writable('bots.db')) {
        echo "✅ Datenbank ist beschreibbar<br>";
    } else {
        echo "⚠️ Datenbank ist nicht beschreibbar - Berechtigungen prüfen!<br>";
    }
    
    if (is_writable('bots')) {
        echo "✅ Bots-Verzeichnis ist beschreibbar<br>";
    } else {
        echo "⚠️ Bots-Verzeichnis ist nicht beschreibbar - Berechtigungen prüfen!<br>";
    }
    
    // 6. PHP-Extensions prüfen
    echo "<h2>6. Prüfe PHP-Extensions...</h2>";
    
    $required_extensions = ['sqlite3', 'session'];
    foreach ($required_extensions as $ext) {
        if (extension_loaded($ext)) {
            echo "✅ Extension '$ext' geladen<br>";
        } else {
            echo "❌ Extension '$ext' fehlt!<br>";
        }
    }
    
    // 7. System-Befehle prüfen
    echo "<h2>7. Prüfe System-Befehle...</h2>";
    
    $commands = ['python3', 'node', 'pip3', 'npm'];
    foreach ($commands as $cmd) {
        $output = shell_exec("which $cmd 2>&1");
        if (!empty($output)) {
            echo "✅ '$cmd' verfügbar<br>";
        } else {
            echo "⚠️ '$cmd' nicht gefunden - Bots funktionieren möglicherweise nicht<br>";
        }
    }
    
    echo "<h2>✅ Setup abgeschlossen!</h2>";
    echo "<p><strong>Nächste Schritte:</strong></p>";
    echo "<ol>";
    echo "<li>Gehen Sie zu <a href='index.php'>index.php</a></li>";
    echo "<li>Melden Sie sich mit admin/admin123 an</li>";
    echo "<li>Erstellen Sie Ihren ersten Bot</li>";
    echo "<li>Löschen Sie diese setup.php nach der Verwendung</li>";
    echo "</ol>";
    
    echo "<p><strong>Wichtige Hinweise:</strong></p>";
    echo "<ul>";
    echo "<li>Ändern Sie das Admin-Passwort nach dem ersten Login</li>";
    echo "<li>Löschen Sie setup.php und create_admin.php nach der Verwendung</li>";
    echo "<li>Stellen Sie sicher, dass der Webserver Schreibrechte hat</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<h2>❌ Fehler beim Setup:</h2>";
    echo "<p><strong>Fehler:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Lösung:</strong></p>";
    echo "<ul>";
    echo "<li>Prüfen Sie die Webserver-Berechtigungen</li>";
    echo "<li>Stellen Sie sicher, dass PHP SQLite3 unterstützt</li>";
    echo "<li>Kontaktieren Sie den Server-Administrator</li>";
    echo "</ul>";
}
?> 