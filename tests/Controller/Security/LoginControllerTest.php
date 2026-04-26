<?php

namespace App\Tests\Controller\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class LoginControllerTest extends WebTestCase
{
    public function testLoginSuccessful(): void
    {
        $client = static::createClient();

        $this->login($client);

        self::assertResponseRedirects('/courses', 302);
        $client->followRedirect();

        self::assertSelectorTextContains('body', 'Курсы');
    }

    public function testLoginUnsuccessful(): void
    {
        $client = static::createClient();

        $this->login($client, 'test-user@mail.ru', 'not-password');

        self::assertResponseRedirects('/login', 302);
        $client->followRedirect();
        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains('.alert-danger', 'Invalid credentials.');
    }

    public function testLoginBillingUnavailableShowsError(): void
    {
        $client = static::createClient();

        $this->login($client, 'billing-unavailable@mail.ru');

        self::assertResponseRedirects('/login', 302);
        $client->followRedirect();
        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains('.alert-danger', 'Сервис временно недоступен.');
    }

    public function testRedirectToProfileWhenUserIsAuthorized(): void
    {
        $client = static::createClient();

        $this->login($client);

        self::assertResponseRedirects('/courses', 302);
        $client->followRedirect();
        self::assertResponseIsSuccessful();

        $client->request('GET', '/login');

        self::assertResponseRedirects('/user/profile', 302);
    }

    #[DataProvider('invalidCredentialsProvider')]
    public function testLoginWithInvalidCredentialsShowsError(array $formData): void
    {
        $client = static::createClient();

        $client->request('GET', '/login');
        self::assertResponseIsSuccessful();

        $client->submitForm('Войти', $formData);

        self::assertResponseRedirects('/login', 302);
        $client->followRedirect();
        self::assertResponseIsSuccessful();

        self::assertSelectorExists('.alert-danger');
    }

    public static function invalidCredentialsProvider(): array
    {
        return [
            'email blank' => [
                [
                    'email' => '',
                    'password' => 'password',
                ],
            ],
            'email incorrect' => [
                [
                    'email' => 'incorrect-email',
                    'password' => 'password',
                ],
            ],
            'password blank' => [
                [
                    'email' => 'test-user@mail.ru',
                    'password' => '',
                ],
            ],
        ];
    }

    private function login(
        KernelBrowser $client,
        string $email = 'test-user@mail.ru',
        string $password = 'password'
    ): void {
        $client->request('GET', '/courses');
        self::assertResponseIsSuccessful();

        $client->clickLink('Войти');
        self::assertResponseIsSuccessful();

        $client->submitForm('Войти', [
            'email' => $email,
            'password' => $password,
            '_remember_me' => false,
        ]);
    }
}
