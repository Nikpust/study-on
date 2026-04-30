<?php

namespace App\Security;

use App\Dto\Security\LoginDto;
use App\Exception\BillingUnavailableException;
use App\Service\BillingClient;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class BillingAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly BillingClient $billingClient,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function authenticate(Request $request): Passport
    {
        $dto = new LoginDto();
        $dto->email = $request->getPayload()->getString('email');
        $dto->password = $request->getPayload()->getString('password');

        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $dto->email);

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            $fieldErrors = [];

            foreach ($errors as $error) {
                $fieldErrors[$error->getPropertyPath()][] = $error->getMessage();
            }

            $request->getSession()->set('login_validation_errors', $fieldErrors);

            throw new CustomUserMessageAuthenticationException('Проверьте введённые данные.');
        }

        $email = $dto->email;
        $password = $dto->password;
        $rememberMe = $request->getPayload()->getBoolean('_remember_me');

        try {
            $data = $this->billingClient->auth($email, $password);
        } catch (BillingUnavailableException) {
            throw new CustomUserMessageAuthenticationException(
                'Сервис временно недоступен. Попробуйте авторизоваться позднее.'
            );
        }

        if (($data['_status_code'] ?? 500) !== 200) {
            throw new CustomUserMessageAuthenticationException(
                $data['message'] ?? 'Ошибка авторизации'
            );
        }

        $apiToken = $data['token'] ?? null;
        $refreshToken = $data['refresh_token'] ?? null;
        if (!$apiToken || !$refreshToken) {
            throw new CustomUserMessageAuthenticationException(
                $data['message'] ?? 'Ошибка авторизации'
            );
        }

        if ($rememberMe) {
            $request->getSession()->set('refresh_token', $refreshToken);
        }

        $userLoader = function () use ($apiToken, $refreshToken) {
            try {
                $currentUser = $this->billingClient->getCurrentUser($apiToken);
            } catch (BillingUnavailableException) {
                throw new CustomUserMessageAuthenticationException(
                    'Сервис временно недоступен. Попробуйте авторизоваться позднее.'
                );
            }

            if (($currentUser['_status_code'] ?? 500) !== 200) {
                throw new CustomUserMessageAuthenticationException(
                    $currentUser['message'] ?? 'Ошибка авторизации'
                );
            }

            $user = new User();

            $user->setEmail($currentUser['username'] ?? '');
            $user->setRoles($currentUser['roles'] ?? []);
            $user->setApiToken($apiToken);
            $user->setRefreshToken($refreshToken);

            return $user;
        };

        return new SelfValidatingPassport(
            new UserBadge($email, $userLoader),
            [
                new CsrfTokenBadge('authenticate', $request->getPayload()->getString('_csrf_token')),
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $targetUrl = $this->getTargetPath($request->getSession(), $firewallName)
            ?? $this->urlGenerator->generate('app_course_index');

        $response = new RedirectResponse($targetUrl);

        $refreshToken = $request->getSession()->get('refresh_token');

        if ($refreshToken) {
            $response->headers->setCookie(
                Cookie::create('refresh_token')
                    ->withValue($refreshToken)
                    ->withExpires(strtotime('+1 month'))
                    ->withHttpOnly(true)
                    ->withSecure(false)
                    ->withSameSite('lax')
                    ->withPath('/')
            );
        }

        return $response;
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
