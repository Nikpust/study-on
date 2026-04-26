<?php

namespace App\Tests\Mock;

use App\Exception\BillingUnavailableException;
use App\Service\BillingClient;

final readonly class BillingClientMock extends BillingClient
{
    public function __construct()
    {
        parent::__construct('http://test-billing');
    }

    public function auth(string $email, string $password): array
    {
        $this->ifBillingUnavailable($email);

        if ($email === 'test-user@mail.ru' && $password === 'password') {
            return [
                'token' => 'user-jwt-token',
                '_status_code' => 200,
            ];
        }

        if ($email === 'test-admin@mail.ru' && $password === 'password') {
            return [
                'token' => 'admin-jwt-token',
                '_status_code' => 200,
            ];
        }

        return [
            'message' => 'Invalid credentials.',
            '_status_code' => 401,
        ];
    }

    public function register(string $email, string $password): array
    {
        $this->ifBillingUnavailable($email);

        if ($email === 'exists@mail.ru') {
            return [
                'type' => 'https://symfony.com/errors/validation',
                'title' => 'Validation Failed',
                'status' => 422,
                'detail' => 'email: Указанный email уже зарегистрирован.',
                'violations' => [
                    [
                        'propertyPath' => 'email',
                        'title' => 'Указанный email уже зарегистрирован.',
                        'template' => 'Указанный email уже зарегистрирован.',
                        'parameters' => [
                            '{{ value }}' => '"exists@mail.ru"',
                        ],
                        'type' => 'urn:uuid:23bd9dbf-6b9b-41cd-a99e-4844bcf3077f',
                    ],
                ],
                '_status_code' => 422,
            ];
        }

        return [
            'roles' => ['ROLE_USER'],
            'token' => 'jwt-token',
            '_status_code' => 201,
        ];
    }

    public function getCurrentUser(string $token): array
    {
        return match ($token) {
            'user-jwt-token' => [
                'username' => 'test-user@mail.ru',
                'roles' => ['ROLE_USER'],
                'balance' => 7250.50,
                '_status_code' => 200,
            ],
            'admin-jwt-token' => [
                'username' => 'test-admin@mail.ru',
                'roles' => ['ROLE_SUPER_ADMIN'],
                'balance' => 0.0,
                '_status_code' => 200,
            ],
            default => [
                'message' => 'Invalid token.',
                '_status_code' => 401,
            ]
        };
    }

    private function ifBillingUnavailable(string $email): void
    {
        if ($email === 'billing-unavailable@mail.ru') {
            throw new BillingUnavailableException('Сервис временно недоступен.');
        }
    }
}
