<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class CepLookupService
{
    /**
     * @return array<string, string>|null
     */
    public static function search(string $cep): ?array
    {
        $cep = preg_replace('/[^0-9]/', '', $cep);

        if ($cep === null || strlen($cep) !== 8) {
            return null;
        }

        return self::openCep($cep)
            ?? self::viaCep($cep)
            ?? self::brasilCep($cep)
            ?? self::apiCep($cep);
    }

    /**
     * @return array<string, string>|null
     */
    protected static function viaCep(string $cep): ?array
    {
        try {
            $response = Http::timeout(5)->retry(2, 100)
                ->get("https://viacep.com.br/ws/{$cep}/json/");

            if ($response->failed() || $response->json('erro')) {
                return null;
            }

            $data = $response->json();

            return [
                'street' => $data['logradouro'] ?? '',
                'neighborhood' => $data['bairro'] ?? '',
                'city' => $data['localidade'] ?? '',
                'state' => $data['uf'] ?? '',
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, string>|null
     */
    protected static function openCep(string $cep): ?array
    {
        try {
            $response = Http::timeout(5)->retry(2, 100)
                ->get("https://opencep.com/v1/{$cep}.json");

            if ($response->failed() || empty($response->json('logradouro'))) {
                return null;
            }

            $data = $response->json();

            return [
                'street' => $data['logradouro'] ?? '',
                'neighborhood' => $data['bairro'] ?? '',
                'city' => $data['localidade'] ?? '',
                'state' => $data['uf'] ?? '',
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, string>|null
     */
    protected static function brasilCep(string $cep): ?array
    {
        try {
            $response = Http::timeout(5)->retry(2, 100)
                ->get("https://brasilcep.com.br/api/address/{$cep}");

            if ($response->failed() || empty($response->json('address'))) {
                return null;
            }

            $data = $response->json();

            return [
                'street' => $data['address'] ?? '',
                'neighborhood' => $data['district'] ?? '',
                'city' => $data['city'] ?? '',
                'state' => $data['state'] ?? '',
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, string>|null
     */
    protected static function apiCep(string $cep): ?array
    {
        try {
            $response = Http::timeout(5)->retry(2, 100)
                ->get("https://api.cep.com.br/v2/locations?code={$cep}");

            $locations = $response->json('locations');

            if ($response->failed() || empty($locations)) {
                return null;
            }

            $location = $locations[0];

            return [
                'street' => $location['address'] ?? '',
                'neighborhood' => $location['district'] ?? '',
                'city' => $location['city'] ?? '',
                'state' => $location['state'] ?? '',
            ];
        } catch (\Throwable) {
            return null;
        }
    }
}
