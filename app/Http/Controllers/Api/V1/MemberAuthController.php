<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\MemberLoginRequest;
use App\Http\Resources\MemberProfileResource;
use App\Models\Parish;
use App\Models\User;
use App\Services\AuditService;
use App\Services\DirectoryService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class MemberAuthController extends Controller
{
    public function login(MemberLoginRequest $request, DirectoryService $directory, AuditService $audit): JsonResponse
    {
        $identity = trim($request->validated('identity'));
        $normalizedIdentity = mb_strtolower($identity);
        $user = User::query()
            ->whereNotNull('member_id')
            ->where(function (Builder $query) use ($identity, $normalizedIdentity): void {
                $query->whereRaw('LOWER(email) = ?', [$normalizedIdentity])
                    ->orWhereHas('member', function (Builder $member) use ($identity, $normalizedIdentity): void {
                        $member->whereRaw('LOWER(member_code) = ?', [$normalizedIdentity])
                            ->orWhereRaw('LOWER(email) = ?', [$normalizedIdentity])
                            ->orWhere('phone', $identity);
                    });
            })
            ->with(['member.parish.deanery', 'member.jumuiya.leader'])
            ->first();

        $validPassword = Hash::check(
            $request->validated('password'),
            $user?->password ?? '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.',
        );
        $member = $user?->member;
        $parish = Parish::query()->whereKey($member?->parish_id);
        $directory->publiclyVisible($parish);

        if (! $validPassword || ! $user?->is_active || $member?->status !== 'active' || ! $parish->exists()) {
            Log::notice('api.member_authentication_failed', [
                'request_id' => $request->attributes->get('request_id'),
                'ip_address' => $request->ip(),
            ]);

            return ApiResponse::error('INVALID_CREDENTIALS', 'The supplied credentials are invalid.', 401);
        }

        $expiresAt = now()->addMinutes(config('core.token_expiration'));
        $token = $user->createToken($request->validated('device_name'), ['member:read'], $expiresAt);
        $audit->record($user, 'member_auth.login', 'users', $user->id);

        return ApiResponse::success([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt->toIso8601String(),
            'member' => (new MemberProfileResource($member))->resolve($request),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $member = $request->attributes->get('active_member');
        $member->loadMissing(['parish.deanery', 'jumuiya.leader']);

        return ApiResponse::success((new MemberProfileResource($member))->resolve($request));
    }

    public function logout(Request $request, AuditService $audit): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();
        $audit->record($request->user(), 'member_auth.logout', 'users', $request->user()->id);

        return ApiResponse::success(['message' => 'Signed out successfully.']);
    }
}
