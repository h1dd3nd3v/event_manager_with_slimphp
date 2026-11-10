<?php

declare(strict_types=1);

namespace App\Utilities;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use RuntimeException;
use stdClass;

class JwtToken
{
    public const LIFETIME_SECONDS = 86400;

    public static function issueForUsername(string $username): string
    {
        $issuedAt = time();

        return JWT::encode([
            'sub' => $username,
            'iat' => $issuedAt,
            'exp' => $issuedAt + self::LIFETIME_SECONDS,
        ], self::signingKey(), 'HS256');
    }

    public static function decode(string $token): stdClass
    {
        return JWT::decode($token, new Key(self::signingKey(), 'HS256'));
    }

    private static function signingKey(): string
    {
        $key = $_ENV['AUTH_JWT_SECRET'] ?? null;

        if (!is_string($key) || strlen($key) < 32) {
            throw new RuntimeException('AUTH_JWT_SECRET must contain at least 32 bytes.');
        }

        return $key;
    }
}
