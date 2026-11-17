-- Base dédiée aux tests automatisés (doctrine.yaml ajoute le suffixe « _test » en environnement test).
CREATE DATABASE IF NOT EXISTS covoiturage_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON covoiturage_test.* TO 'app'@'%';
FLUSH PRIVILEGES;
