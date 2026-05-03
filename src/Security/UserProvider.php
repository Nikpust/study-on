<?php

namespace App\Security;

use App\Exception\BillingUnavailableException;
use App\Service\BillingClient;
use App\Service\JwtDecoder;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class UserProvider implements UserProviderInterface
{
    public function __construct(
        private readonly BillingClient $billingClient,
        private readonly RequestStack $requestStack,
        private readonly JwtDecoder $jwtDecoder,
    ) {
    }

    public function loadUserByIdentifier($identifier): UserInterface
    {
        $refreshToken = $this->requestStack->getCurrentRequest()?->cookies->get('refresh_token');
        if (!$refreshToken) {
            throw new UserNotFoundException('Пользователь не найден.');
        }

        try {
            $data = $this->billingClient->getTokenByRefreshToken($refreshToken);
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

            $currentUser = $this->billingClient->getCurrentUser($apiToken);
            if (($currentUser['_status_code'] ?? 500) !== 200) {
                throw new CustomUserMessageAuthenticationException(
                    $currentUser['message'] ?? 'Ошибка авторизации'
                );
            }

            if (($currentUser['username'] ?? null) !== $identifier) {
                throw new UserNotFoundException('Пользователь не найден.');
            }

            $user = new User();

            $user->setEmail($currentUser['username'] ?? '');
            $user->setRoles($currentUser['roles'] ?? []);
            $user->setApiToken($apiToken);
            $user->setRefreshToken($refreshToken);

            return $user;
        } catch (BillingUnavailableException) {
            throw new CustomUserMessageAuthenticationException(
                'Сервис временно недоступен. Попробуйте авторизоваться позднее.'
            );
        }
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', $user::class));
        }

        try {
            $tokenExpired = $this->jwtDecoder->isExpired($user->getApiToken());
        } catch (\InvalidArgumentException) {
            $tokenExpired = true;
        }

        if (!$tokenExpired) {
            return $user;
        }

        try {
            $data = $this->billingClient->getTokenByRefreshToken($user->getRefreshToken());
            if (($data['_status_code'] ?? 500) !== 200) {
                throw new CustomUserMessageAuthenticationException('Сессия истекла, выполните повторный вход');
            }

            $user->setApiToken($data['token'] ?? null);
            $user->setRefreshToken($data['refresh_token'] ?? null);

            return $user;
        } catch (BillingUnavailableException) {
            return $user;
        }
    }

    /**
     * Tells Symfony to use this provider for this User class.
     */
    public function supportsClass(string $class): bool
    {
        return User::class === $class || is_subclass_of($class, User::class);
    }
}
