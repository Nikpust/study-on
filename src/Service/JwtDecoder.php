<?php

namespace App\Service;

use InvalidArgumentException;

final class JwtDecoder
{
    public function decodePayload(string $jwt): array
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            throw new InvalidArgumentException('Невалидный JWT token.');
        }

        $payload = $parts[1];

        $payload = strtr($payload, '-_', '+/');
        $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);

        $decoded = base64_decode($payload, true);

        if ($decoded === false) {
            throw new InvalidArgumentException('Невалидный JWT token.');
        }

        return json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
    }

    public function isExpired(string $jwt, int $reserveSeconds = 30): bool
    {
        $payload = $this->decodePayload($jwt);

        if (!isset($payload['exp']) || !is_numeric($payload['exp'])) {
            throw new InvalidArgumentException('JWT token не содержит срока истечения жизни.');
        }

        return $payload['exp'] <= time() + $reserveSeconds;
    }

}
