<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Schéma initial. Écrit à la main depuis le mapping des entités ; vérifiez-le avec
 * `php bin/console doctrine:schema:validate` et, au besoin, régénérez-le avec `doctrine:migrations:diff`.
 * Les contraintes CHECK (places prises entre 0 et la capacité) sont un filet de sécurité MySQL 8.0.16+
 * contre toute surréservation, même en cas de bug applicatif.
 */
final class Version20261118090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Schéma initial du covoiturage régional';
    }

    public function up(Schema $schema): void
    {
        $t = 'DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB';
        $this->addSql("CREATE TABLE city (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, region VARCHAR(100) NOT NULL, latitude NUMERIC(9, 6) NOT NULL, longitude NUMERIC(9, 6) NOT NULL, UNIQUE INDEX uniq_city_name (name), PRIMARY KEY (id)) $t");
        $this->addSql("CREATE TABLE app_user (id INT AUTO_INCREMENT NOT NULL, password VARCHAR(255) NOT NULL, roles JSON NOT NULL, phone VARCHAR(30) DEFAULT NULL, driver_cancellations INT DEFAULT 0 NOT NULL, email VARCHAR(180) NOT NULL, first_name VARCHAR(80) NOT NULL, last_name VARCHAR(80) NOT NULL, UNIQUE INDEX uniq_user_email (email), PRIMARY KEY (id)) $t");
        $this->addSql("CREATE TABLE vehicle (id INT AUTO_INCREMENT NOT NULL, owner_id INT NOT NULL, make VARCHAR(60) NOT NULL, model VARCHAR(60) NOT NULL, color VARCHAR(40) NOT NULL, plate VARCHAR(20) NOT NULL, seats INT NOT NULL, UNIQUE INDEX uniq_vehicle_plate (plate), INDEX IDX_vehicle_owner (owner_id), PRIMARY KEY (id)) $t");
        $this->addSql("CREATE TABLE trip (id INT AUTO_INCREMENT NOT NULL, driver_id INT NOT NULL, vehicle_id INT NOT NULL, status VARCHAR(20) NOT NULL, route_geometry JSON DEFAULT NULL, note VARCHAR(255) DEFAULT NULL, seats_total INT NOT NULL, rate_per_km NUMERIC(5, 2) NOT NULL, INDEX IDX_trip_driver (driver_id), INDEX IDX_trip_vehicle (vehicle_id), PRIMARY KEY (id)) $t");
        $this->addSql("CREATE TABLE trip_stop (id INT AUTO_INCREMENT NOT NULL, trip_id INT NOT NULL, city_id INT NOT NULL, position INT NOT NULL, arrival_at DATETIME NOT NULL, departure_at DATETIME NOT NULL, meeting_point VARCHAR(150) DEFAULT NULL, INDEX IDX_stop_trip (trip_id), INDEX IDX_stop_city (city_id), UNIQUE INDEX uniq_stop_trip_position (trip_id, position), PRIMARY KEY (id)) $t");
        $this->addSql("CREATE TABLE trip_leg (id INT AUTO_INCREMENT NOT NULL, trip_id INT NOT NULL, from_stop_id INT NOT NULL, to_stop_id INT NOT NULL, seats_taken INT DEFAULT 0 NOT NULL, position INT NOT NULL, distance_km NUMERIC(7, 2) NOT NULL, price_mad INT NOT NULL, capacity INT NOT NULL, INDEX IDX_leg_trip (trip_id), INDEX IDX_leg_from (from_stop_id), INDEX IDX_leg_to (to_stop_id), UNIQUE INDEX uniq_leg_trip_position (trip_id, position), PRIMARY KEY (id)) $t");
        $this->addSql("CREATE TABLE booking (id INT AUTO_INCREMENT NOT NULL, trip_id INT NOT NULL, passenger_id INT NOT NULL, boarding_stop_id INT NOT NULL, alighting_stop_id INT NOT NULL, status VARCHAR(30) NOT NULL, cancelled_at DATETIME DEFAULT NULL, reference VARCHAR(20) NOT NULL, seats INT NOT NULL, total_price_mad INT NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX uniq_booking_reference (reference), INDEX IDX_booking_trip (trip_id), INDEX IDX_booking_passenger (passenger_id), INDEX IDX_booking_boarding (boarding_stop_id), INDEX IDX_booking_alighting (alighting_stop_id), PRIMARY KEY (id)) $t");
        $this->addSql("CREATE TABLE payment (id INT AUTO_INCREMENT NOT NULL, booking_id INT NOT NULL, refunded_mad INT DEFAULT 0 NOT NULL, status VARCHAR(30) NOT NULL, amount_mad INT NOT NULL, UNIQUE INDEX UNIQ_payment_booking (booking_id), PRIMARY KEY (id)) $t");
        $this->addSql("CREATE TABLE review (id INT AUTO_INCREMENT NOT NULL, trip_id INT NOT NULL, author_id INT NOT NULL, target_id INT NOT NULL, role VARCHAR(20) NOT NULL, rating INT NOT NULL, comment LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_review_trip (trip_id), INDEX IDX_review_author (author_id), INDEX IDX_review_target (target_id), UNIQUE INDEX uniq_review_trip_author_target (trip_id, author_id, target_id), PRIMARY KEY (id)) $t");

        $fk = [
            ['vehicle', 'fk_vehicle_owner', 'owner_id', 'app_user', ''],
            ['trip', 'fk_trip_driver', 'driver_id', 'app_user', ''],
            ['trip', 'fk_trip_vehicle', 'vehicle_id', 'vehicle', ''],
            ['trip_stop', 'fk_stop_trip', 'trip_id', 'trip', ' ON DELETE CASCADE'],
            ['trip_stop', 'fk_stop_city', 'city_id', 'city', ''],
            ['trip_leg', 'fk_leg_trip', 'trip_id', 'trip', ' ON DELETE CASCADE'],
            ['trip_leg', 'fk_leg_from', 'from_stop_id', 'trip_stop', ''],
            ['trip_leg', 'fk_leg_to', 'to_stop_id', 'trip_stop', ''],
            ['booking', 'fk_booking_trip', 'trip_id', 'trip', ''],
            ['booking', 'fk_booking_passenger', 'passenger_id', 'app_user', ''],
            ['booking', 'fk_booking_boarding', 'boarding_stop_id', 'trip_stop', ''],
            ['booking', 'fk_booking_alighting', 'alighting_stop_id', 'trip_stop', ''],
            ['payment', 'fk_payment_booking', 'booking_id', 'booking', ' ON DELETE CASCADE'],
            ['review', 'fk_review_trip', 'trip_id', 'trip', ''],
            ['review', 'fk_review_author', 'author_id', 'app_user', ''],
            ['review', 'fk_review_target', 'target_id', 'app_user', ''],
        ];
        foreach ($fk as [$table, $name, $col, $ref, $extra]) {
            $this->addSql("ALTER TABLE $table ADD CONSTRAINT $name FOREIGN KEY ($col) REFERENCES $ref (id)$extra");
        }

        // Filet de sécurité contre la surréservation (MySQL >= 8.0.16)
        $this->addSql('ALTER TABLE trip_leg ADD CONSTRAINT chk_leg_seats CHECK (seats_taken >= 0 AND seats_taken <= capacity)');
        $this->addSql('ALTER TABLE review ADD CONSTRAINT chk_review_rating CHECK (rating BETWEEN 1 AND 5)');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT chk_booking_seats CHECK (seats BETWEEN 1 AND 4)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['review', 'payment', 'booking', 'trip_leg', 'trip_stop', 'trip', 'vehicle', 'app_user', 'city'] as $table) {
            $this->addSql("DROP TABLE $table");
        }
        $this->addSql('SET FOREIGN_KEY_CHECKS = 1');
    }
}
