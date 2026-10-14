<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Parish;
use App\Models\User;
use App\Support\MemberPhoneNumber;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MemberPhoneAuthentication
{
    public function __construct(private MemberSmsSender $sms, private DirectoryService $directory, private AuditService $audit) {}

    /** @return array{challenge_id: string, expires_in: int, resend_after: int, development_code?: string} */
    public function requestCode(string $phone): array
    {
        $this->sms->ensureConfigured();
        $this->ensurePreviewPhone($phone);
        $phoneKey = hash('sha256', $phone);

        return Cache::lock('member-otp:send-lock:'.$phoneKey, 20)->block(3, function () use ($phone, $phoneKey): array {
            if (Cache::has('member-otp:cooldown:'.$phoneKey)) {
                throw new HttpException(429, 'Please wait before requesting another code.');
            }
            $member = $this->eligibleMember($phone);
            if (! $member && $this->sms->isPreview()) {
                throw new HttpException(422, $this->previewMemberMessage());
            }
            $id = (string) Str::uuid();
            $code = (string) random_int(100000, 999999);
            $ttl = (int) config('member_auth.code_ttl_seconds', 300);
            $cooldown = (int) config('member_auth.resend_seconds', 60);
            $challenge = [
                'phone' => $phone,
                'member_id' => $member?->id,
                'code_hash' => Hash::make($code),
                'attempts' => 0,
                'expires_at' => now()->addSeconds($ttl)->timestamp,
            ];
            if ($member) {
                $this->sms->send($phone, $code);
            }
            $previous = Cache::get('member-otp:active:'.$phoneKey);
            if (is_string($previous)) {
                Cache::forget('member-otp:challenge:'.$previous);
            }
            Cache::put('member-otp:challenge:'.$id, $challenge, $ttl);
            Cache::put('member-otp:active:'.$phoneKey, $id, $ttl);
            Cache::put('member-otp:cooldown:'.$phoneKey, true, $cooldown);

            $response = ['challenge_id' => $id, 'expires_in' => $ttl, 'resend_after' => $cooldown];
            if ($this->sms->isPreview()) {
                $response['development_code'] = $code;
            }

            return $response;
        });
    }

    /** @return array{user: User, token: string, expires_at: string}|null */
    public function verifyCode(string $id, string $code, string $deviceName): ?array
    {
        $this->sms->ensureConfigured();

        return Cache::lock('member-otp:verify-lock:'.$id, 20)->block(3, function () use ($id, $code, $deviceName): ?array {
            $key = 'member-otp:challenge:'.$id;
            $challenge = Cache::get($key);
            if (! is_array($challenge) || $challenge['expires_at'] <= now()->timestamp) {
                Cache::forget($key);
                throw new HttpException(422, 'This verification code has expired or was already used. Request a new code.');
            }
            $phoneKey = hash('sha256', $challenge['phone']);
            $this->ensurePreviewPhone($challenge['phone']);

            return Cache::lock('member-otp:send-lock:'.$phoneKey, 20)->block(3, function () use ($id, $code, $deviceName, $key, $challenge, $phoneKey): ?array {
                if (Cache::get('member-otp:active:'.$phoneKey) !== $id) {
                    Cache::forget($key);
                    throw new HttpException(422, 'A newer verification code was requested. Enter the latest code.');
                }
                $challenge['attempts']++;
                $valid = Hash::check($code, $challenge['code_hash']);
                if (! $valid) {
                    if ($challenge['attempts'] >= (int) config('member_auth.max_attempts', 5)) {
                        Cache::forget($key);
                        throw new HttpException(422, 'Too many incorrect attempts. Request a new verification code.');
                    } else {
                        Cache::put($key, $challenge, max(1, $challenge['expires_at'] - now()->timestamp));
                    }

                    return null;
                }
                if (! $challenge['member_id']) {
                    Cache::forget($key);
                    Cache::forget('member-otp:active:'.$phoneKey);
                    if ($this->sms->isPreview()) {
                        throw new HttpException(422, $this->previewMemberMessage());
                    }
                    return null;
                }

                $session = DB::transaction(function () use ($challenge, $deviceName): ?array {
                    $member = Member::query()->lockForUpdate()->find($challenge['member_id']);
                    $eligible = $this->eligibleMember($challenge['phone']);
                    if (! $member || $eligible?->id !== $member->id
                        || MemberPhoneNumber::normalize($member->phone) !== $challenge['phone']) {
                        if ($this->sms->isPreview()) {
                            throw new HttpException(422, $this->previewMemberMessage());
                        }
                        return null;
                    }
                    $user = User::query()->where('member_id', $member->id)->lockForUpdate()->first();
                    if ($user && (! $user->is_active || $user->role_id !== null)) {
                        if ($this->sms->isPreview()) {
                            throw new HttpException(422, $this->previewMemberMessage());
                        }
                        return null;
                    }
                    if (! $user) {
                        $user = new User;
                        $user->name = collect([$member->first_name, $member->middle_name, $member->last_name])->filter()->implode(' ');
                        $user->email = 'member-'.$member->id.'@phone.mkatolik.invalid';
                        $user->password = Str::random(64);
                        $user->member()->associate($member);
                        $user->save();
                        $this->audit->record(null, 'member_account.created', 'users', $user->id);
                    }

                    $expiresAt = now()->addMonthsNoOverflow(3);
                    $token = $user->createToken($deviceName, ['member:read'], $expiresAt);
                    $this->audit->record($user, 'member_auth.phone_login', 'users', $user->id);

                    return ['user' => $user, 'token' => $token->plainTextToken, 'expires_at' => $expiresAt->toIso8601String()];
                });
                Cache::forget($key);
                Cache::forget('member-otp:active:'.$phoneKey);

                return $session;
            });
        });
    }

    private function eligibleMember(string $phone): ?Member
    {
        $members = MemberPhoneNumber::query($phone)->limit(2)->get();
        if ($members->count() !== 1) {
            return null;
        }
        $member = $members->first();
        $parish = Parish::query()->whereKey($member->parish_id);
        $this->directory->publiclyVisible($parish);
        if ($member->status !== 'active' || ! $parish->exists()
            || ($member->user && (! $member->user->is_active || $member->user->role_id !== null))) {
            return null;
        }

        return $member;
    }

    private function ensurePreviewPhone(string $phone): void
    {
        if ($this->sms->isPreview() && $phone !== config('member_auth.preview_phone', '+255769289824')) {
            throw new HttpException(422, 'Temporary OTP login is available only for 0769289824.');
        }
    }

    private function previewMemberMessage(): string
    {
        return 'This phone number must belong to exactly one active member in an active parish. Check the member phone, duplicate numbers, and account status in the portal, then request a new code.';
    }
}
