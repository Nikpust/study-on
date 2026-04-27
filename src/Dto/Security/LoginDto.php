<?php

namespace App\Dto\Security;

use Symfony\Component\Validator\Constraints as Assert;

class LoginDto
{
    #[Assert\NotBlank(message: 'Email не должен быть пустым.')]
    #[Assert\Email(message: 'Неверный email.')]
    public ?string $email = null;

    #[Assert\NotBlank(message: 'Пароль не должен быть пустым.')]
    #[Assert\Length(
        min: 6,
        minMessage: 'Пароль должен быть не короче {{ limit }} символов.'
    )]
    public ?string $password = null;
}
