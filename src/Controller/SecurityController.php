<?php

namespace App\Controller;

use App\Dto\Security\RegisterDto;
use App\Exception\BillingUnavailableException;
use App\Form\RegisterType;
use App\Security\BillingAuthenticator;
use App\Security\User;
use App\Service\BillingClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;

class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // if ($this->getUser()) {
        //     return $this->redirectToRoute('target_path');
        // }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', ['last_username' => $lastUsername, 'error' => $error]);
    }

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        BillingClient $billingClient,
        UserAuthenticatorInterface $authenticator,
        BillingAuthenticator $billingAuthenticator,
    ): Response {
        $dto = new RegisterDto();

        $form = $this->createForm(RegisterType::class, $dto);
        $form->handleRequest($request);

        $errors = null;

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $response = $billingClient->register($dto->email, $dto->password);

                if (($response['_status_code'] ?? 500) !== 201) {
                    foreach ($response['violations'] ?? [] as $violation) {
                        if (!empty($violation['title'])) {
                            $errors[] = $violation['title'];
                        }
                    }

                    if ($errors === []) {
                        $errors[] = 'Ошибка регистрации.';
                    }
                } else {
                    $user = new User();

                    $user->setEmail($dto->email);
                    $user->setRoles($response['roles'] ?? []);
                    $user->setApiToken($response['token'] ?? null);

                    return $authenticator->authenticateUser(
                        $user,
                        $billingAuthenticator,
                        $request,
                    );
                }
            } catch (BillingUnavailableException) {
                $errors[] = 'Сервис временно недоступен. Попробуйте зарегистрироваться позднее.';
            }
        }

        $statusCode = Response::HTTP_OK;

        if ($errors !== [] || ($form->isSubmitted() && !$form->isValid())) {
            $statusCode = Response::HTTP_UNPROCESSABLE_ENTITY;
        }

        return $this->render('security/register.html.twig', [
            'form' => $form,
            'errors' => $errors,
        ], new Response(status: $statusCode));
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException(
            'This method can be blank - it will be intercepted by the logout key on your firewall.'
        );
    }
}
