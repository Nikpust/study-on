<?php

namespace App\Controller;

use App\Exception\BillingUnavailableException;
use App\Repository\CourseRepository;
use App\Security\User;
use App\Service\BillingClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/user', name: 'app_user_')]
#[IsGranted('ROLE_USER')]
final class UserController extends AbstractController
{
    public function __construct(
        private readonly BillingClient $billingClient,
        private readonly CourseRepository $courseRepository,
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

            if (($data['_status_code'] ?? 500) !== 200) {
                $error = $data['message'] ?? 'Не удалось получить данные о балансе.';
            }
        } catch (BillingUnavailableException) {
            $error = 'Данные баланса временно недоступны. Попробуйте обновить страницу или зайти позднее.';
        }

        return $this->render('user/profile.html.twig', [
            'balance' => $data['balance'] ?? null,
            'error' => $error ?? null,
        ]);
    }

    #[Route('/transactions', name: 'transactions', methods: ['GET'])]
    public function transactions(): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        try {
            $transactions = $this->billingClient->getTransactions($user->getApiToken());

            if (($transactions['_status_code'] ?? 500) !== 200) {
                $this->addFlash(
                    'danger',
                    $transactions['message'] ?? 'Не удалось получить данные о балансе.'
                );
                return $this->redirectToRoute('app_user_profile');
            }

            unset($transactions['_status_code']);
        } catch (BillingUnavailableException $e) {
            $this->addFlash('danger', $e->getMessage());
            return $this->redirectToRoute('app_user_profile');
        }

        foreach ($transactions as $key => $transaction) {
            if (isset($transaction['course_code'])) {
                $transactions[$key]['course'] = $this->courseRepository->findOneBy([
                    'code' => $transaction['course_code']
                ]);
            }
        }

        return $this->render('user/transactions.html.twig', [
            'transactions' => $transactions,
        ]);
    }
}
