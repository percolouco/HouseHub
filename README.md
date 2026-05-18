# 🦙 HouseHub OS

Système de gestion familiale sur-mesure — Budget, Calendrier, Voyages, Tâches, Courses, Notes, Impression 3D et plus.

Stack : PHP 8.2 natif · Vanilla JS · MariaDB 11 · Docker · Traefik

---

## 🚀 Déploiement (Docker)

**Prérequis** : Docker, Docker Compose, Traefik sur le réseau `proxy`.

```bash
# 1. Cloner le repo
git clone http://<gitea>/perco/HouseHub.git && cd HouseHub

# 2. Créer le fichier d'environnement
cp .env.example .env
# Éditer .env pour changer les mots de passe

# 3. Lancer
docker compose up -d --build

# 4. Accéder à l'app
# https://househub.nas.percolouco.com
```

La base de données meta (`househub_meta`) et les permissions sont **initialisées automatiquement** au premier démarrage via `docker/init/00-setup.sql`.

Aucun compte par défaut — le premier utilisateur s'inscrit via `/register.php` et choisit de créer un espace familial.

---

## 👥 Gestion multi-tenant

HouseHub supporte plusieurs familles indépendantes sur la même instance.

| URL | Description |
|-----|-------------|
| `/register.php` | Créer un compte + nouvel espace, ou rejoindre via code d'invitation |
| `/login.php` | Connexion |
| `/settings.php` | Profil, mot de passe, langue, modules actifs, code d'invitation |
| `/admin/` | Panneau d'administration (admin uniquement) |

### Inviter quelqu'un dans son espace
1. Aller sur `/settings.php` → copier le code d'invitation
2. La personne va sur `/register.php` → onglet "Rejoindre" → coller le code
3. Les deux comptes partagent le même environnement de données

### Rôle admin
Le premier utilisateur inscrit doit être promu admin manuellement :
```sql
UPDATE househub_meta.users SET is_admin = 1 WHERE username = 'ton_username';
```

---

## 🧩 Modules

Tous les modules sont activables/désactivables par famille depuis `/settings.php`.

| Module | Route | Description |
|--------|-------|-------------|
| 💰 Budget | `/budget.php` | Suivi mensuel, prévisionnel, épargne, import CSV bancaire |
| 📅 Calendrier | `/family-calendar.php` | Congés (CP/JRA/JA), modes de garde, planning hebdo, vacances scolaires |
| 🗓️ Calendrier iOS | `/calendar-ios.php` | Synchronisation CalDAV avec iPhone/iPad |
| 🏖️ Voyages | `/holidays.php` | Roadtrips, cartographie Leaflet, itinéraires OSRM, météo Open-Meteo |
| 🎁 Cadeaux | `/gift-list.php` | Listes Noël / anniversaires, intégration Tricount |
| 🚗 Garage | `/garage.php` | Véhicules, historique entretiens, pièces détachées |
| 🛒 Courses | `/groceries.php` | Liste de courses partagée en temps réel |
| ✅ Todo | `/todo.php` | Listes de tâches avec rappels quotidiens via Discord webhook |
| 📝 Mémo | `/memo.php` | Notes personnelles / familiales avec éditeur riche |
| 🖨️ PrintVault | `/printvault.php` | Gestionnaire de fichiers d'impression 3D (STL, 3MF, GCode) avec viewer Three.js |
| 📋 Planka | `/planka.php` | Kanban natif connecté à Planka — boards, listes, cartes, drag & drop, checklist |

---

## 📋 Planka

Module Kanban natif connecté à l'instance Planka du NAS — affichage et gestion directement dans HouseHub sans iframe.

- **Boards** : sélecteur de boards du projet Planka lié à la famille
- **Colonnes** : listes Planka affichées en colonnes scrollables
- **Cartes** : labels colorés, date d'échéance, checklist
- **Actions** : créer, modifier, supprimer des cartes ; drag & drop entre colonnes ; cocher les tâches
- **Par famille** : chaque espace HouseHub peut pointer vers son propre projet Planka
- **Paramètres** : bouton ⚙️ pour configurer le `project_id` Planka

---

## 🖨️ PrintVault

Module de gestion de fichiers d'impression 3D entièrement intégré à HouseHub.

- **Formats supportés** : STL (binaire/ASCII), 3MF, GCode
- **Viewer 3D** : Three.js (STL/3MF) et visualisation des trajectoires GCode avec dégradé de couleur par hauteur Z
- **Métadonnées GCode** : temps d'impression estimé, longueur filament, température buse/plateau
- **Stockage local** : fichiers sur le serveur, métadonnées en MariaDB (`pf_pv_*`)
- **Limite upload** : 300 Mo

---

## ✅ Todo — Rappels Discord

Le module Todo supporte des rappels quotidiens via un **webhook Discord** :

1. Aller sur `/todo.php` → ⚙️ → coller l'URL du webhook Discord
2. Configurer le cron sur l'hôte :

```bash
* * * * * docker exec househub php /var/www/html/cron/todo_notify.php >> /home/perco/househub_todo_cron.log 2>&1
```

Le cron tourne chaque minute et envoie une notification Discord à l'heure exacte configurée sur chaque tâche.

---

## 🛠️ Stack technique

| Composant | Technologie |
|-----------|-------------|
| Backend | PHP 8.2 (Apache) |
| Base de données | MariaDB 11 |
| Frontend | Vanilla JS, CSS custom (dark mode natif) |
| Viewer 3D | Three.js (ES modules via importmap CDN) |
| Cartographie | Leaflet + OSRM |
| Conteneurisation | Docker + Docker Compose |
| Reverse proxy | Traefik |
| Internationalisation | FR / CA |

---

## 📌 Versioning

| Version | Changements |
|---------|-------------|
| v2.1.0 | Module Planka : Kanban natif connecté à Planka via API proxy PHP |
| v2.0.0 | Module PrintVault : viewer 3D STL/3MF/GCode, stockage local, trajectoires GCode illimitées |
| v1.5.0 | Modules Todo (rappels Discord), Mémo, Courses (liste partagée) |
| v1.4.0 | Synchronisation CalDAV iOS |
| v1.3.0 | Optimisation SQL — suppression des requêtes N+1, temps de réponse ÷3 |
| v1.2.0 | Modernisation UI — Toasts, confirmations sur-mesure |
| v1.1.0 | Refonte expérience mobile (Bottom Sheets) |
| v1.0.0 | Version stable initiale — Budget, Calendrier, Voyages, Cadeaux, Garage |
