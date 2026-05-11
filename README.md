🦙 HouseHub OS
Système de gestion familiale sur-mesure : Budget, Calendrier, Voyages, Cadeaux et Garage.

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

## 👥 Gestion multi-tenant

HouseHub supporte plusieurs familles indépendantes sur la même instance :

| URL | Description |
|-----|-------------|
| `/register.php` | Créer un compte + nouvel espace, ou rejoindre un espace existant via code |
| `/login.php` | Connexion |
| `/settings.php` | Paramètres : profil, mot de passe, langue, modules actifs, code d'invitation |
| `/admin/` | Panneau d'administration (admin uniquement) |
| `/garage.php` | Module Garage — véhicules, entretiens, pièces |

### Inviter quelqu'un dans son espace
1. Aller sur `/settings.php` → copier le code d'invitation
2. La personne va sur `/register.php` → onglet "Rejoindre" → coller le code
3. Les deux comptes partagent le même environnement de données

### Rôle admin
Le premier utilisateur inscrit doit être promu admin manuellement :
```sql
UPDATE househub_meta.users SET is_admin = 1 WHERE username = 'ton_username';
```
L'admin peut ensuite promouvoir/désactiver d'autres utilisateurs depuis `/admin/`.

## 🧩 Modules

| Module | Route | Description |
|--------|-------|-------------|
| Calendrier | `/family-calendar.php` | Congés, modes de garde, planning hebdo |
| Budget | `/budget.php` | Suivi mensuel, prévisionnel, épargne |
| Voyages | `/holidays.php` | Roadtrips, cartographie, météo |
| Cadeaux | `/gift-list.php` | Noël, anniversaires, Tricount |
| Garage | `/garage.php` | Véhicules, entretiens, pièces détachées |

Les modules sont activables/désactivables par famille depuis `/settings.php`.

---

📝 Présentation du projet
HouseHub est une application web privée conçue pour centraliser l'organisation de la tribu. Elle repose sur une stack légère (PHP natif / Vanilla JS) pour garantir rapidité et portabilité, avec un support complet de l'internationalisation (FR/CA).

🛠️ Fonctionnalités Principales
💰 Module Budget
Suivi Mensuel : Interface de gestion des dépenses réelles avec import CSV bancaire et catégorisation automatique.

Budget Prévisionnel : Planification des revenus et répartition automatique vers les différents comptes (Commune, Livrets, Épargne).

Épargne : Visualisation des soldes bancaires et ventilation par postes de dépenses avec mode "Addition Rapide".

📅 Calendrier Familial
Planning Hebdomadaire : Gestion visuelle des modes de garde, des congés enfants et de la maladie.

Gestion des Congés (CP/JRA/JA) : Calcul automatique des soldes restants avec snapshots de correction.

Vacances Scolaires : Intégration automatique via l'API Data.Education (Zone C).

🏖️ Holidays & Roadtrips
Gestion de Voyages : Planification avec statuts (Brouillon, Confirmé, Terminé).

Moteur de Roadtrip : Cartographie interactive (Leaflet), itinéraires (OSRM) et gestion des étapes.

Météo Intelligente : Prévisions réelles ou archives historiques (Open-Meteo).

## 🚀 Roadmap d'Optimisation

- [x] **[Quickwin] Sécurisation AJAX (pachaFetch)**
- [x] **[Quickwin] Correction du piège "action"**
- [x] **[Structure] Centralisation des Constantes**
- [x] **[UX] Harmonisation Mobile**
- [x] **[Performance] Audit et Optimisation SQL**
- [ ] **[Backend] Système de Backup Automatique de la BDD**

Étape 3 : Performance & Backend
[ ] Audit SQL : Remplacement des requêtes en boucle par des jointures.

[ ] Cache Cleanup : Script de nettoyage pour le cache de géocodage.

📌 Patch / Versioning

- **v1.3.0 :** Optimisation Performance SQL. Suppression des requêtes en boucle (N+1) dans les modules Budget et Holidays. Temps de réponse divisé par 3 sur les gros historiques.

* **v1.2.0 :** Modernisation de l'UI. Remplacement des alertes systèmes par des Toasts et des confirmations sur-mesure.
* **v1.1.0 :** Refonte de l'expérience mobile (Bottom Sheets).
  v1.0.4 : Centralisation des constantes (IDs, devises) dans includes/config.php et injection dans window.CONFIG.

v1.0.3 : Migration du module Calendrier vers pachaFetch.

v1.0.2 : Correction systématique du bug form.action dans les fichiers Budget.

v1.0.1 : Création de l'utilitaire global pachaFetch dans le header.

v1.0.0 : Version stable initiale.
