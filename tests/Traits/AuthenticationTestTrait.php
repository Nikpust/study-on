<?php

namespace App\Tests\Traits;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;

trait AuthenticationTestTrait
{
    protected function submitLoginForm(KernelBrowser $client, string $email, string $password = 'password'): void
    {
        $client->request('GET', '/login');
        self::assertResponseIsSuccessful();

        $client->submitForm('Войти', [
            'email' => $email,
            'password' => $password,
            '_remember_me' => false,
        ]);
    }

    private function login(KernelBrowser $client, string $email, string $password): Crawler
    {
        $this->submitLoginForm($client, $email, $password);

        self::assertResponseRedirects('/courses', 302);
        $crawler = $client->followRedirect();
        self::assertResponseIsSuccessful();

        return $crawler;
    }

    protected function loginAsUser(KernelBrowser $client): Crawler
    {
        return $this->login($client, 'test-user@mail.ru', 'password');
    }

    protected function loginAsAdmin(KernelBrowser $client): Crawler
    {
        return $this->login($client, 'test-admin@mail.ru', 'password');
    }
}
