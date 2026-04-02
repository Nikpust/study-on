<?php

namespace App\DataFixtures;

use App\Entity\Course;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CourseFixtures extends Fixture
{
    public const COURSE_WEB_REFERENCE = 'course-web';
    public const COURSE_PHP_REFERENCE = 'course-php';
    public const COURSE_DATABASE_REFERENCE = 'course-database';

    public function load(ObjectManager $manager): void
    {
        $coursesData = [
            [
                'reference' => self::COURSE_WEB_REFERENCE,
                'code' => 'web-development-basics',
                'title' => 'Основы веб-разработки',
                'description' => 'Курс знакомит с устройством современных веб-приложений. Слушатели изучат основы клиент-серверного взаимодействия, структуру HTML-документов, базовые возможности CSS и JavaScript, а также общий процесс разработки и публикации сайта.',
            ],
            [
                'reference' => self::COURSE_PHP_REFERENCE,
                'code' => 'php-backend-development',
                'title' => 'Backend-разработка на PHP',
                'description' => 'Практический курс по разработке серверной части веб-приложений на PHP. Рассматриваются обработка HTTP-запросов, работа с формами, маршрутизация, взаимодействие с базой данных и построение архитектуры backend-приложения.',
            ],
            [
                'reference' => self::COURSE_DATABASE_REFERENCE,
                'code' => 'database-design-postgresql',
                'title' => 'Проектирование баз данных в PostgreSQL',
                'description' => 'Курс посвящён проектированию реляционных баз данных и работе с PostgreSQL. Рассматриваются таблицы, связи между сущностями, первичные и внешние ключи, нормализация данных, написание SQL-запросов и организация хранения данных в прикладных системах.',
            ],
        ];

        foreach ($coursesData as $courseData) {
            $course = new Course();
            $course->setCode($courseData['code']);
            $course->setTitle($courseData['title']);
            $course->setDescription($courseData['description']);
            $manager->persist($course);
            $this->addReference($courseData['reference'], $course);
        }

        $manager->flush();
    }
}
