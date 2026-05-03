<?php

namespace App\Tests\Controller;

use App\Tests\Traits\AuthenticationTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class UserControllerTest extends WebTestCase
{
    use AuthenticationTestTrait;

    public function testProfileDisplaysCurrentUserBalance(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client);

        $crawler = $client->clickLink('Профиль');
        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains('h1', 'Профиль');

        $profileCard = $crawler->filter('.card-body')->text();
        self::assertStringContainsString('test-user@mail.ru', $profileCard);
        self::assertStringContainsString('2000', $profileCard);

        self::assertSelectorExists('a[href="/user/transactions"]');
    }

    public function testTransactionsPageDisplaysUserTransactions(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client);

        $client->clickLink('Профиль');
        self::assertResponseIsSuccessful();

        $crawler = $client->clickLink('История транзакций');
        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains('h1', 'История транзакций');
        self::assertCount(3, $crawler->filter('tbody tr'));

        $transactionsTable = $crawler->filter('tbody')->text();
        self::assertStringContainsString('Пополнение', $transactionsTable);
        self::assertStringContainsString('Покупка', $transactionsTable);
        self::assertStringContainsString('2000', $transactionsTable);
    }

    public function testTransactionsPageDisplaysCourseLinkForPaymentTransaction(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client);

        $crawler = $client->request('GET', '/user/transactions');
        self::assertResponseIsSuccessful();

        self::assertSelectorExists('tbody a:contains("Основы Symfony")');
        self::assertStringContainsString('249.0', $crawler->filter('tbody')->text());
    }

    public function testTransactionsPageFiltersByPaymentType(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client);

        $crawler = $client->request('GET', '/user/transactions');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('#transaction-filters-form')->form();
        $form['filter[type]'][1]->tick();

        $crawler = $client->submit($form);
        self::assertResponseIsSuccessful();

        self::assertCount(2, $crawler->filter('tbody tr'));

        $transactionsTable = $crawler->filter('tbody')->text();
        self::assertStringContainsString('Покупка', $transactionsTable);
        self::assertStringNotContainsString('Пополнение', $transactionsTable);
    }

    public function testTransactionsPageFiltersByDepositType(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client);

        $crawler = $client->request('GET', '/user/transactions');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('#transaction-filters-form')->form();
        $form['filter[type]'][0]->tick();

        $crawler = $client->submit($form);
        self::assertResponseIsSuccessful();

        self::assertCount(1, $crawler->filter('tbody tr'));

        $transactionsTable = $crawler->filter('tbody')->text();
        self::assertStringContainsString('Пополнение', $transactionsTable);
        self::assertStringNotContainsString('Покупка', $transactionsTable);
    }

    public function testTransactionsPageFiltersByCourseCode(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client);

        $crawler = $client->request('GET', '/user/transactions');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('#transaction-filters-form')->form([
            'filter[course_code]' => 'symfony-basics',
        ]);

        $crawler = $client->submit($form);
        self::assertResponseIsSuccessful();

        self::assertCount(1, $crawler->filter('tbody tr'));

        $transactionsTable = $crawler->filter('tbody')->text();
        self::assertStringContainsString('Основы Symfony', $transactionsTable);
    }
}
