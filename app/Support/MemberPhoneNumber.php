<?php

namespace App\Support;

use App\Models\Member;
use Illuminate\Database\Eloquent\Builder;

class MemberPhoneNumber
{
    public static function normalize(?string $value): ?string
    {
        if ($value === null || ! preg_match('/^[+0-9\s().-]+$/', $value)) {
            return null;
        }
        $digits = preg_replace('/\D/', '', $value);
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        } elseif (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            $digits = self::countryCode().substr($digits, 1);
        }

        return preg_match('/^[1-9][0-9]{7,14}$/', $digits) ? '+'.$digits : null;
    }

    public static function query(string $phone): Builder
    {
        $digits = "REGEXP_REPLACE(phone, '[^0-9]', '')";
        $country = self::countryCode();
        $normalized = "CASE WHEN LEFT($digits, 2) = '00' THEN SUBSTRING($digits, 3) WHEN LENGTH($digits) = 10 AND LEFT($digits, 1) = '0' THEN CONCAT('$country', SUBSTRING($digits, 2)) ELSE $digits END";

        return Member::query()->whereRaw("$normalized = ?", [ltrim($phone, '+')]);
    }

    private static function countryCode(): string
    {
        return preg_replace('/\D/', '', (string) config('member_auth.country_code', '255')) ?: '255';
    }
}
