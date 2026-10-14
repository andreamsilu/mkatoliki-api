<?php

namespace App\Rules;

use App\Support\MemberPhoneNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueMemberPhone implements ValidationRule
{
    public function __construct(private ?int $ignoreMemberId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $phone = is_string($value) ? MemberPhoneNumber::normalize($value) : null;
        if (! $phone) {
            $fail('Enter a valid phone number, for example +255712345678.');

            return;
        }
        $query = MemberPhoneNumber::query($phone);
        if ($this->ignoreMemberId !== null) {
            $query->where('id', '!=', $this->ignoreMemberId);
        }
        if ($query->exists()) {
            $fail('This phone number is already registered to another member. Use the member\'s own phone number.');
        }
    }
}
