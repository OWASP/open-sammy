<?php

declare(strict_types=1);

namespace App\Utils;

use ParagonIE\ConstantTime\Base32;
use ParagonIE\ConstantTime\Base64;

class RandomStringGenerator
{
    public function base32(int $length = 8): string
    {
        return Base32::encodeUnpadded(random_bytes($length));
    }

    public function base64(int $length = 8): string
    {
        return Base64::encodeUnpadded(random_bytes($length));
    }

    /**
     * @throws \Exception
     */
    public function generateRandomString(int $length = 32): string
    {
        $pieces = [];
        $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $max = mb_strlen($keyspace, '8bit') - 1;
        for ($i = 0; $i < $length; ++$i) {
            $pieces[] = $keyspace[random_int(0, $max)];
        }

        return implode('', $pieces);
    }
}
