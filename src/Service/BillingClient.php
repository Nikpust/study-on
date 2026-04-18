<?php

namespace App\Service;

use App\Exception\BillingUnavailableException;

readonly class BillingClient
{
    public function __construct(
        private string $billingBaseUrl,
    ) {
    }

    public function auth(string $email, string $password): array
    {
        return $this->billingRequest(
            method: 'POST',
            path: '/api/v1/auth',
            data: [
                'username' => $email,
                'password' => $password,
            ],
        );
    }

    public function register(string $email, string $password): array
    {
        return $this->billingRequest(
            method: 'POST',
            path: '/api/v1/register',
            data: [
                'email' => $email,
                'password' => $password,
            ],
        );
    }

    public function getCurrentUser(string $token): array
    {
        return $this->billingRequest(
            method: 'GET',
            path: '/api/v1/users/current',
            token: $token,
        );
    }

    private function billingRequest(string $method, string $path, ?array $data = null, ?string $token = null): array
    {
        $curl = curl_init();

        try {
            $options = [
                CURLOPT_URL => rtrim($this->billingBaseUrl, '/') . $path,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                ],
            ];

            if ($data !== null) {
                $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

                $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
                $options[CURLOPT_POSTFIELDS] = $json;
            }

            if ($token !== null) {
                $options[CURLOPT_HTTPHEADER][] = 'Authorization: Bearer ' . $token;
            }

            curl_setopt_array($curl, $options);
            $responseBody = curl_exec($curl);

            if ($responseBody === false) {
                throw new BillingUnavailableException('Сервис временно недоступен');
            }

            $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);

            try {
                $decoded = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw new BillingUnavailableException('Сервис временно недоступен');
            }

            if (!is_array($decoded)) {
                throw new BillingUnavailableException('Сервис временно недоступен');
            }

            $decoded['_status_code'] = $statusCode;

            return $decoded;
        } catch (\JsonException) {
            throw new \RuntimeException('Не удалось сформировать JSON-запрос');
        } finally {
            curl_close($curl);
        }
    }
}
