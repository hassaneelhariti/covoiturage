<?php

namespace App\Tests\Integration;

use App\Entity\Trip;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/** Base MySQL réelle (base « covoiturage_test », cf. doctrine.yaml) : le schéma est recréé avant chaque test. */
abstract class IntegrationTestCase extends KernelTestCase
{
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get('doctrine.orm.entity_manager');
        $tool = new SchemaTool($this->em);
        $meta = $this->em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($meta);
        $tool->createSchema($meta);
        // Contraintes CHECK absentes du mapping ORM : mêmes définitions que la migration.
        $this->em->getConnection()->executeStatement('ALTER TABLE trip_leg ADD CONSTRAINT chk_leg_seats CHECK (seats_taken >= 0 AND seats_taken <= capacity)');
    }

    protected function persistTrip(Trip $trip): Trip
    {
        $this->em->persist($trip->getDriver());
        $this->em->persist($trip->getVehicle());
        foreach ($trip->getStops() as $stop) {
            $this->em->persist($stop->getCity());
        }
        $this->em->persist($trip);
        $this->em->flush();

        return $trip;
    }

    protected function persistUser(User $user): User
    {
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    protected function service(string $id): object
    {
        return static::getContainer()->get($id);
    }
}
