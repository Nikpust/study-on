<?php

namespace App\Tests\Controller;

use App\Repository\CourseRepository;
use App\Tests\Traits\AuthenticationTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CourseControllerTest extends WebTestCase
{
    use AuthenticationTestTrait;

    public function testMainPageRedirectsToCourseIndex(): void
    {
        $client = static::createClient();

        $client->request('GET', '/');
        self::assertResponseRedirects('/courses', 301);
    }

    public function testIndexReturnsOkAndContainsCourses(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/courses');
        self::assertResponseStatusCodeSame(200);

        self::assertCount($this->getCountCourses(), $crawler->filter('.card-body'));
    }

    public function testShowDisplaysFreeCourseWithLessons(): void
    {
        $client = static::createClient();

        $courseId = $this->getCourseIdByCode('web-development-basics');
        $crawler = $client->request('GET', '/courses/' . $courseId);
        self::assertResponseStatusCodeSame(200);

        self::assertSelectorTextContains('h1', 'Основы веб-разработки');
        self::assertCount(4, $crawler->filter('.list-group-item'));
    }

    public function testIndexDisplaysCoursePricesForGuest(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/courses');
        self::assertResponseIsSuccessful();

        $text = $crawler->text();

        self::assertStringContainsString('459.0', $text);
        self::assertStringContainsString('99.5', $text);
    }

    public function testShowPaidCourseForGuestDisplaysLoginLink(): void
    {
        $client = static::createClient();

        $client->request('GET', $this->getCoursePageByCode('php-backend-development'));
        self::assertResponseIsSuccessful();

        self::assertSelectorExists('a[href="/login"]');
        self::assertSelectorTextContains('body', '459.0');
        self::assertSelectorNotExists('#course-payment-modal');
    }

    public function testShowBoughtCourseForUserDisplaysBoughtStatus(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client);

        $client->request('GET', $this->getCoursePageByCode('symfony-basics'));
        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains('span', 'Курс куплен');
        self::assertSelectorNotExists('#course-payment-modal');
    }

    public function testShowRentedCourseForUserDisplaysRentDate(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client);

        $client->request('GET', $this->getCoursePageByCode('rest-api-development'));
        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains('span', 'Ваша аренда действует до');
        self::assertSelectorNotExists('#course-payment-modal');
    }

    public function testShowUnpaidBuyCourseForUserDisplaysPaymentModal(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client);

        $client->request('GET', $this->getCoursePageByCode('php-backend-development'));
        self::assertResponseIsSuccessful();

        self::assertSelectorExists('button[data-bs-target="#course-payment-modal"]');
        self::assertSelectorNotExists('button[data-bs-target="#course-payment-modal"][disabled]');
        self::assertSelectorExists('#course-payment-modal');
        self::assertSelectorTextContains('body', '459.0');
    }

    public function testShowUnpaidRentCourseForUserDisplaysPaymentModal(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client);

        $client->request('GET', $this->getCoursePageByCode('database-design-postgresql'));
        self::assertResponseIsSuccessful();

        self::assertSelectorExists('button[data-bs-target="#course-payment-modal"]');
        self::assertSelectorNotExists('button[data-bs-target="#course-payment-modal"][disabled]');
        self::assertSelectorExists('#course-payment-modal');
        self::assertSelectorTextContains('body', '99.5');
    }

    public function testShowUnpaidCourseWithInsufficientFundsDisplaysDisabledPaymentButton(): void
    {
        $client = static::createClient();
        $this->loginAsPoorUser($client);

        $client->request('GET', $this->getCoursePageByCode('php-backend-development'));
        self::assertResponseIsSuccessful();

        self::assertSelectorExists('button[data-bs-target="#course-payment-modal"][disabled]');
        self::assertSelectorExists('#course-payment-modal');
    }

    public function testPayCourseSuccessfullyRedirectsBackToCourseWithFlashMessage(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client);

        $courseId = $this->getCourseIdByCode('php-backend-development');

        $crawler = $client->request('GET', '/courses/' . $courseId);
        self::assertResponseIsSuccessful();

        self::assertSelectorExists('button[data-bs-target="#course-payment-modal"]');
        $form = $crawler->filter('#course-payment-modal form')->form();

        $client->submit($form);
        self::assertResponseRedirects('/courses/' . $courseId, 303);

        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.alert-success', 'Курс успешно оплачен');
    }

    public function testPayCourseWithInsufficientFundsRedirectsBackToCourseWithError(): void
    {
        $client = static::createClient();
        $this->loginAsPoorUser($client);

        $courseId = $this->getCourseIdByCode('php-backend-development');

        $crawler = $client->request('GET', '/courses/' . $courseId);
        self::assertResponseIsSuccessful();

        self::assertSelectorExists('button[data-bs-target="#course-payment-modal"][disabled]');
        $form = $crawler->filter('#course-payment-modal form')->form();

        $client->submit($form);
        self::assertResponseRedirects('/courses/' . $courseId, 303);

        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.alert-danger', 'недостаточно средств');
    }

    public function testPayFreeCourseRedirectsBackToCourseWithError(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client);

        $courseId = $this->getCourseIdByCode('web-development-basics');

        $client->request('POST', '/courses/' . $courseId . '/pay');
        self::assertResponseRedirects('/courses/' . $courseId, 303);

        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.alert-danger', 'Бесплатный курс не требует оплаты');
    }

    public function testShowReturns404ForMissingCourse(): void
    {
        $client = static::createClient();

        $client->request('GET', '/courses/99999');
        self::assertResponseStatusCodeSame(404);
    }

    public function testNewPageReturns403ForBaseUser(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client);


        $client->request('GET', '/courses/new');
        self::assertResponseStatusCodeSame(403);
    }

    public function testEditPageReturns403ForBaseUser(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client);

        $courseId = $this->getCourseIdByCode('web-development-basics');
        $client->request('GET', '/courses/' . $courseId . '/edit');
        self::assertResponseStatusCodeSame(403);
    }

    public function testEditPageReturns404ForMissingCourseForAdmin(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);

        $client->request('GET', '/courses/99999/edit');
        self::assertResponseStatusCodeSame(404);
    }

    public function testCreateCourseWithValidData(): void
    {
        $client = static::createClient();
        $crawler = $this->loginAsAdmin($client);

        $countCourse = $this->getCountCourses();

        self::assertCount($countCourse, $crawler->filter('.card-body'));

        $client->clickLink('Создать новый курс');
        self::assertResponseIsSuccessful();

        $client->submitForm('Создать', [
            'course[code]' => 'new-course',
            'course[type]' => 'buy',
            'course[price]' => 399.9,
            'course[title]' => 'Новый тестовый курс',
            'course[description]' => 'Содержимое курса',
        ]);

        self::assertResponseRedirects('/courses', 303);
        $crawler = $client->followRedirect();
        self::assertResponseIsSuccessful();

        self::assertCount($countCourse + 1, $crawler->filter('.card-body'));
        self::assertStringContainsString('Новый тестовый курс', $crawler->filter('.row')->text());
    }

    #[DataProvider('invalidCourseDataProvider')]
    public function testCreateCourseWithInvalidData(array $formData, string $errorMessage): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);

        $client->clickLink('Создать новый курс');
        self::assertResponseIsSuccessful();

        $client->submitForm('Создать', $formData);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextSame('.invalid-feedback', $errorMessage);
    }

    public function testEditCourseWithValidData(): void
    {
        $client = static::createClient();
        $crawler = $this->loginAsAdmin($client);

        $link = $crawler->filter('.card-title a')->first()->link();
        $courseUrl = $link->getUri();

        $client->click($link);
        self::assertResponseIsSuccessful();

        $client->clickLink('Редактировать курс');
        self::assertResponseIsSuccessful();

        $client->submitForm('Сохранить', [
            'course[code]' => 'new-course',
            'course[type]' => 'buy',
            'course[price]' => 399.9,
            'course[title]' => 'Новый тестовый курс',
            'course[description]' => 'Содержимое курса',
        ]);

        self::assertResponseRedirects($courseUrl, 303);
        $client->followRedirect();
        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains('h1', 'Новый тестовый курс');
    }

    #[DataProvider('invalidCourseDataProvider')]
    public function testEditCourseWithInvalidData(array $formData, string $errorMessage): void
    {
        $client = static::createClient();
        $crawler = $this->loginAsAdmin($client);

        $link = $crawler->filter('.card-title a')->first()->link();
        $client->click($link);
        self::assertResponseIsSuccessful();

        $client->clickLink('Редактировать курс');
        self::assertResponseIsSuccessful();

        $client->submitForm('Сохранить', $formData);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextSame('.invalid-feedback', $errorMessage);
    }

    public function testDeleteCourse(): void
    {
        $client = static::createClient();
        $crawler = $this->loginAsAdmin($client);

        $countCourse = $this->getCountCourses();

        $link = $crawler->filter('.card-title a')->first()->link();
        $crawler = $client->click($link);
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('#delete-course-form')->form();
        $client->submit($form);

        self::assertResponseRedirects('/courses', 303);
        $crawler = $client->followRedirect();
        self::assertResponseIsSuccessful();

        self::assertCount($countCourse - 1, $crawler->filter('.card-body'));
    }

    public function testDeleteCourseReturns403ForBaseUser(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client);

        $courseId = $this->getCourseIdByCode('web-development-basics');

        $client->request('POST', '/courses/' . $courseId);
        self::assertResponseStatusCodeSame(403);
    }

    public function testCoursePageExistsActionButtonsForAdmin(): void
    {
        $client = static::createClient();
        $crawler = $this->loginAsAdmin($client);

        self::assertSelectorExists('a:contains("Создать новый курс")');

        $link = $crawler->filter('.card-title a')->first()->link();
        $client->click($link);
        self::assertResponseIsSuccessful();

        self::assertSelectorExists('a:contains("Редактировать курс")');
        self::assertSelectorExists('button:contains("Удалить курс")');
        self::assertSelectorExists('#delete-course-form');
        self::assertSelectorExists('a:contains("Добавить урок")');
    }

    public function testCoursePageNotExistsActionButtonsForBaseUser(): void
    {
        $client = static::createClient();
        $crawler = $this->loginAsUser($client);

        self::assertSelectorNotExists('a:contains("Создать новый курс")');

        $link = $crawler->filter('.card-title a')->first()->link();
        $client->click($link);
        self::assertResponseIsSuccessful();

        self::assertSelectorNotExists('a:contains("Редактировать курс")');
        self::assertSelectorNotExists('button:contains("Удалить курс")');
        self::assertSelectorNotExists('#delete-course-form');
        self::assertSelectorNotExists('a:contains("Добавить урок")');
    }

    public function testNewCoursePageContainsBillingFieldsForAdmin(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);

        $client->request('GET', '/courses/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('select[name="course[type]"]');
        self::assertSelectorExists('input[name="course[price]"]');
    }

    public function testEditCoursePageContainsBillingFieldsForAdmin(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);

        $courseId = $this->getCourseIdByCode('php-backend-development');

        $client->request('GET', '/courses/' . $courseId . '/edit');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('select[name="course[type]"]');
        self::assertSelectorExists('input[name="course[price]"]');
    }

    public static function invalidCourseDataProvider(): array
    {
        return [
            'code unique' => [
              [
                  'course[code]' => 'php-backend-development',
                  'course[type]' => 'buy',
                  'course[price]' => 100,
                  'course[title]' => 'Нормальный курс',
                  'course[description]' => 'Описание курса',
              ],
              'Курс с таким кодом уже существует',
            ],
            'code blank' => [
                [
                    'course[code]' => '',
                    'course[type]' => 'buy',
                    'course[price]' => 100,
                    'course[title]' => 'Нормальный курс',
                    'course[description]' => 'Описание курса',
                ],
                'Укажите код курса',
            ],
            'code short' => [
                [
                    'course[code]' => 'ab',
                    'course[type]' => 'buy',
                    'course[price]' => 100,
                    'course[title]' => 'Нормальный курс',
                    'course[description]' => 'Описание курса',
                ],
                'Код курса должен содержать не менее 3 символов',
            ],
            'code long' => [
                [
                    'course[code]' => str_repeat('a', 256),
                    'course[type]' => 'buy',
                    'course[price]' => 100,
                    'course[title]' => 'Нормальный курс',
                    'course[description]' => 'Описание курса',
                ],
                'Код курса должен содержать не более 255 символов',
            ],
            'title blank' => [
                [
                    'course[code]' => 'valid-code',
                    'course[type]' => 'buy',
                    'course[price]' => 100,
                    'course[title]' => '',
                    'course[description]' => 'Описание курса',
                ],
                'Укажите название курса',
            ],
            'title short' => [
                [
                    'course[code]' => 'valid-code',
                    'course[type]' => 'buy',
                    'course[price]' => 100,
                    'course[title]' => 'ab',
                    'course[description]' => 'Описание курса',
                ],
                'Название курса должно содержать не менее 3 символов',
            ],
            'title long' => [
                [
                    'course[code]' => 'valid-code',
                    'course[type]' => 'buy',
                    'course[price]' => 100,
                    'course[title]' => str_repeat('a', 256),
                    'course[description]' => 'Описание курса',
                ],
                'Название курса должно содержать не более 255 символов',
            ],
            'description long' => [
                [
                    'course[code]' => 'valid-code',
                    'course[type]' => 'buy',
                    'course[price]' => 100,
                    'course[title]' => 'Нормальный курс',
                    'course[description]' => str_repeat('a', 1001),
                ],
                'Описание курса не должно превышать 1000 символов',
            ],
            'paid course without price' => [
                [
                    'course[code]' => 'valid-code',
                    'course[type]' => 'buy',
                    'course[price]' => '',
                    'course[title]' => 'Нормальный курс',
                    'course[description]' => 'Описание курса',
                ],
                'Платный курс должен иметь положительную цену.',
            ],
            'free course with price' => [
                [
                    'course[code]' => 'valid-code',
                    'course[type]' => 'free',
                    'course[price]' => 100,
                    'course[title]' => 'Нормальный курс',
                    'course[description]' => 'Описание курса',
                ],
                'Бесплатный курс не должен иметь цену.',
            ],
            'paid course with zero price' => [
                [
                    'course[code]' => 'valid-code',
                    'course[type]' => 'buy',
                    'course[price]' => 0,
                    'course[title]' => 'Нормальный курс',
                    'course[description]' => 'Описание курса',
                ],
                'Платный курс должен иметь положительную цену.',
            ],
            'paid course with negative price' => [
                [
                    'course[code]' => 'valid-code',
                    'course[type]' => 'rent',
                    'course[price]' => -100,
                    'course[title]' => 'Нормальный курс',
                    'course[description]' => 'Описание курса',
                ],
                'Платный курс должен иметь положительную цену.',
            ],
        ];
    }

    private function getCourseIdByCode(string $code): int
    {
        $container = static::getContainer();

        $course = $container->get(CourseRepository::class)->findOneByCode($code);
        self::assertNotNull($course);

        return $course->getId();
    }

    private function getCoursePageByCode(string $code): string
    {
        return '/courses/' . $this->getCourseIdByCode($code);
    }

    private function getCountCourses(): int
    {
        $container = static::getContainer();

        return $container->get(CourseRepository::class)->count([]);
    }
}
