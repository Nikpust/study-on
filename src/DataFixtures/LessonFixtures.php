<?php

namespace App\DataFixtures;

use App\Entity\Course;
use App\Entity\Lesson;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class LessonFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $lessonsData = [
            CourseFixtures::COURSE_WEB_REFERENCE => [
                [
                    'title' => 'Как устроен веб: клиент и сервер',
                    'content' => 'В уроке рассматривается базовая схема работы веб-приложения: как браузер отправляет' .
                        'запрос, как сервер его обрабатывает и как формируется ответ пользователю.',
                    'number' => 1,
                ],
                [
                    'title' => 'Структура HTML-документа',
                    'content' => 'Слушатели изучают основные теги HTML, структуру документа, назначение head и body,' .
                        'а также правила построения семантической разметки.',
                    'number' => 2,
                ],
                [
                    'title' => 'Оформление страниц с помощью CSS',
                    'content' => 'Урок посвящён подключению стилей, базовым CSS-свойствам, работе с цветами,' .
                        'отступами, шрифтами и блочной моделью элементов.',
                    'number' => 3,
                ],
                [
                    'title' => 'Введение в JavaScript для веб-страниц',
                    'content' => 'Рассматриваются основы JavaScript: переменные, условия, функции и простое' .
                        'взаимодействие со страницей через DOM.',
                    'number' => 4,
                ],
            ],
            CourseFixtures::COURSE_PHP_REFERENCE => [
                [
                    'title' => 'Основы языка PHP',
                    'content' => 'В уроке изучаются базовые конструкции PHP: переменные, массивы, условные операторы,' .
                        'циклы, функции и работа со строками.',
                    'number' => 1,
                ],
                [
                    'title' => 'Обработка HTTP-запросов на сервере',
                    'content' => 'Слушатели знакомятся с тем, как серверное приложение получает данные из GET и' .
                        'POST-запросов, а также как формирует HTTP-ответ.',
                    'number' => 2,
                ],
                [
                    'title' => 'Маршрутизация и контроллеры',
                    'content' => 'Урок объясняет, как в backend-приложении организуется обработка URL-адресов и каким' .
                        'образом контроллеры отвечают за бизнес-логику.',
                    'number' => 3,
                ],
                [
                    'title' => 'Работа с базой данных из PHP-приложения',
                    'content' => 'Рассматриваются основные подходы к подключению к базе данных, выполнению запросов и' .
                        'сохранению данных из серверной части приложения.',
                    'number' => 4,
                ],
            ],
            CourseFixtures::COURSE_DATABASE_REFERENCE => [
                [
                    'title' => 'Введение в реляционные базы данных',
                    'content' => 'В уроке рассматриваются основные понятия реляционной модели: таблицы, записи, поля,' .
                        'первичные ключи и назначение СУБД.',
                    'number' => 1,
                ],
                [
                    'title' => 'Проектирование таблиц и связей',
                    'content' => 'Слушатели изучают типы связей между сущностями, использование внешних ключей и' .
                        'правила построения структуры базы данных.',
                    'number' => 2,
                ],
                [
                    'title' => 'Основы SQL-запросов',
                    'content' => 'Урок посвящён написанию базовых SQL-запросов: SELECT, INSERT, UPDATE, DELETE, а' .
                        'также фильтрации и сортировке данных.',
                    'number' => 3,
                ],
                [
                    'title' => 'Нормализация и целостность данных',
                    'content' => 'Рассматриваются цели нормализации, устранение дублирования данных и способы' .
                        'поддержания целостности информации в PostgreSQL.',
                    'number' => 4,
                ],
            ],
        ];

        foreach ($lessonsData as $courseReference => $courseLessons) {
            $course = $this->getReference($courseReference, Course::class);

            foreach ($courseLessons as $lessonData) {
                $lesson = new Lesson();
                $lesson->setCourse($course);
                $lesson->setTitle($lessonData['title']);
                $lesson->setContent($lessonData['content']);
                $lesson->setNumber($lessonData['number']);

                $manager->persist($lesson);
            }
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            CourseFixtures::class,
        ];
    }
}
