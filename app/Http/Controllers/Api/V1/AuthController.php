<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Services\AuditService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function login(LoginRequest $request, AuditService $audit): JsonResponse
    {
        $user = User::where('email', strtolower($request->validated('email')))->first();
        $validPassword = Hash::check($request->validated('password'), $user?->password ?? '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
        if (! $validPassword || ! $user?->hasPermission('directory.read')) {
            Log::notice('api.authentication_failed', ['request_id' => $request->attributes->get('request_id'), 'ip_address' => $request->ip()]);

            return ApiResponse::error('INVALID_CREDENTIALS', 'The supplied credentials are invalid.', 401);
        }
        $abilities = ['directory:read'];
        if ($user->hasPermission('directory.write')) {
            $abilities[] = 'directory:write';
        }
        $expiresAt = now()->addMinutes(config('core.token_expiration'));
        $token = $user->createToken($request->validated('device_name'), $abilities, $expiresAt);
        $audit->record($user, 'auth.login', 'users', $user->id);

        return ApiResponse::success(['token' => $token->plainTextToken, 'token_type' => 'Bearer', 'expires_at' => $expiresAt->toIso8601String(), 'user' => $this->profile($user)]);
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success($this->profile($request->user()));
    }

    public function logout(Request $request, AuditService $audit): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();
        $audit->record($request->user(), 'auth.logout', 'users', $request->user()->id);

        return ApiResponse::success(['message' => 'Signed out successfully.']);
    }

    private function profile(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role?->name, 'diocese_id' => $user->diocese_id, 'deanery_id' => $user->deanery_id, 'parish_id' => $user->parish_id];
    }
}
