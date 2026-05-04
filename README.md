# 🦙 PachaFamily - Dashboard Familial Sur-Mesure

PachaFamily est une application web privée de gestion familiale. Développée sans framework lourd (Vanilla JS / PHP Natif), elle garantit des performances optimales, une maintenance simplifiée et une personnalisation totale.

## 🌟 Modules & Fonctionnalités

### 💰 1. Module Budget (`/modules/budget/`)

Gestion asynchrone (SPA-like) des finances de la famille.

- **Suivi Mensuel** : Saisie rapide des dépenses avec répartition automatique (Alex/Laia/Commun).
- **Budget Prévisionnel** : Configuration des salaires, charges fixes, et calcul des restes à vivre. Mode "Calculatrice" flottant intégré.
- **Épargne** : Suivi des cagnottes par personne et par catégorie (Vacances, Nens, Perso).
- **Récapitulatif** : Bilan annuel (À venir).

### 📅 2. Module Calendrier (`/modules/family-calendar/`)

Agenda partagé pour toute la famille.

- Affichage des événements et vacances scolaires.
- Gestion des jours de congés avec compteurs annuels par personne.

### 🌴 3. Module Voyages (`/modules/holidays/`)

Planificateur de roadtrips et vacances.

- **Cartographie** : Intégration de Leaflet.js.
- **Itinéraires** : Calcul des trajets via OSRM et géocodage via Nominatim.
- Checkpoints, notes et liaisons directes avec le budget d'épargne.

### 🎁 4. Module Cadeaux (`/modules/gift-list/`)

Listes de souhaits pour les événements (Noël, Anniversaires, Rois Mages...).

---

## 🚀 Plan d'Optimisation & Debugging (Roadmap)

Cette section trace les étapes pour épurer la dette technique et optimiser l'application.

### 🟢 Phase 1 : Quick Wins (Gains rapides)

- [x] **Refonte Asynchrone Budget** : Remplacement des formulaires classiques par des appels `fetch()` silencieux sur `suivi.php`, `budget_prev.php` et `epargne.php`.
- [ ] **Nettoyage des Vues** : Extraire le JavaScript et le CSS encore présents dans les fichiers `.php` vers leurs fichiers `.js` et `.css` dédiés.
- [ ] **Corrections des liens GET** : Remplacer les `<a href="?action=delete">` par des `<button onclick="deleteItem()">` asynchrones partout.

### 🟡 Phase 2 : Robustesse & UX (Effort moyen)

- [ ] **Déploiement des Tests E2E** : Étendre `test-engine.js` pour couvrir le Calendrier et les Voyages.
- [ ] **Uniformisation Mobile** : Vérifier tous les `hover` et `z-index` pour garantir que les actions (édition/suppression) soient cliquables sur écrans tactiles.
- [ ] **Gestion des Erreurs JSON** : Appliquer systématiquement le `.text()` avant `JSON.parse()` dans les fetchs pour capturer les erreurs PHP (Warnings).

### 🔴 Phase 3 : Architecture & Performances (Heavy lifting)

- [ ] **Optimisation SQL (N+1)** : Réviser les requêtes du module Calendrier pour réduire le nombre d'appels à la base de données.
- [ ] **Mise en cache API Map** : Implémenter un système de cache local pour les requêtes OSRM/Nominatim afin d'éviter les limites de requêtes (Rate Limits) sur le module Voyages.
- [ ] **Routeur PHP Global** : Remplacer la navigation par `?tab=` par un routeur natif centralisé (Front Controller).

---

## 🏷️ Patch / Versions

- **v1.1.0 - Le Grand Bond Asynchrone** (Mai 2026) : Transformation des vues Budget (Prévisionnel & Épargne) en interfaces asynchrones (Fetch API). Création d'un moteur de tests E2E maison (`test-engine.js`). Résolution des conflits de `z-index` sur mobile. Sécurisation des JSON face aux Warnings PHP.
- **v1.0.0 - MVP Initial** : Mise en place de la structure de base, base de données MariaDB, modules Budget (Suivi), Calendrier, Cadeaux et Voyages (Leaflet).
