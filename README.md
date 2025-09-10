# Ja zum Leben – Website (Local Development mit DDEV)

Dieses Repository enthält die lokale Entwicklungsumgebung für die neue Website [ja-zum-leben.at](https://ja-zum-leben.at) auf Basis von WordPress, Divi (Theme) und einem Divi-Child-Theme. Die lokale Umgebung wird mit DDEV bereitgestellt und die Anwendung wird später in einen Kubernetes-Cluster deployt.

## Voraussetzungen
- Docker Desktop (aktuellste Version)
- DDEV (https://ddev.readthedocs.io/)
- mkcert (optional, für lokale HTTPS-Zertifikate; DDEV kann dies automatisieren)
- Git

## Schnellstart
1. Repository klonen
   ```bash
   git clone <REPO_URL>
   cd ja-zum-leben-divi5
   ```
2. DDEV starten
   ```bash
   ddev start
   ```
3. WordPress öffnen
   - Projekt-URL anzeigen: `ddev describe`
   - Typischerweise: `https://ja-zum-leben-divi5.ddev.site`

### Datenbank-Import (optional)
Wenn ein Datenbank-Dump vorhanden ist (z. B. aus Produktion/Staging):
```bash
ddev import-db --src path/to/dump.sql.gz
```
Alternativ Media-Import:
```bash
ddev import-files --src path/to/uploads.tar.gz
```

### WP-CLI verwenden
```bash
ddev wp plugin list
ddev wp theme list
```

## Projektstruktur (Auszug)
- `/.ddev/` – DDEV-Konfiguration für die lokale Umgebung
- `/wp-content/themes/divi-child/` – Child-Theme für projektspezifische Anpassungen
  - `/acf-json/` – ACF Local JSON, für Versionierung von Feldgruppen
- `/wp-content/themes/Divi/` – Divi Haupt-Theme (Upstream)
- `/wp-content/plugins/` – Plugins, inkl. Advanced Custom Fields (ACF)

## Entwicklungshinweise
- CSS/JS/Functions-Anpassungen erfolgen im Child-Theme: `wp-content/themes/divi-child/`
- ACF: Feldgruppen werden als JSON in `wp-content/themes/divi-child/acf-json/` versioniert. Änderungen im Backend können via ACF „Sync“ ins JSON übernommen werden.
- Divi Cache: Der Divi/ET-Cache liegt in `wp-content/et-cache/` und ist im `.gitignore` ausgeschlossen.
- WordPress Core-Dateien liegen im Repo, damit die lokale Laufzeitumgebung komplett ist. Sicherheitsrelevante Konfigurationen erfolgen via DDEV/Umgebungsvariablen.

## DDEV nützliche Befehle
- Projektstatus: `ddev describe`
- Logs (Web): `ddev logs -s web`
- PHP-Version/Extensions etc. werden über `.ddev/config.yaml` gesteuert
- Mailhog (lokale Mails): `ddev launch -p mailhog`

## Deployment nach Kubernetes (High-Level)
Das Produktiv-Deployment erfolgt in einen Kubernetes-Cluster. High-Level-Überblick:

1. Container-Image bauen
   - Dockerfile erstellen (PHP-FPM/NGINX + WordPress Codebase)
   - Abhängigkeiten installieren (falls Composer/Node benötigt werden)
   - Build/Copy des Child-Themes und ggf. generierter Assets

2. Persistenz sicherstellen
   - `wp-content/uploads` als PersistentVolumeClaim (PVC)
   - Optional separate PVCs für Cache/Temp, wenn sinnvoll

3. Konfiguration per Env/Secrets
   - Datenbank-Host, -Name, -User, -Passwort via Kubernetes Secrets/ConfigMaps
   - `WP_HOME`, `WP_SITEURL`, `DISALLOW_FILE_EDIT`, `FORCE_SSL_ADMIN` etc.

4. Ingress/SSL
   - Ingress-Ressource mit TLS-Zertifikat (z. B. via cert-manager/Let’s Encrypt)

5. Datenbank/Services
   - Externer Managed DB-Service oder eigener DB-Cluster (z. B. MariaDB/MySQL)
   - Caching/Session (optional): Redis/Memcached

6. CI/CD
   - Pipeline zum Bauen/Pushen des Images und Ausrollen der Manifeste (kubectl/Helm/Kustomize)

Wichtig: Uploads und generierte Dateien dürfen nicht im Container-Image schreibend abgelegt werden, sondern gehören in persistente Volumes.

## Backups und Daten-Migrationen
- Datenbank-Dumps exportieren/importieren z. B. mit `ddev export-db` / `ddev import-db`
- Media-Dateien per `ddev import-files`/`export-files` austauschen

## Sicherheit
- Production-Umgebungsvariablen niemals ins Repo committen
- Admin-Credentials und Secrets in Kubernetes als Secret-Objekte verwalten
- Schreibrechte für `wp-content` restriktiv, Code schreibgeschützt

## Lizenz / Rechte
Dieses Projekt ist proprietär. Rechte vorbehalten. Externe Themes/Plugins unterliegen deren jeweiligen Lizenzen.
