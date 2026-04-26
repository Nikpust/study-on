<?php

namespace App\Tests\Controller;

use App\Entity\Course;
use App\Repository\CourseRepository;
use App\Tests\Traits\AuthenticationTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class LessonControllerTest extends WebTestCase
{
    use AuthenticationTestTrait;

    public function testShowDisplaysExistingLessonForAuthorizedUser(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client);

        $coursePage = $this->getCoursePageByCode('web-development-basics');
        $crawler = $client->request('GET', $coursePage);
        self::assertResponseIsSuccessful();

        $link = $crawler->filter('.list-group-item')->first()->link();
        $crawler = $client->click($link);
        self::assertResponseStatusCodeSame(200);

        $href = $crawler->filter('h4 a')->attr('href');
        self::assertSame($coursePage, $href);
    }

    public function testShowRedirectsUnauthorizedUserToLogin(): void
    {
        $client = static::createClient();

        $coursePage = $this->getCoursePageByCode('web-development-basics');
        $crawler = $client->request('GET', $coursePage);
        self::assertResponseIsSuccessful();

        $link = $crawler->filter('.list-group-item')->first()->link();
        $client->click($link);
        self::assertResponseRedirects('/login', 302);
    }

    public function testShow404ForMissingLesson(): void
    {
        $client = static::createClient();

        $client->request('GET', '/lessons/99999');
        self::assertResponseStatusCodeSame(404);
    }

    public function testNewPageReturns403ForBaseUser(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client);

        $courseId = $this->getCourseIdByCode('web-development-basics');
        $client->request('GET', '/lessons/new', ['course_id' => $courseId]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testNewPageReturns404ForMissingCourseForAdmin(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);

        $client->request('GET', '/lessons/new', ['course_id' => 99999]);
        self::assertResponseStatusCodeSame(404);
    }

    public function testEditPageReturns403ForBaseUser(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client);

        $lessonId = $this->getFirstLessonId('web-development-basics');
        $client->request('GET', '/lessons/' . $lessonId . '/edit');
        self::assertResponseStatusCodeSame(403);
    }

    public function testEditPageReturns404ForMissingLessonForAdmin(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);

        $client->request('GET', '/lessons/99999/edit');
        self::assertResponseStatusCodeSame(404);
    }

    public function testAddLessonWithValidDataForAdmin(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);

        $coursePage = $this->getCoursePageByCode('web-development-basics');
        $crawler = $client->request('GET', $coursePage);
        self::assertResponseIsSuccessful();

        $countLessonBefore = $crawler->filter('.list-group-item')->count();

        $client->clickLink('Добавить урок');
        self::assertResponseIsSuccessful();

        $client->submitForm('Добавить', [
            'lesson[title]' => 'Новый урок',
            'lesson[content]' => 'Описание урока',
            'lesson[number]' => 5,
        ]);

        self::assertResponseRedirects($coursePage, 303);
        $crawler = $client->followRedirect();
        self::assertResponseIsSuccessful();

        $countLessonAfter = $crawler->filter('.list-group-item')->count();
        self::assertSame($countLessonBefore + 1, $countLessonAfter);
    }

    #[DataProvider('invalidLessonDataProvider')]
    public function testAddLessonWithInvalidDataForAdmin(array $formData, string $errorMessage): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);

        $coursePage = $this->getCoursePageByCode('web-development-basics');
        $client->request('GET', $coursePage);
        self::assertResponseIsSuccessful();

        $client->clickLink('Добавить урок');
        self::assertResponseIsSuccessful();

        $client->submitForm('Добавить', $formData);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextSame('.invalid-feedback', $errorMessage);
    }

    public function testEditLessonWithValidDataForAdmin(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);

        $coursePage = $this->getCoursePageByCode('web-development-basics');
        $crawler = $client->request('GET', $coursePage);
        self::assertResponseIsSuccessful();

        $link = $crawler->filter('.list-group-item')->first()->link();
        $client->click($link);
        self::assertResponseStatusCodeSame(200);

        $client->clickLink('Редактировать урок');
        self::assertResponseIsSuccessful();

        $client->submitForm('Сохранить', [
            'lesson[title]' => 'Новый урок',
            'lesson[content]' => 'Описание урока',
            'lesson[number]' => 1,
        ]);

        self::assertResponseRedirects($coursePage, 303);
        $client->followRedirect();
        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains('.list-group-item span', 'Новый урок');
    }

    #[DataProvider('invalidLessonDataProvider')]
    public function testEditLessonWithInvalidDataForAdmin(array $formData, string $errorMessage): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);

        $coursePage = $this->getCoursePageByCode('web-development-basics');
        $crawler = $client->request('GET', $coursePage);
        self::assertResponseIsSuccessful();

        $link = $crawler->filter('.list-group-item')->first()->link();
        $client->click($link);
        self::assertResponseStatusCodeSame(200);

        $client->clickLink('Редактировать урок');
        self::assertResponseIsSuccessful();

        $client->submitForm('Сохранить', $formData);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextSame('.invalid-feedback', $errorMessage);
    }

    public function testDeleteLessonForAdmin(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);

        $coursePage = $this->getCoursePageByCode('web-development-basics');
        $crawler = $client->request('GET', $coursePage);
        self::assertResponseIsSuccessful();

        $countLessonsBefore = $crawler->filter('.list-group-item')->count();

        $link = $crawler->filter('.list-group-item')->first()->link();
        $crawler = $client->click($link);
        self::assertResponseStatusCodeSame(200);

        $form = $crawler->filter('#delete-lesson-form')->form();
        $client->submit($form);

        self::assertResponseRedirects($coursePage, 303);
        $crawler = $client->followRedirect();
        self::assertResponseIsSuccessful();

        $countLessonsAfter = $crawler->filter('.list-group-item')->count();

        self::assertSame($countLessonsBefore, $countLessonsAfter + 1);
    }

    public function testDeleteLessonReturns403ForBaseUser(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client);

        $coursePage = $this->getCoursePageByCode('web-development-basics');
        $crawler = $client->request('GET', $coursePage);
        self::assertResponseIsSuccessful();

        $link = $crawler->filter('.list-group-item')->first()->link();
        $client->click($link);
        self::assertResponseStatusCodeSame(200);

        $lessonId = $this->getFirstLessonId('web-development-basics');

        $client->request('POST', '/lessons/' . $lessonId);
        self::assertResponseStatusCodeSame(403);
    }

    public function testShowLessonPageExistsActionButtonsForAdmin(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);

        $coursePage = $this->getCoursePageByCode('web-development-basics');
        $crawler = $client->request('GET', $coursePage);
        self::assertResponseIsSuccessful();

        $link = $crawler->filter('.list-group-item')->first()->link();
        $client->click($link);
        self::assertResponseStatusCodeSame(200);

        self::assertSelectorExists('a:contains("Редактировать урок")');
        self::assertSelectorExists('button:contains("Удалить урок")');
        self::assertSelectorExists('#delete-lesson-form');
    }

    public function testShowLessonPageNotExistsActionButtonsForBaseUser(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client);

        $coursePage = $this->getCoursePageByCode('web-development-basics');
        $crawler = $client->request('GET', $coursePage);
        self::assertResponseIsSuccessful();

        $link = $crawler->filter('.list-group-item')->first()->link();
        $client->click($link);
        self::assertResponseStatusCodeSame(200);

        self::assertSelectorNotExists('a:contains("Редактировать урок")');
        self::assertSelectorNotExists('button:contains("Удалить урок")');
        self::assertSelectorNotExists('#delete-lesson-form');
    }

    public static function invalidLessonDataProvider(): array
    {
        return [
            'title blank' => [
                [
                    'lesson[title]' => '',
                    'lesson[content]' => 'Содержание урока',
                    'lesson[number]' => 5,
                ],
                'Укажите название урока',
            ],
            'title short' => [
                [
                    'lesson[title]' => 'ab',
                    'lesson[content]' => 'Содержание урока',
                    'lesson[number]' => 5,
                ],
                'Название урока должно содержать не менее 3 символов',
            ],
            'title long' => [
                [
                    'lesson[title]' => str_repeat('a', 256),
                    'lesson[content]' => 'Содержание урока',
                    'lesson[number]' => 5,
                ],
                'Название урока должно содержать не более 255 символов',
            ],
            'content blank' => [
                [
                    'lesson[title]' => 'Новый урок',
                    'lesson[content]' => '',
                    'lesson[number]' => 5,
                ],
                'Укажите содержание урока',
            ],
            'number blank' => [
                [
                    'lesson[title]' => 'Новый урок',
                    'lesson[content]' => 'Содержание урока',
                    'lesson[number]' => null,
                ],
                'Укажите номер урока',
            ],
            'number positive' => [
                [
                    'lesson[title]' => 'Новый урок',
                    'lesson[content]' => 'Содержание урока',
                    'lesson[number]' => -1,
                ],
                'Номер урока должен быть положительным числом',
            ],
            'number positive zero' => [
                [
                    'lesson[title]' => 'Новый урок',
                    'lesson[content]' => 'Содержание урока',
                    'lesson[number]' => 0,
                ],
                'Номер урока должен быть положительным числом',
            ],
            'number long' => [
                [
                    'lesson[title]' => 'Новый урок',
                    'lesson[content]' => 'Содержание урока',
                    'lesson[number]' => 10001,
                ],
                'Номер урока не должен превышать 10000',
            ],
        ];
    }

    private function getCourseByCode(string $code): Course
    {
        $container = static::getContainer();

        $course = $container->get(CourseRepository::class)->findOneByCode($code);
        self::assertNotNull($course);

        return $course;
    }

    private function getCourseIdByCode(string $code): int
    {
        return $this->getCourseByCode($code)->getId();
    }

    private function getCoursePageByCode(string $code): string
    {
        return '/courses/' . $this->getCourseIdByCode($code);
    }

    private function getFirstLessonId(string $code): int
    {
        $lesson = $this->getCourseByCode($code)->getLessons()->first();
        self::assertNotFalse($lesson);

        return $lesson->getId();
    }
}
