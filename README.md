# Discord Bot Hosting Plattform 🚀

![PHP](https://img.shields.io/badge/PHP-7.4+-blue) ![SQLite](https://img.shields.io/badge/SQLite-3-orange) ![License](https://img.shields.io/badge/License-MIT-green) ![Status](https://img.shields.io/badge/Status-Active-brightgreen)

**Moderne PHP-Plattform zum Hosten und Verwalten von Discord Bots – inklusive Team-Management, Terminal, 2FA und stylischem Admin-Panel.**

---

## ✨ Features

* **Benutzer- & Admin-Panel** mit 2FA, SQLite und CSRF-Schutz
* **Bots verwalten:** Hinzufügen, Start/Stop, Kategorien, Status, Team-Mitglieder
* **Team-Management:** Bots mit anderen Usern teilen (Bot-Sharing)
* **Terminal:** Echtzeit-Befehle für jeden Bot (start, stop, logs …)
* **Statistiken & Charts:** Einnahmen, Bot-Status, Kategorien
* **Backup & Restore:** Datenbank & Dateien sichern
* **Modernes UI:** Lila/Dark-Theme, responsive, große Buttons, Badges, Icons
* **Sicherheit:** Passwort-Hashing, Prepared Statements, Session- und Input-Validierung

---

## ⚡ Schnellstart

1. Dateien ins Webroot kopieren (z.B. `/var/www/html`)
2. Prüfen: **PHP 7.4+**, **SQLite3**, Webserver (Apache/Nginx)
3. Browser öffnen & `setup.php` ausführen
4. Admin-Daten im Setup setzen (Benutzername, E-Mail, Passwort)
5. `setup.php` **unbedingt löschen**
6. Bots hinzufügen & loslegen!

[📦 Repository herunterladen](https://github.com/Crushh3ad/Bot-panel.git)

---

## 🖥️ Hauptfunktionen

* **Dashboard:** Übersicht über Bots, Einnahmen, User, Admins
* **Bot-Card:** Start/Stop, Terminal, Konfiguration, Dateien, Team verwalten
* **Team-Modal:** User zu Bots hinzufügen/entfernen
* **Admin-Panel:** User-Management, Backup, Audit-Log, Statistik-Widgets
* **Charts:** Einnahmen-Verlauf, Bot-Status, Kategorien

---

## 📁 Wichtige Dateien

```text
index.php              # Login & Registrierung
user_panel.php         # User-Einstellungen
admin.php              # Admin-Panel (User, Backup, Log)
dashboard.php          # Haupt-Dashboard & Bot-Übersicht
config.php             # Bot-Konfiguration
terminal.php           # Bot-Terminal (Echtzeit)
dateimanager.php       # Datei-Manager für Bots
bots.db                # SQLite-Datenbank
admin_style.css        # Admin-Panel Styles
style_dark_lila.css    # Haupt-Styles
setup.php              # Einmaliges Setup (Admin-Daten setzen!)
```

---

## 👥 Team-Management (Bot-Sharing)

* Klicke in der Bot-Card auf **„Team verwalten“**
* User per Dropdown hinzufügen oder entfernen
* Team-Mitglieder können den Bot sehen & steuern

---

## 🛡️ Sicherheit

* Passwörter mit `password_hash()` sichern
* 2FA für Admins
* CSRF-Token in allen Formularen
* Prepared Statements (SQL-Injection-Schutz)
* Session- und Input-Validierung

---

## 🐛 Troubleshooting

| Problem     | Lösung                                   |
| ----------- | ---------------------------------------- |
| Datenbank   | Schreibrechte für `bots.db` prüfen       |
| Session     | PHP-Session-Verzeichnis & Cookies prüfen |
| Terminal/JS | Browser-Konsole auf Fehler prüfen        |

---

## 🛠️ Installation & Setup

```bash
# Repository herunterladen
git clone https://github.com/Crushh3ad/Bot-panel.git
cd Bot-panel

# Berechtigungen setzen (Linux)
chmod 755 .
chmod 644 *.php
```

1. Dateien ins Webroot kopieren (z.B. `/var/www/html`)
2. PHP 7.4+, SQLite3 & Webserver aktiviert? ✔️
3. Setup starten:

  * **Admin-Daten in der → `setup.php` eintragen:** Benutzername, E-Mail, Passwort
   * Browser: `http://<dein-server>/setup.php` aufrufen
   * Setup abschließen → `setup.php` **unbedingt löschen**!
4. Seite im Browser aufrufen
5. Bots hinzufügen & loslegen!

**Windows-Hinweis:**

* Dateien nach `C:\xampp\htdocs\Bot-panel` kopieren
* PHP & SQLite3 im XAMPP Control Panel aktivieren
* Browser: `http://localhost/Bot-panel/setup.php`
* Admin-Daten setzen und Setup abschließen


## 📄 Lizenz

MIT License

Made with ❤️ für Discord Bot Teams


