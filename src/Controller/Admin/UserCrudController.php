<?php

namespace App\Controller\Admin;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/** Les comptes se créent via l'inscription publique ; le back-office ajuste téléphone et rôles. */
class UserCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->disable(Action::NEW, Action::DELETE)->add(Action::INDEX, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield TextField::new('email')->hideOnForm();
        yield TextField::new('fullName', 'Nom')->hideOnForm();
        yield TextField::new('phone', 'Téléphone');
        yield ChoiceField::new('roles', 'Rôles')->setChoices(['Administrateur' => 'ROLE_ADMIN'])->allowMultipleChoices()->renderExpanded();
        yield IntegerField::new('driverCancellations', 'Annulations conducteur')->hideOnForm();
    }
}
