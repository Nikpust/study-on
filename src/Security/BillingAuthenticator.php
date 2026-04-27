<?php

namespace App\Security;

use App\Dto\Security\LoginDto;
use App\Exception\BillingUnavailableException;
use App\Service\BillingClient;
use Psr\Cache\CacheItemPoolInterface;
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
        private readonly CacheItemPoolInterface $cache,
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

        $token = $data['token'] ?? null;
        if (!$token) {
            throw new CustomUserMessageAuthenticationException(
                $data['message'] ?? 'Ошибка авторизации'
            );
        }

        $userLoader = function () use ($token, $rememberMe) {
            try {
                $currentUser = $this->billingClient->getCurrentUser($token);
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
            $user->setApiToken($token);

            if ($rememberMe) {
                $item = $this->cache->getItem('billing_token_' . hash('sha256', $user->getEmail()));
                $item->set($token);
                $item->expiresAfter(3600);
                $this->cache->save($item);
            }

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
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->urlGenerator->generate('app_course_index'));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
