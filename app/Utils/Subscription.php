<?php
declare(strict_types=1);

namespace App\Utils;

class Subscription
{
    public static function encode(string $value): string
    {
        return base64_encode(openssl_encrypt(
            data: $value,
            cipher_algo: 'AES-256-CBC',
            passphrase: Code::conf('subscription.hyundai.key'),
            options: OPENSSL_RAW_DATA,
            iv: str_repeat(chr(0), 16)
        ));
    }

    public static function decode(string $value): string|null|bool
    {
        return openssl_decrypt(
            data: base64_decode($value),
            cipher_algo: 'AES-256-CBC',
            passphrase: Code::conf('subscription.hyundai.key'),
            options: OPENSSL_RAW_DATA,
            iv: str_repeat(chr(0), 16)
        );
    }
}
