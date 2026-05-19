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

| URL             | Description                                                         |
| --------------- | ------------------------------------------------------------------- |
| `/register.php` | Créer un compte + nouvel espace, ou rejoindre via code d'invitation |
| `/login.php`    | Connexion                                                           |
| `/settings.php` | Profil, mot de passe, langue, modules actifs, code d'invitation     |
| `/admin/`       | Panneau d'administration (admin uniquement)                         |

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

| Module            | Route                  | Description                                                                     |
| ----------------- | ---------------------- | ------------------------------------------------------------------------------- |
| 💰 Budget         | `/budget.php`          | Suivi mensuel, prévisionnel, épargne, import CSV bancaire                       |
| 📅 Calendrier     | `/family-calendar.php` | Congés (CP/JRA/JA), modes de garde, planning hebdo, vacances scolaires          |
| 🗓️ Calendrier iOS | `/calendar-ios.php`    | Synchronisation CalDAV avec iPhone/iPad                                         |
| 🏖️ Voyages        | `/holidays.php`        | Roadtrips, cartographie Leaflet, itinéraires OSRM, météo Open-Meteo             |
| 🎁 Cadeaux        | `/gift-list.php`       | Listes Noël / anniversaires, intégration Tricount                               |
| 🚗 Garage         | `/garage.php`          | Véhicules, historique entretiens, pièces détachées                              |
| 🛒 Courses        | `/groceries.php`       | Liste de courses partagée en temps réel                                         |
| ✅ Todo           | `/todo.php`            | Listes de tâches avec rappels quotidiens via Discord webhook                    |
| 📝 Mémo           | `/memo.php`            | Notes personnelles / familiales avec éditeur riche                              |
| 🖨️ PrintVault     | `/printvault.php`      | Gestionnaire de fichiers d'impression 3D (STL, 3MF, GCode) avec viewer Three.js |
| 📋 Planka         | `/planka.php`          | Kanban natif connecté à Planka — boards, listes, cartes, drag & drop, checklist |

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

| Composant            | Technologie                              |
| -------------------- | ---------------------------------------- |
| Backend              | PHP 8.2 (Apache)                         |
| Base de données      | MariaDB 11                               |
| Frontend             | Vanilla JS, CSS custom (dark mode natif) |
| Viewer 3D            | Three.js (ES modules via importmap CDN)  |
| Cartographie         | Leaflet + OSRM                           |
| Conteneurisation     | Docker + Docker Compose                  |
| Reverse proxy        | Traefik                                  |
| Internationalisation | FR / CA                                  |

---

## 🔄 Mises à jour & Migrations SQL (Multi-Tenant)

L'architecture de HouseHub isole chaque foyer dans sa propre base de données (`househub_f1`, `househub_f2`, etc.). Lors de l'ajout de nouvelles fonctionnalités impliquant des changements de structure SQL, il faut suivre une double procédure :

1. **Pour les futures familles (Initialisation) :**
   Mettre à jour le fichier `docker/schema_family.sql`. Ce fichier est lu par `register.php` à la création d'un nouvel espace.
2. **Pour les familles existantes (Migration) :**
   Créer ou mettre à jour le script `migrate.php` à la racine du projet. Ce script boucle sur toutes les bases existantes référencées dans `househub_meta.families` pour y injecter les requêtes `ALTER TABLE` ou `CREATE TABLE`.
   - **Exécution** : Accéder à `https://votre-domaine.com/migrate.php` avec un navigateur.
   - ⚠️ **Sécurité** : Pensez à supprimer ou sécuriser l'accès à ce fichier après exécution en production.

---

## 🗺️ Roadmap de Variabilisation (Migration Multi-Tenant)

Afin de transformer complètement l'application héritée de Pacha Family en un produit générique et autonome pour chaque espace familial, les chantiers suivants doivent être menés.

🟢 Niveau 1 : Facile (Constantes et configurations globales)
[x] Configuration de base : Extraire la devise (CURRENCY) et la zone de vacances scolaires (ZONE_SCOLAIRE) du fichier config.php pour les stocker dans les paramètres d'espace en BDD.
[ ] Filtres API Éducation Nationale : Rendre dynamique la zone de l'API (zones LIKE '%Zone C%' dans family-calendar.js) pour s'adapter à la région de chaque foyer.
[ ] Identifiant Planka : Variabiliser le project_id Planka par défaut écrit en dur dans planka/api.php.
[ ] Suppression des IDs statiques : Supprimer les IDs parents fixes (ID_ALEX = 2, ID_LAIA = 3) dans config.php au profit d'une lecture dynamique basée sur les utilisateurs réels de la famille connectée.

🟡 Niveau 2 : Intermédiaire (Listes et dictionnaires en dur)
[ ] Gestion des membres (Cadeaux) : Dans gift-list.php, remplacer la liste d'enfants ($children) et d'adultes ($baseAdults, $extraAdults) codée en dur par une lecture dynamique des membres du foyer déclarés en BDD.
[ ] Occasions festives (Cadeaux) : Rendre configurables les occasions spéciales (TIO, NOEL, ROIS, ANNIV, SANT).
[ ] Configuration des catégories (Budget) : Dans budget/views/suivi.php, migrer le tableau $categoriesConfig (FMCG, Essence, École, etc.), leurs couleurs et leurs suggestions de magasins associés vers un stockage paramétrable.
[ ] Déductions du Reste à venir (Budget) : Rendre dynamiques les libellés de catégories ciblés pour le calcul automatique du reste à venir (Estimacio escola, Estimation gasolina, etc.).
[ ] Types de carburants (Garage) : Variabiliser la liste statique des carburants dans garage.php.

🔴 Niveau 3 : Complexe (Logique métier et Schéma SQL)
[ ] Refonte relationnelle de la répartition (Budget) : Modifier la structure de la table pf_alloc_values pour supprimer les colonnes figées amount_alex et amount_laia et basculer sur un modèle relationnel (alloc_id, user_id, amount) capable de gérer n'importe quel nombre d'adultes (1, 2, 3 ou plus).
[ ] Destinations de transferts (Budget) : Rendre dynamiques les cibles de virements prévisionnels (vers L.Pol, vers L.Pep, etc.) en fonction des comptes épargne créés par la famille.
[ ] Onglets d'Épargne : Générer dynamiquement les sous-onglets de la vue epargne.php à partir des membres réels au lieu des blocs statiques Alex, Laia et Nens.
[ ] Types de Garde (Calendrier) : Supprimer les types d'événements et compteurs codés en dur (OFF_CAROLE, EXTRA_OFF_CAROLE, CENTRE, AVIS, PEP_SICK) pour permettre à chaque foyer de définir ses propres modalités de garde et compteurs de congés associés.
[ ] Cycles de Congés : Rendre configurables les mois de début de calcul des droits aux congés (Août pour les CP, date d'anniversaire personnalisée pour les JA) par profil utilisateur.

---

## 📌 Versioning

| Version | Changements                                                                                |
| ------- | ------------------------------------------------------------------------------------------ |
| v2.2.0  | Multi-tenant (p.1) : Paramètres dynamiques du foyer (€, Zone) via BDD + script migration   |
| v2.1.0  | Module Planka : Kanban natif connecté à Planka via API proxy PHP                           |
| v2.0.0  | Module PrintVault : viewer 3D STL/3MF/GCode, stockage local, trajectoires GCode illimitées |
| v1.5.0  | Modules Todo (rappels Discord), Mémo, Courses (liste partagée)                             |
| v1.4.0  | Synchronisation CalDAV iOS                                                                 |
| v1.3.0  | Optimisation SQL — suppression des requêtes N+1, temps de réponse ÷3                       |
| v1.2.0  | Modernisation UI — Toasts, confirmations sur-mesure                                        |
| v1.1.0  | Refonte expérience mobile (Bottom Sheets)                                                  |
| v1.0.0  | Version stable initiale — Budget, Calendrier, Voyages, Cadeaux, Garage                     |
