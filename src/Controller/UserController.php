<?php

namespace App\Controller;

use App\Exception\BillingUnavailableException;
use App\Security\User;
use App\Service\BillingClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

#[Route('/user', name: 'app_user_')]
final class UserController extends AbstractController
{
    public function __construct(
        private readonly BillingClient $billingClient,
    ) {
    }
    #[Route('/profile', name: 'profile', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        try {
            $data = $this->billingClient->getCurrentUser($user->getApiToken());
        } catch (BillingUnavailableException) {
            $error = 'Данные баланса временно недоступны. Попробуйте обновить страницу или зайти позднее.';
        }

        return $this->render('user/profile.html.twig', [
            'balance' => $data['balance'] ?? null,
            'error' => $error ?? null,
        ]);
    }
}
