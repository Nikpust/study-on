<?php

namespace App\Dto\Security;

use Symfony\Component\Validator\Constraints as Assert;

final class RegisterDto
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

    #[Assert\NotBlank(message: 'Подтвердите указанный пароль.')]
    #[Assert\EqualTo(
        propertyPath: 'password',
        message: 'Пароли не совпадают.'
    )]
    public ?string $confirmPassword = null;
}
