<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewContributionPaymentRequest;
use App\Http\Requests\SaveContributionCampaignRequest;
use App\Http\Resources\ContributionCampaignResource;
use App\Http\Resources\ContributionPaymentResource;
use App\Models\ContributionCampaign;
use App\Models\ContributionPayment;
use App\Models\Parish;
use App\Services\AuditService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ContributionManagementController extends Controller
{
    public function campaigns(Request $request, Parish $parish): JsonResponse
    {
        Gate::authorize('view', $parish);
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'q' => ['sometimes', 'string', 'min:2', 'max:100'],
            'status' => ['sometimes', Rule::in(['draft', 'upcoming', 'active', 'completed', 'cancelled'])],
        ]);
        $campaigns = $parish->contributionCampaigns()
            ->when(isset($validated['q']), function (Builder $query) use ($validated): void {
                $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $validated['q']).'%';
                $query->where('title', 'like', $term);
            })
            ->when(isset($validated['status']), fn (Builder $query) => $query->where('status', $validated['status']))
            ->withCount('payments')
            ->withSum(['payments as confirmed_amount' => fn (Builder $query) => $query->where('status', 'confirmed')], 'amount')
            ->latest('id')
            ->paginate(perPage: $request->integer('per_page', 25), page: $request->integer('page', 1));

        return ApiResponse::page($campaigns, ContributionCampaignResource::collection($campaigns->getCollection())->resolve($request));
    }

    public function storeCampaign(SaveContributionCampaignRequest $request, Parish $parish, AuditService $audit): JsonResponse
    {
        Gate::authorize('update', $parish);
        $campaign = $parish->contributionCampaigns()->create($request->validated());
        $audit->record($request->user(), 'contribution_campaign.created', 'contribution_campaigns', $campaign->id, new: $campaign->attributesToArray());

        return ApiResponse::success((new ContributionCampaignResource($campaign))->resolve($request), 201);
    }

    public function updateCampaign(SaveContributionCampaignRequest $request, ContributionCampaign $campaign, AuditService $audit): JsonResponse
    {
        Gate::authorize('update', $campaign->parish);
        $before = $campaign->attributesToArray();
        $campaign->update($request->validated());
        $audit->record($request->user(), 'contribution_campaign.updated', 'contribution_campaigns', $campaign->id, $before, $campaign->attributesToArray());

        return ApiResponse::success((new ContributionCampaignResource($campaign->fresh()))->resolve($request));
    }

    public function destroyCampaign(Request $request, ContributionCampaign $campaign, AuditService $audit): JsonResponse
    {
        Gate::authorize('update', $campaign->parish);
        if ($campaign->payments()->exists()) {
            throw ValidationException::withMessages([
                'campaign' => 'A contribution campaign with payments cannot be deleted.',
            ]);
        }
        $before = $campaign->attributesToArray();
        $campaign->delete();
        $audit->record($request->user(), 'contribution_campaign.deleted', 'contribution_campaigns', $campaign->id, $before);

        return ApiResponse::success(['deleted' => true]);
    }

    public function payments(Request $request, Parish $parish): JsonResponse
    {
        Gate::authorize('view', $parish);
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'campaign_id' => ['sometimes', 'integer', 'exists:contribution_campaigns,id'],
            'status' => ['sometimes', Rule::in(['pending', 'confirmed', 'rejected'])],
            'q' => ['sometimes', 'string', 'min:2', 'max:100'],
        ]);
        $payments = ContributionPayment::query()
            ->whereHas('campaign', fn (Builder $query) => $query->where('parish_id', $parish->id))
            ->with(['campaign:id,parish_id,title', 'member:id,member_code,first_name,middle_name,last_name'])
            ->when(isset($validated['campaign_id']), fn (Builder $query) => $query->where('contribution_campaign_id', $validated['campaign_id']))
            ->when(isset($validated['status']), fn (Builder $query) => $query->where('status', $validated['status']))
            ->when(isset($validated['q']), function (Builder $query) use ($validated): void {
                $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $validated['q']).'%';
                $query->where(fn (Builder $query) => $query
                    ->where('reference', 'like', $term)
                    ->orWhere('receipt_number', 'like', $term)
                    ->orWhereHas('member', fn (Builder $member) => $member
                        ->where('member_code', 'like', $term)
                        ->orWhere('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)));
            })
            ->latest('requested_at')
            ->latest('id')
            ->paginate(perPage: $request->integer('per_page', 25), page: $request->integer('page', 1));

        return ApiResponse::page($payments, ContributionPaymentResource::collection($payments->getCollection())->resolve($request));
    }

    public function reviewPayment(ReviewContributionPaymentRequest $request, ContributionPayment $payment, AuditService $audit): JsonResponse
    {
        $payment->loadMissing('campaign.parish');
        Gate::authorize('update', $payment->campaign->parish);
        $before = $payment->attributesToArray();
        $attributes = $request->validated();
        $attributes['confirmed_at'] = $attributes['status'] === 'confirmed' ? now() : null;
        if ($attributes['status'] === 'confirmed' && empty($attributes['receipt_number']) && empty($payment->receipt_number)) {
            $attributes['receipt_number'] = 'RCT-'.now()->format('Ymd').'-'.Str::upper(Str::random(10));
        }
        $payment->update($attributes);
        $audit->record($request->user(), 'contribution_payment.reviewed', 'contribution_payments', $payment->id, $before, $payment->attributesToArray());

        return ApiResponse::success((new ContributionPaymentResource($payment->fresh()->load(['campaign:id,parish_id,title', 'member:id,member_code,first_name,middle_name,last_name'])))->resolve($request));
    }
}
