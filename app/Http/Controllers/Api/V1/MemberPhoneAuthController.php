<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\RequestMemberOtpRequest;
use App\Http\Requests\VerifyMemberOtpRequest;
use App\Http\Resources\MemberProfileResource;
use App\Services\MemberPhoneAuthentication;
use App\Support\ApiResponse;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MemberPhoneAuthController extends Controller
{
    public function requestCode(RequestMemberOtpRequest $request, MemberPhoneAuthentication $auth): JsonResponse
    {
        try {
            return ApiResponse::success($auth->requestCode($request->validated('phone')));
        } catch (HttpException $error) {
            return ApiResponse::error('PHONE_LOGIN_UNAVAILABLE', $error->getMessage(), $error->getStatusCode());
        } catch (LockTimeoutException) {
            return ApiResponse::error('PHONE_LOGIN_BUSY', 'Please try again shortly.', 503);
        }
    }

    public function verifyCode(VerifyMemberOtpRequest $request, MemberPhoneAuthentication $auth): JsonResponse
    {
        try {
            $session = $auth->verifyCode($request->validated('challenge_id'), $request->validated('code'), $request->validated('device_name'));
        } catch (HttpException $error) {
            return ApiResponse::error('PHONE_LOGIN_UNAVAILABLE', $error->getMessage(), $error->getStatusCode());
        } catch (LockTimeoutException) {
            return ApiResponse::error('PHONE_LOGIN_BUSY', 'Please try again shortly.', 503);
        }
        if (! $session) {
            return ApiResponse::error('INVALID_VERIFICATION_CODE', 'The verification code is incorrect. Enter the latest code or request a new one.', 422);
        }
        $member = $session['user']->member->load(['parish.deanery', 'jumuiya.leader']);

        return ApiResponse::success([
            'token' => $session['token'],
            'token_type' => 'Bearer',
            'expires_at' => $session['expires_at'],
            'member' => (new MemberProfileResource($member))->resolve($request),
        ]);
    }
}
