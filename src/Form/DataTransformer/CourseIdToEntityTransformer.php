<?php

namespace App\Form\DataTransformer;

use App\Entity\Course;
use App\Repository\CourseRepository;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

final class CourseIdToEntityTransformer implements DataTransformerInterface
{
    public function __construct(
        private readonly CourseRepository $courseRepository,
    ) {
    }

    public function transform($value): string
    {
        if ($value instanceof Course) {
            return (string) ($value->getId() ?? '');
        }

        return '';
    }

    public function reverseTransform($value): ?Course
    {
        if (null === $value || '' === $value) {
            throw new TransformationFailedException('Ошибка: курс не указан');
        }

        $course = $this->courseRepository->find((int) $value);
        if (!$course instanceof Course) {
            throw new TransformationFailedException('Ошибка: указанный курс не найден');
        }

        return $course;
    }
}
