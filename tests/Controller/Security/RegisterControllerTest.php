<?php

namespace App\Tests\Controller\Security;

use App\Tests\Traits\AuthenticationTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RegisterControllerTest extends WebTestCase
{
    use AuthenticationTestTrait;

    public function testRegisterSuccessful(): void
    {
        $client = static::createClient();

        $this->register($client);

        self::assertResponseRedirects('/courses', 302);
        $client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Курсы');
    }

    public function testRegisterExistingUserShowsError(): void
    {
        $client = static::createClient();

        $this->register($client, 'exists@mail.ru');

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.alert-danger', 'Указанный email уже зарегистрирован.');
    }

    public function testRegisterBillingUnavailableShowsError(): void
    {
        $client = static::createClient();

        $this->register($client, 'billing-unavailable@mail.ru');

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains(
            '.alert-danger',
            'Сервис временно недоступен. Попробуйте зарегистрироваться позднее.'
        );
    }

    public function testRedirectToProfileWhenUserIsAuthorized(): void
    {
        $client = static::createClient();

        $this->loginAsUser($client);

        $client->request('GET', '/register');
        self::assertResponseRedirects('/user/profile', 302);
    }

    #[DataProvider('invalidDataProvider')]
    public function testRegisterWithInvalidDataShowsFormError(array $formData, string $errorMessage): void
    {
        $client = static::createClient();

        $client->request('GET', '/register');
        self::assertResponseIsSuccessful();

        $client->submitForm('Зарегистрироваться', [
            'register[email]' => $formData['email'],
            'register[password]' => $formData['password'],
            'register[confirmPassword]' => $formData['confirmPassword'],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.invalid-feedback', $errorMessage);
    }

    public static function invalidDataProvider(): array
    {
        return [
            'email blank' => [
                [
                    'email' => '',
                    'password' => 'password',
                    'confirmPassword' => 'password',
                ],
                'Email не должен быть пустым.',
            ],
            'email incorrect' => [
                [
                    'email' => 'incorrect-email',
                    'password' => 'password',
                    'confirmPassword' => 'password',
                ],
                'Неверный email.',
            ],
            'password blank' => [
                [
                    'email' => 'test-user@mail.ru',
                    'password' => '',
                    'confirmPassword' => 'password',
                ],
                'Пароль не должен быть пустым.',
            ],
            'password short' => [
                [
                    'email' => 'test-user@mail.ru',
                    'password' => 'abc',
                    'confirmPassword' => 'abc',
                ],
                'Пароль должен быть не короче 6 символов.',
            ],
            'confirmPassword blank' => [
                [
                    'email' => 'test-user@mail.ru',
                    'password' => 'password',
                    'confirmPassword' => '',
                ],
                'Подтвердите указанный пароль.',
            ],
            'confirmPassword differ' => [
                [
                    'email' => 'test-user@mail.ru',
                    'password' => 'password',
                    'confirmPassword' => 'different-password',
                ],
                'Пароли не совпадают.',
            ],
        ];
    }

    private function register(
        KernelBrowser $client,
        string $email = 'new-user@mail.ru',
        string $password = 'password',
        string $confirmPassword = 'password',
    ): void {
        $client->request('GET', '/courses');
        self::assertResponseIsSuccessful();

        $client->clickLink('Зарегистрироваться');
        self::assertResponseIsSuccessful();

        $client->submitForm('Зарегистрироваться', [
            'register[email]' => $email,
            'register[password]' => $password,
            'register[confirmPassword]' => $confirmPassword,
        ]);
    }
}
