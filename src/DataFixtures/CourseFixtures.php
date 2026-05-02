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
    public const COURSE_SYMFONY_REFERENCE = 'course-symfony';
    public const COURSE_API_REFERENCE = 'course-api';
    public const COURSE_DOCKER_REFERENCE = 'course-docker';
    public const COURSE_TESTING_REFERENCE = 'course-testing';
    public const COURSE_FRONTEND_REFERENCE = 'course-frontend';
    public const COURSE_REACT_REFERENCE = 'course-react';
    public const COURSE_VUE_REFERENCE = 'course-vue';

    public function load(ObjectManager $manager): void
    {
        $coursesData = [
            [
                'reference' => self::COURSE_WEB_REFERENCE,
                'code' => 'web-development-basics',
                'title' => 'Основы веб-разработки',
                'description' => 'Курс знакомит с устройством современных веб-приложений. Слушатели изучат основы' .
                    'клиент-серверного взаимодействия, структуру HTML-документов, базовые возможности CSS и' .
                    'JavaScript, а также общий процесс разработки и публикации сайта.',
            ],
            [
                'reference' => self::COURSE_PHP_REFERENCE,
                'code' => 'php-backend-development',
                'title' => 'Backend-разработка на PHP',
                'description' => 'Практический курс по разработке серверной части веб-приложений на PHP.' .
                    'Рассматриваются обработка HTTP-запросов, работа с формами, маршрутизация, взаимодействие с базой' .
                    'данных и построение архитектуры backend-приложения.',
            ],
            [
                'reference' => self::COURSE_DATABASE_REFERENCE,
                'code' => 'database-design-postgresql',
                'title' => 'Проектирование баз данных в PostgreSQL',
                'description' => 'Курс посвящён проектированию реляционных баз данных и работе с PostgreSQL.' .
                    'Рассматриваются таблицы, связи между сущностями, первичные и внешние ключи, нормализация данных,' .
                    'написание SQL-запросов и организация хранения данных в прикладных системах.',
            ],
            [
                'reference' => self::COURSE_SYMFONY_REFERENCE,
                'code' => 'symfony-basics',
                'title' => 'Основы Symfony',
                'description' => 'Курс знакомит с фреймворком Symfony и его основными возможностями. ' .
                    'Слушатели изучат структуру Symfony-приложения, маршрутизацию, контроллеры, шаблоны, сервисы, ' .
                    'конфигурацию и базовые подходы к разработке веб-приложений на Symfony.',
            ],
            [
                'reference' => self::COURSE_API_REFERENCE,
                'code' => 'rest-api-development',
                'title' => 'Разработка REST API',
                'description' => 'Курс посвящён проектированию и разработке REST API для веб-приложений. ' .
                    'Рассматриваются HTTP-методы, статусы ответов, сериализация данных, маршруты API, обработка ' .
                    'ошибок, аутентификация и документирование интерфейсов взаимодействия между сервисами.',
            ],
            [
                'reference' => self::COURSE_DOCKER_REFERENCE,
                'code' => 'docker-for-developers',
                'title' => 'Docker для разработчиков',
                'description' => 'Практический курс по использованию Docker в процессе разработки приложений. ' .
                    'Слушатели изучат контейнеры, образы, Dockerfile, docker-compose, настройку окружения, работу с ' .
                    'сервисами приложения и базовые подходы к запуску проектов в изолированной среде.',
            ],
            [
                'reference' => self::COURSE_TESTING_REFERENCE,
                'code' => 'automated-testing-php',
                'title' => 'Автоматизированное тестирование на PHP',
                'description' => 'Курс посвящён автоматизированному тестированию PHP-приложений. ' .
                    'Рассматриваются модульные и функциональные тесты, подготовка тестовых данных, работа с PHPUnit, ' .
                    'проверка бизнес-логики и подходы к повышению надёжности backend-кода.',
            ],
            [
                'reference' => self::COURSE_FRONTEND_REFERENCE,
                'code' => 'frontend-with-bootstrap',
                'title' => 'Frontend-разработка с Bootstrap',
                'description' => 'Курс знакомит с разработкой пользовательских интерфейсов с использованием ' .
                    'Bootstrap. Слушатели изучат сетку, компоненты, адаптивную вёрстку, формы, навигацию и базовые ' .
                    'приёмы создания современных интерфейсов без написания большого объёма CSS-кода.',
            ],
            [
                'reference' => self::COURSE_REACT_REFERENCE,
                'code' => 'frontend-with-react',
                'title' => 'Frontend-разработка на React',
                'description' => 'Курс посвящён разработке пользовательских интерфейсов с использованием React. ' .
                    'Слушатели изучат компоненты, свойства, состояние, хуки, обработку событий, работу с формами, ' .
                    'маршрутизацию и базовые подходы к построению современных frontend-приложений.',
            ],
            [
                'reference' => self::COURSE_VUE_REFERENCE,
                'code' => 'frontend-with-vue',
                'title' => 'Frontend-разработка на Vue',
                'description' => 'Курс знакомит с разработкой пользовательских интерфейсов с использованием Vue. ' .
                    'Рассматриваются компоненты, реактивность, директивы, свойства, события, работа с формами, ' .
                    'маршрутизация и организация структуры frontend-приложения.',
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
