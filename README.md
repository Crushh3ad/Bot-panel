# Discord Bot Hosting Platform

**Moderne PHP-Plattform zum Hosten und Verwalten von Discord Bots – mit Team-Management, Terminal, 2FA und stylischem Admin-Panel.**

---

## 🚀 Features

- **Benutzer- & Admin-Panel** mit 2FA, SQLite, CSRF-Schutz
- **Bots verwalten:** Hinzufügen, Start/Stop, Kategorien, Status, Team-Mitglieder
- **Team-Management:** Teile Bots mit anderen Usern (Bot-Sharing)
- **Terminal:** Echtzeit-Befehle für jeden Bot (start, stop, logs, ...)
- **Statistiken & Charts:** Einnahmen, Bot-Status, Kategorien
- **Backup & Restore** (Datenbank & Dateien)
- **Modernes UI:** Lila/Dark-Theme, responsive, große Buttons, Badges, Icons
- **Sicher:** Passwort-Hashing, Prepared Statements, Session- und Input-Validierung

---

## ⚡ Schnellstart

1. **Kopiere alle Dateien ins Webroot** (z.B. `/var/www/html`)
2. **Stelle sicher:** PHP 7.4+, SQLite3, Webserver (Apache/Nginx)
3. **Rufe die Seite im Browser auf** und registriere den ersten User
4. **Bots hinzufügen** und loslegen!

---

## 🖥️ Hauptfunktionen

- **Dashboard:** Übersicht aller Bots, Einnahmen, User, Admins
- **Bot-Card:** Start/Stop, Terminal, Konfiguration, Dateien, Team verwalten
- **Team-Modal:** Füge User zu Bots hinzu oder entferne sie
- **Admin-Panel:** User-Management, Backup, Audit-Log, Statistik-Widgets
- **Charts:** Einnahmen-Verlauf, Bot-Status, Kategorien (modern & kompakt)

---

## 📁 Wichtige Dateien

```
index.php         # Login & Registrierung
user_panel.php    # User-Einstellungen
admin.php         # Admin-Panel (User, Backup, Log)
dashboard.php     # Haupt-Dashboard & Bot-Übersicht
config.php        # Bot-Konfiguration
terminal.php      # Bot-Terminal (Echtzeit)
dateimanager.php  # Datei-Manager für Bots
bots.db           # SQLite-Datenbank
admin_style.css   # Admin-Panel Styles
style_dark_lila.css # Haupt-Styles
```

---

## 👥 Team-Management (Bot-Sharing)

- Klicke in der Bot-Card auf **„Team verwalten“**
- Füge User per Dropdown hinzu (oder entferne sie)
- Team-Mitglieder können den Bot sehen & steuern

---

## 🛡️ Sicherheit

- Passwörter: `password_hash()`
- 2FA für Admins
- CSRF-Token in allen Formularen
- Prepared Statements (SQL-Injection-Schutz)
- Session- und Input-Validierung

---

## 🐛 Troubleshooting

- **Datenbank-Probleme:** Schreibrechte für `bots.db` prüfen
- **Session-Probleme:** PHP-Session-Verzeichnis & Cookies prüfen
- **Terminal/JS:** Browser-Konsole auf Fehler prüfen

---

## 📄 Lizenz
MIT License

---

**Made with ❤️ for Discord Bot Teams** 

---

## 🛠️ Installation

1. **Repository herunterladen**
   ```bash
   git clone https://github.com/dein-repo/discord-bot-hosting.git
   cd discord-bot-hosting
   ```
2. **Dateien ins Webroot kopieren** (z.B. `/var/www/html` bei Apache)
3. **Berechtigungen setzen** (Linux):
   ```bash
   chmod 755 .
   chmod 644 *.php
   ```
4. **Stelle sicher:** PHP 7.4+, SQLite3, Webserver (Apache/Nginx) sind installiert und aktiviert
5. **Setup ausführen:**
   - Rufe im Browser `http://<dein-server>/setup.php` auf
   - Folge den Anweisungen (Datenbank & Admin werden angelegt)
   - **Lösche danach unbedingt die Datei `setup.php` aus dem Verzeichnis!**
6. **Rufe die Seite im Browser auf**
   - Registriere den ersten Benutzer (dieser wird Admin)
   - (Optional) 2FA für Admins einrichten
7. **Bots hinzufügen und loslegen!**

**Windows-Hinweis:**
- Kopiere alle Dateien in dein Webserver-Verzeichnis (z.B. `C:\xampp\htdocs\discord-bot-hosting`)
- Stelle sicher, dass PHP und SQLite3 aktiviert sind (XAMPP Control Panel)

--- 