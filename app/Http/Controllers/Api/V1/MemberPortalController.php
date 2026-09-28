<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContributionPaymentRequest;
use App\Http\Requests\StoreMemberServiceRequest;
use App\Http\Requests\UpdateMemberPasswordRequest;
use App\Http\Requests\UpdateMemberProfileRequest;
use App\Http\Resources\MemberProfileResource;
use App\Models\ContributionCampaign;
use App\Models\ContributionPayment;
use App\Models\Member;
use App\Models\MemberNotification;
use App\Models\MemberSacrament;
use App\Models\MemberServiceRequest;
use App\Services\AuditService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MemberPortalController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        return ApiResponse::success($this->dashboardData($this->member($request), $request));
    }

    public function updateProfile(UpdateMemberProfileRequest $request, AuditService $audit): JsonResponse
    {
        $member = $this->member($request);
        $attributes = $request->validated();
        $before = $member->only(['first_name', 'middle_name', 'last_name', 'phone', 'email']);

        DB::transaction(function () use ($attributes, $audit, $before, $member, $request): void {
            $memberAttributes = collect($attributes)->except('email')->all();
            if ($memberAttributes !== []) {
                $member->fill($memberAttributes)->save();
            }
            if (array_key_exists('email', $attributes)) {
                $member->email = $attributes['email'];
                $member->save();
                $request->user()->email = $attributes['email'];
                $request->user()->save();
            }
            $audit->record($request->user(), 'member_profile.updated', 'members', $member->id, $before, $member->only(['first_name', 'middle_name', 'last_name', 'phone', 'email']));
        });

        $member->loadMissing(['parish.deanery', 'jumuiya.leader']);

        return ApiResponse::success((new MemberProfileResource($member))->resolve($request));
    }

    public function updatePassword(UpdateMemberPasswordRequest $request, AuditService $audit): JsonResponse
    {
        $user = $request->user();
        if (! Hash::check($request->validated('current_password'), $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'The current password is incorrect.']);
        }
        $user->password = $request->validated('password');
        $user->save();
        $audit->record($user, 'member_password.updated', 'users', $user->id);

        return ApiResponse::success(['message' => 'Password updated successfully.']);
    }

    public function markNotificationRead(Request $request, int $notification): JsonResponse
    {
        $record = $this->member($request)->notifications()->findOrFail($notification);
        $record->update(['read_at' => $record->read_at ?? now()]);

        return ApiResponse::success($this->notification($record));
    }

    public function requestContributionPayment(StoreContributionPaymentRequest $request, int $campaign, AuditService $audit): JsonResponse
    {
        $member = $this->member($request);
        $campaign = ContributionCampaign::query()
            ->whereKey($campaign)
            ->where('parish_id', $member->parish_id)
            ->where('status', 'active')
            ->where(fn (Builder $query) => $query->whereNull('starts_on')->orWhere('starts_on', '<=', today()))
            ->where(fn (Builder $query) => $query->whereNull('ends_on')->orWhere('ends_on', '>=', today()))
            ->firstOrFail();
        $payment = ContributionPayment::create([
            'member_id' => $member->id,
            'contribution_campaign_id' => $campaign->id,
            'amount' => $request->validated('amount'),
            'payment_method' => $request->validated('payment_method'),
            'reference' => 'MKT-'.now()->format('Ymd').'-'.Str::upper(Str::random(10)),
            'status' => 'pending',
        ]);
        $audit->record($request->user(), 'contribution_payment.requested', 'contribution_payments', $payment->id);

        return ApiResponse::success($this->payment($payment), 201);
    }

    public function requestSacramentService(StoreMemberServiceRequest $request, int $sacrament, AuditService $audit): JsonResponse
    {
        $member = $this->member($request);
        $sacrament = $member->sacraments()->findOrFail($sacrament);
        $serviceRequest = MemberServiceRequest::create([
            'member_id' => $member->id,
            'member_sacrament_id' => $sacrament->id,
            ...$request->validated(),
            'status' => 'pending',
            'requested_at' => now(),
        ]);
        $audit->record($request->user(), 'member_service_request.created', 'member_service_requests', $serviceRequest->id);

        return ApiResponse::success([
            'id' => $serviceRequest->id,
            'type' => $serviceRequest->type,
            'status' => $serviceRequest->status,
            'requested_at' => $serviceRequest->requested_at?->toIso8601String(),
        ], 201);
    }

    public function report(Request $request, string $type): JsonResponse
    {
        abort_unless(in_array($type, ['contributions', 'sacraments', 'membership', 'annual'], true), 404);
        $data = $this->dashboardData($this->member($request), $request);

        return ApiResponse::success(['type' => $type, 'generated_at' => now()->toIso8601String(), 'data' => $data]);
    }

    private function member(Request $request): Member
    {
        return $request->attributes->get('active_member');
    }

    /**
     * @return array<string, mixed>
     */
    private function dashboardData(Member $member, Request $request): array
    {
        $member->loadMissing([
            'parish.deanery',
            'jumuiya.leader',
            'sacraments' => fn ($query) => $query->orderBy('received_on')->orderBy('id'),
            'notifications' => fn ($query) => $query->latest('published_at')->latest('id'),
        ]);
        $campaigns = ContributionCampaign::query()
            ->where('parish_id', $member->parish_id)
            ->whereIn('status', ['active', 'upcoming', 'completed'])
            ->with(['payments' => fn ($query) => $query->where('member_id', $member->id)->latest('requested_at')])
            ->orderBy('starts_on')
            ->orderBy('id')
            ->get();

        return [
            'profile' => (new MemberProfileResource($member))->resolve($request),
            'contributions' => $campaigns->map(fn (ContributionCampaign $campaign): array => [
                'id' => $campaign->id,
                'title' => $campaign->title,
                'target_amount' => (float) $campaign->target_amount,
                'paid_amount' => (float) $campaign->payments->where('status', 'confirmed')->sum('amount'),
                'starts_on' => $campaign->starts_on?->toDateString(),
                'ends_on' => $campaign->ends_on?->toDateString(),
                'status' => $campaign->status,
                'payments' => $campaign->payments->map(fn (ContributionPayment $payment): array => $this->payment($payment)),
            ]),
            'sacraments' => $member->sacraments->map(fn (MemberSacrament $sacrament): array => [
                'id' => $sacrament->id,
                'name' => $sacrament->name,
                'received_on' => $sacrament->received_on?->toDateString(),
                'place' => $sacrament->place,
                'status' => $sacrament->status,
            ]),
            'notifications' => $member->notifications->map(fn (MemberNotification $notification): array => $this->notification($notification)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payment(ContributionPayment $payment): array
    {
        return [
            'id' => $payment->id,
            'amount' => (float) $payment->amount,
            'payment_method' => $payment->payment_method,
            'reference' => $payment->reference,
            'receipt_number' => $payment->receipt_number,
            'status' => $payment->status,
            'requested_at' => $payment->requested_at?->toIso8601String(),
            'confirmed_at' => $payment->confirmed_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function notification(MemberNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'title' => $notification->title,
            'message' => $notification->message,
            'type' => $notification->type,
            'published_at' => $notification->published_at?->toIso8601String(),
            'read_at' => $notification->read_at?->toIso8601String(),
        ];
    }
}
