<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $auth): Response
    {
        return $this->render('security/login.html.twig', [
            'last_username' => $auth->getLastUsername(),
            'error' => $auth->getLastAuthenticationError(),
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): never
    {
        throw new \LogicException('Intercepté par le firewall.');
    }

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        if ($request->isMethod('POST')) {
            $email = mb_strtolower(trim((string) $request->request->get('email')));
            $first = trim((string) $request->request->get('firstName'));
            $last = trim((string) $request->request->get('lastName'));
            $password = (string) $request->request->get('password');
            $error = match (true) {
                !$this->isCsrfTokenValid('register', (string) $request->request->get('_token')) => 'Formulaire expiré, réessayez.',
                !filter_var($email, FILTER_VALIDATE_EMAIL) => 'Adresse e-mail invalide.',
                '' === $first || '' === $last => 'Prénom et nom obligatoires.',
                mb_strlen($password) < 8 => 'Mot de passe : 8 caractères minimum.',
                null !== $em->getRepository(User::class)->findOneBy(['email' => $email]) => 'Cette adresse est déjà utilisée.',
                default => null,
            };
            if (null === $error) {
                $user = new User($email, $first, $last);
                $user->setPhone(trim((string) $request->request->get('phone')) ?: null);
                $user->setPassword($hasher->hashPassword($user, $password));
                $em->persist($user);
                $em->flush();
                $this->addFlash('success', 'Compte créé : connectez-vous.');

                return $this->redirectToRoute('app_login');
            }
            $this->addFlash('error', $error);
        }

        return $this->render('security/register.html.twig');
    }
}
