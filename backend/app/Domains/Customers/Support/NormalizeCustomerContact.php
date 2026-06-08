<?php

namespace App\Domains\Customers\Support;

class NormalizeCustomerContact
{
    public function email(?string $email): ?string
    {
        if ($email === null || trim($email) === '') {
            return null;
        }

        return mb_strtolower(trim($email));
    }

    public function phone(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        return $digits !== '' ? $digits : null;
    }
}
