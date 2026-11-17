# Installation

## Prérequis
Docker et Docker Compose v2. Rien d'autre (PHP, Composer et MySQL tournent dans les conteneurs). Port 8080 libre.

## Lancer
```bash
git clone <url-du-depot> && cd <dossier>
docker compose up --build
```
Au premier démarrage le conteneur installe les dépendances, applique les migrations et charge les données de démonstration
(idempotent). Quand le journal affiche « apache2 -D FOREGROUND », ouvrez <http://localhost:8080>.

## Comptes de démonstration
| Rôle | E-mail | Mot de passe |
|---|---|---|
| Administrateur (back-office : `/admin`) | admin@covoiturage.test | admin123 |
| Conducteur | youssef@covoiturage.test, salma@covoiturage.test | password123 |
| Passager | imane@covoiturage.test, karim@covoiturage.test | password123 |
| Les deux | hamza@covoiturage.test | password123 |

Essayez : Agadir → Marrakech (trajet avec étape à Essaouira), ou Agadir → Essaouira.

## Tests
```bash
docker compose exec app vendor/bin/phpunit            # tout
docker compose exec app vendor/bin/phpunit --testsuite unit
```
Les tests d'intégration utilisent la base `covoiturage_test` (créée automatiquement au premier démarrage de MySQL).

## Réinitialiser
`docker compose down -v` supprime aussi la base ; relancez ensuite `docker compose up --build`.

## Dépannage
- **Port 8080 occupé** : changez `8080:80` dans `docker-compose.yml`.
- **« Base de données injoignable »** : MySQL met parfois 30 s à démarrer ; le conteneur réessaie 15 fois. Relancez `docker compose up`.
- **Base de tests absente** (volume créé avant l'ajout de `docker/mysql-init.sql`) : `docker compose down -v` puis relancer.
- **Cartes / prix suggéré sans distance routière** : Nominatim/OSRM injoignables (réseau). L'application retombe sur une estimation à vol d'oiseau ; le libellé l'indique.
- **`doctrine:schema:validate` signale des écarts d'index** : la migration initiale est écrite à la main ; régénérez-la avec `doctrine:migrations:diff` si besoin.
