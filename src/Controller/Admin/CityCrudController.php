<?php

namespace App\Controller\Admin;

use App\Entity\City;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class CityCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return City::class;
    }

    public function createEntity(string $entityFqcn): City
    {
        return new City('', '', '0.000000', '0.000000');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield TextField::new('name', 'Ville');
        yield TextField::new('region', 'Région');
        yield TextField::new('latitude', 'Latitude');
        yield TextField::new('longitude', 'Longitude');
    }
}
