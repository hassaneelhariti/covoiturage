# Covoiturage régional — PFE Challenge EMC (sujet 3)

Plateforme de covoiturage entre villes marocaines : trajets avec étapes, réservation de portions de parcours,
paiement simulé, annulations encadrées, avis après trajet. Symfony 7.4, PHP 8.3, MySQL 8, Doctrine ORM + migrations.

## Niveaux visés
| Niveau | Contenu | État |
|---|---|---|
| 1 Modéliser | entités, migration, données de démonstration, back-office EasyAdmin (`/admin`) | écrit, à valider au premier lancement |
| 2 Règles | places par tronçon, verrou anti-surréservation, annulations conducteur / passager, avis après trajet, tests | écrit ; tests à exécuter |
| 3 Produit | inscription / connexion, recherche, réservation, espace conducteur, profils et avis | écrit |
| 4 API externes | Nominatim + OSRM : prix suggéré selon la distance routière, carte Leaflet | écrit |
| 5 Livrer | Docker Compose, `INSTALL.md` | écrit ; à tester sur machine propre |

## Lancer
Voir [INSTALL.md](INSTALL.md) : `docker compose up --build`, puis <http://localhost:8080>.

## Documentation
- [docs/MODELE.md](docs/MODELE.md) : modèle de données (Mermaid) et choix.
- [docs/DECISIONS.md](docs/DECISIONS.md) : réponses aux « questions à trancher » et journal des décisions.

## Structure
`src/Entity` (modèle) · `src/Service` (règles métier) · `src/Controller` (web + `Admin/`) · `templates/` ·
`migrations/` · `tests/Unit` (règles pures, sans base) · `tests/Integration` (MySQL réelle) · `docker/`.

## Reste à faire / limites connues
- Aucune de ces fonctions n'a encore été exécutée de bout en bout : lancer les tests et parcourir l'interface en priorité.
- Pas de test de concurrence en parallèle réel (le verrou est couvert par un test d'état périmé et la contrainte CHECK en base).
- Édition d'un trajet publié non exposée dans l'interface (la règle de blocage existe dans `TripService`).
- Pas de pagination, d'envoi d'e-mails ni de limitation du débit de connexion.
- Nominatim / OSRM : serveurs publics de démonstration, à remplacer en production.
