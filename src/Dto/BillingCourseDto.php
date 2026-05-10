<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class BillingCourseDto
{
    #[Assert\NotBlank(message: 'Тип курса не должен быть пустым.')]
    #[Assert\Choice(
        choices: ['free', 'rent', 'buy'],
        message: 'Тип курса должен быть одним из: free, rent, buy.'
    )]
    public ?string $type = null;

    public ?float $price = null;

    #[Assert\Callback]
    public function validatePrice(ExecutionContextInterface $context): void
    {
        if ($this->price !== null && $this->type === 'free') {
            $context
                ->buildViolation('Бесплатный курс не должен иметь цену.')
                ->atPath('price')
                ->addViolation();
        }

        if (
            ($this->price === null || $this->price <= 0)
            && in_array($this->type, ['rent', 'buy'], true)
        ) {
            $context
                ->buildViolation('Платный курс должен иметь положительную цену.')
                ->atPath('price')
                ->addViolation();
        }
    }
}
