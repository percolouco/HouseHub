🦙 HouseHub OS
Système de gestion familiale sur-mesure : Budget, Calendrier, Voyages et Cadeaux.

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
