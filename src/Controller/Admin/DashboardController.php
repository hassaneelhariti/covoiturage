<?php

namespace App\Controller\Admin;

use App\Entity\Booking;
use App\Entity\City;
use App\Entity\Review;
use App\Entity\Trip;
use App\Entity\User;
use App\Entity\Vehicle;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        return $this->render('@EasyAdmin/page/content.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()->setTitle('Covoiturage — Back-office');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToRoute('Retour au site', 'fa fa-home', 'app_home');
        yield MenuItem::linkToCrud('Villes', 'fa fa-city', City::class);
        yield MenuItem::linkToCrud('Utilisateurs', 'fa fa-users', User::class);
        yield MenuItem::linkToCrud('Véhicules', 'fa fa-car', Vehicle::class);
        yield MenuItem::linkToCrud('Trajets', 'fa fa-route', Trip::class);
        yield MenuItem::linkToCrud('Réservations', 'fa fa-ticket', Booking::class);
        yield MenuItem::linkToCrud('Avis', 'fa fa-star', Review::class);
    }
}
