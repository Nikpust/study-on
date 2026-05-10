<?php

namespace App\Service;

use App\Dto\BillingCourseDto;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class BillingCourseFormHandler
{
    public function __construct(
        private ValidatorInterface $validator,
    ) {
    }

    public function getCourseData(FormInterface $form): array
    {
        $type = $form->get('type')->getData();

        return [
            'code' => $form->get('code')->getData(),
            'type' => $type,
            'price' => $type === 'free' ? null : $form->get('price')->getData(),
            'title' => $form->get('title')->getData(),
        ];
    }

    public function addBillingErrorsToForm(FormInterface $form, array $response): void
    {
        if (!isset($response['violations']) || !is_array($response['violations'])) {
            $form->addError(
                new FormError($response['message']
                    ?? $response['detail']
                    ?? 'Некорректные данные.')
            );

            return;
        }

        foreach ($response['violations'] as $violation) {
            $propertyPath = $violation['propertyPath'] ?? null;
            $message = $violation['title'] ?? $violation['message'] ?? 'Некорректные данные.';

            if ($propertyPath !== null && $form->has($propertyPath)) {
                $form->get($propertyPath)->addError(new FormError($message));

                continue;
            }

            $form->addError(new FormError($message));
        }
    }

    public function validateBillingCourseData(FormInterface $form): void
    {
        $dto = new BillingCourseDto();
        $dto->type = $form->get('type')->getData();
        $dto->price = $form->get('price')->getData();

        $errors = $this->validator->validate($dto);

        foreach ($errors as $error) {
            $field = $error->getPropertyPath();
            $message = $error->getMessage();

            if ($field !== '' && $form->has($field)) {
                $form->get($field)->addError(new FormError($message));

                continue;
            }

            $form->addError(new FormError($message));
        }
    }
}
