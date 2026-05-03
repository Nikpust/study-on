<?php

namespace App\Tests\Mock;

use App\Exception\BillingUnavailableException;
use App\Service\BillingClient;

final readonly class BillingClientMock extends BillingClient
{
    private const COURSES = [
        'web-development-basics' => [
            'code' => 'web-development-basics',
            'type' => 'free',
        ],
        'php-backend-development' => [
            'code' => 'php-backend-development',
            'type' => 'buy',
            'price' => '459.0',
        ],
        'database-design-postgresql' => [
            'code' => 'database-design-postgresql',
            'type' => 'rent',
            'price' => '99.5',
        ],
        'symfony-basics' => [
            'code' => 'symfony-basics',
            'type' => 'buy',
            'price' => '249.0',
        ],
        'rest-api-development' => [
            'code' => 'rest-api-development',
            'type' => 'rent',
            'price' => '59.5',
        ],
        'docker-for-developers' => [
            'code' => 'docker-for-developers',
            'type' => 'buy',
            'price' => '200.0',
        ],
        'automated-testing-php' => [
            'code' => 'automated-testing-php',
            'type' => 'rent',
            'price' => '65.5',
        ],
        'frontend-with-bootstrap' => [
            'code' => 'frontend-with-bootstrap',
            'type' => 'free',
        ],
    ];

    private const TRANSACTIONS = [
        'user-jwt-token' => [
            [
                'id' => 1,
                'created_at' => '2026-05-01T10:00:00+00:00',
                'type' => 'payment',
                'course_code' => 'symfony-basics',
                'amount' => '249.0',
            ],
            [
                'id' => 2,
                'created_at' => '2026-05-02T10:00:00+00:00',
                'type' => 'payment',
                'course_code' => 'rest-api-development',
                'amount' => '59.5',
                'expires_at' => '2099-05-09T10:00:00+00:00',
            ],
            [
                'id' => 3,
                'created_at' => '2026-05-03T10:00:00+00:00',
                'type' => 'deposit',
                'course_code' => null,
                'amount' => '2000.0',
                'expires_at' => null,
            ],
        ],
        'admin-jwt-token' => [],
        'poor-user-jwt-token' => [],
        'jwt-token' => [],
    ];

    public function __construct()
    {
        parent::__construct('http://test-billing');
    }

    public function getTokenByRefreshToken(string $refreshToken): array
    {
        return match ($refreshToken) {
            'user-refresh-token' => [
                'token' => 'user-jwt-token',
                'refresh_token' => 'user-refresh-token',
                '_status_code' => 200,
            ],
            'admin-refresh-token' => [
                'token' => 'admin-jwt-token',
                'refresh_token' => 'admin-refresh-token',
                '_status_code' => 200,
            ],
            'poor-user-refresh-token' => [
                'token' => 'poor-user-jwt-token',
                'refresh_token' => 'poor-user-refresh-token',
                '_status_code' => 200,
            ],
            'refresh-token' => [
                'token' => 'jwt-token',
                'refresh_token' => 'refresh-token',
                '_status_code' => 200,
            ],
            default => [
                'message' => 'Invalid refresh token.',
                '_status_code' => 401,
            ],
        };
    }

    public function auth(string $email, string $password): array
    {
        $this->ifBillingUnavailable($email);

        return match (true) {
            $email === 'test-user@mail.ru' && $password === 'password' => [
                'token' => 'user-jwt-token',
                'refresh_token' => 'user-refresh-token',
                '_status_code' => 200,
            ],
            $email === 'poor-user@mail.ru' && $password === 'password' => [
                'token' => 'poor-user-jwt-token',
                'refresh_token' => 'poor-user-refresh-token',
                '_status_code' => 200,
            ],
            $email === 'test-admin@mail.ru' && $password === 'password' => [
                'token' => 'admin-jwt-token',
                'refresh_token' => 'admin-refresh-token',
                '_status_code' => 200,
            ],
            default => [
                'message' => 'Invalid credentials.',
                '_status_code' => 401,
            ],
        };
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
            'refresh_token' => 'refresh-token',
            '_status_code' => 201,
        ];
    }

    public function getCurrentUser(string $apiToken): array
    {
        return match ($apiToken) {
            'user-jwt-token' => [
                'username' => 'test-user@mail.ru',
                'roles' => ['ROLE_USER'],
                'balance' => 2000.0,
                '_status_code' => 200,
            ],
            'admin-jwt-token' => [
                'username' => 'test-admin@mail.ru',
                'roles' => [
                    'ROLE_USER',
                    'ROLE_SUPER_ADMIN'
                ],
                'balance' => 0.0,
                '_status_code' => 200,
            ],
            'poor-user-jwt-token' => [
                'username' => 'poor-user@mail.ru',
                'roles' => ['ROLE_USER'],
                'balance' => 0.0,
                '_status_code' => 200,
            ],
            'jwt-token' => [
                'username' => 'new-user@mail.ru',
                'roles' => ['ROLE_USER'],
                'balance' => 2000.0,
                '_status_code' => 200,
            ],
            default => [
                'message' => 'Invalid token.',
                '_status_code' => 401,
            ]
        };
    }

    public function getCourses(): array
    {
        return array_values(self::COURSES);
    }

    public function getCourse(string $courseCode): array
    {
        $course = self::COURSES[$courseCode] ?? null;

        if ($course === null) {
            return [
                'message' => 'Курс не найден.',
                '_status_code' => 404,
            ];
        }

        return [
            ...$course,
            '_status_code' => 200,
        ];
    }

    public function getTransactions(string $apiToken, array $filters = []): array
    {
        $transactions = self::TRANSACTIONS[$apiToken] ?? [
            'message' => 'JWT Token not found',
            '_status_code' => 401,
        ];

        if (isset($transactions['_status_code'])) {
            return $transactions;
        }

        return $this->applyTransactionFilters($transactions, $filters);
    }

    public function payCourse(string $apiToken, string $courseCode): array
    {
        if ($apiToken === '') {
            return [
                'message' => 'Требуется авторизация.',
                '_status_code' => 401,
            ];
        }

        if ($apiToken === 'poor-user-jwt-token') {
            return [
                'message' => 'На вашем счету недостаточно средств.',
                '_status_code' => 406,
            ];
        }

        return match ($courseCode) {
            'php-backend-development' => [
                'success' => true,
                'course_type' => 'buy',
                '_status_code' => 200,
            ],
            'database-design-postgresql' => [
                'success' => true,
                'course_type' => 'rent',
                'expires_at' => '2099-05-08T13:46:07+00:00',
                '_status_code' => 200,
            ],
            'web-development-basics' => [
                'message' => 'Бесплатный курс не требует оплаты.',
                '_status_code' => 400,
            ],
            default => [
                'message' => 'Курс не найден.',
                '_status_code' => 404,
            ],
        };
    }

    private function applyTransactionFilters(array $transactions, array $filters): array
    {
        if (($filters['type'] ?? null) !== null) {
            $transactions = array_filter(
                $transactions,
                static fn (array $transaction): bool => $transaction['type'] === $filters['type']
            );
        }

        if (($filters['course_code'] ?? null) !== null) {
            $transactions = array_filter(
                $transactions,
                static fn (array $transaction): bool => $transaction['course_code'] === $filters['course_code']
            );
        }

        if (in_array($filters['skip_expired'] ?? false, [true, 'true', 1, '1'], true)) {
            $now = new \DateTimeImmutable();

            $transactions = array_filter(
                $transactions,
                static function (array $transaction) use ($now): bool {
                    if (empty($transaction['expires_at'])) {
                        return true;
                    }

                    return new \DateTimeImmutable($transaction['expires_at']) > $now;
                }
            );
        }

        return array_values($transactions);
    }

    private function ifBillingUnavailable(string $email): void
    {
        if ($email === 'billing-unavailable@mail.ru') {
            throw new BillingUnavailableException('Сервис временно недоступен.');
        }
    }
}
