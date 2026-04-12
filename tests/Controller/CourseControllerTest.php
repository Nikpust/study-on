<?php

namespace App\Tests\Controller;

use App\Repository\CourseRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CourseControllerTest extends WebTestCase
{
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

        self::assertCount(3, $crawler->filter('.card-body'));
    }

    public function testShowDisplaysExistingCourseWithLessons(): void
    {
        $client = static::createClient();

        $courseId = $this->getCourseIdByCode('web-development-basics');
        $crawler = $client->request('GET', '/courses/' . $courseId);
        self::assertResponseStatusCodeSame(200);

        self::assertSelectorTextContains('h1', 'Основы веб-разработки');
        self::assertCount(4, $crawler->filter('.list-group-item'));
    }

    public function testShowReturns404ForMissingCourse(): void
    {
        $client = static::createClient();

        $client->request('GET', '/courses/99999');
        self::assertResponseStatusCodeSame(404);
    }

    public function testNewPageReturnsOk(): void
    {
        $client = static::createClient();

        $client->request('GET', '/courses/new');
        self::assertResponseStatusCodeSame(200);
    }

    public function testEditPageReturnsOkForExistingCourse(): void
    {
        $client = static::createClient();

        $courseId = $this->getCourseIdByCode('web-development-basics');
        $client->request('GET', '/courses/' . $courseId . '/edit');
        self::assertResponseStatusCodeSame(200);
    }

    public function testEditPageReturns404ForMissingCourse(): void
    {
        $client = static::createClient();

        $client->request('GET', '/courses/99999/edit');
        self::assertResponseStatusCodeSame(404);
    }

    public function testCreateCourseWithValidData(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/courses');
        self::assertResponseIsSuccessful();

        self::assertCount(3, $crawler->filter('.card-body'));

        $client->clickLink('Создать новый курс');
        self::assertResponseIsSuccessful();

        $client->submitForm('Создать', [
            'course[code]' => 'new-course',
            'course[title]' => 'Новый тестовый курс',
            'course[description]' => 'Содержимое курса',
        ]);

        self::assertResponseRedirects('/courses', 303);
        $crawler = $client->followRedirect();
        self::assertResponseIsSuccessful();

        self::assertCount(4, $crawler->filter('.card-body'));
        self::assertStringContainsString('Новый тестовый курс', $crawler->filter('.courses-grid')->text());
    }

    #[DataProvider('invalidCourseDataProvider')]
    public function testCreateCourseWithInvalidData(array $formData, string $errorMessage): void
    {
        $client = static::createClient();

        $client->request('GET', '/courses');
        $client->clickLink('Создать новый курс');
        self::assertResponseIsSuccessful();

        $client->submitForm('Создать', $formData);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextSame('.invalid-feedback', $errorMessage);
    }

    public function testEditCourseWithValidData(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/courses');
        self::assertResponseIsSuccessful();

        $link = $crawler->filter('.card-title a')->first()->link();
        $courseUrl = $link->getUri();

        $client->click($link);
        self::assertResponseIsSuccessful();

        $client->clickLink('Редактировать курс');
        self::assertResponseIsSuccessful();

        $client->submitForm('Сохранить', [
            'course[code]' => 'new-course',
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

        $crawler = $client->request('GET', '/courses');
        self::assertResponseIsSuccessful();

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

        $crawler = $client->request('GET', '/courses');
        $link = $crawler->filter('.card-title a')->first()->link();
        $crawler = $client->click($link);
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('#delete-course-form')->form();
        $client->submit($form);

        self::assertResponseRedirects('/courses', 303);
        $crawler = $client->followRedirect();
        self::assertResponseIsSuccessful();

        self::assertCount(2, $crawler->filter('.card-body'));
    }

    public static function invalidCourseDataProvider(): array
    {
        return [
            'code unique' => [
              [
                  'course[code]' => 'php-backend-development',
                  'course[title]' => 'Нормальный курс',
                  'course[description]' => 'Описание курса',
              ],
              'Курс с таким кодом уже существует',
            ],
            'code blank' => [
                [
                    'course[code]' => '',
                    'course[title]' => 'Нормальный курс',
                    'course[description]' => 'Описание курса',
                ],
                'Укажите код курса',
            ],
            'code short' => [
                [
                    'course[code]' => 'ab',
                    'course[title]' => 'Нормальный курс',
                    'course[description]' => 'Описание курса',
                ],
                'Код курса должен содержать не менее 3 символов',
            ],
            'code long' => [
                [
                    'course[code]' => str_repeat('a', 256),
                    'course[title]' => 'Нормальный курс',
                    'course[description]' => 'Описание курса',
                ],
                'Код курса должен содержать не более 255 символов',
            ],
            'title blank' => [
                [
                    'course[code]' => 'valid-code',
                    'course[title]' => '',
                    'course[description]' => 'Описание курса',
                ],
                'Укажите название курса',
            ],
            'title short' => [
                [
                    'course[code]' => 'valid-code',
                    'course[title]' => 'ab',
                    'course[description]' => 'Описание курса',
                ],
                'Название курса должно содержать не менее 3 символов',
            ],
            'title long' => [
                [
                    'course[code]' => 'valid-code',
                    'course[title]' => str_repeat('a', 256),
                    'course[description]' => 'Описание курса',
                ],
                'Название курса должно содержать не более 255 символов',
            ],
            'description long' => [
                [
                    'course[code]' => 'valid-code',
                    'course[title]' => 'Нормальный курс',
                    'course[description]' => str_repeat('a', 1001),
                ],
                'Описание курса не должно превышать 1000 символов',
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
}
