<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ContributionCampaign;
use App\Models\ContributionPayment;
use App\Models\MemberSacrament;
use App\Models\MemberServiceRequest;
use App\Models\Parish;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ParishReportController extends Controller
{
    public function __invoke(Request $request, Parish $parish, string $type): JsonResponse
    {
        Gate::authorize('view', $parish);
        abort_unless(in_array($type, ['membership', 'contributions', 'sacraments', 'annual'], true), 404);

        $data = match ($type) {
            'membership' => $this->membership($parish),
            'contributions' => $this->contributions($parish),
            'sacraments' => $this->sacraments($parish),
            'annual' => [
                'membership' => $this->membership($parish),
                'contributions' => $this->contributions($parish),
                'sacraments' => $this->sacraments($parish),
                'communications' => [
                    'announcements' => $parish->announcements()->whereYear('created_at', now()->year)->count(),
                    'events' => $parish->events()->whereYear('starts_at', now()->year)->count(),
                    'projects' => $parish->projects()->count(),
                ],
            ],
        };

        return ApiResponse::success([
            'type' => $type,
            'generated_at' => now()->toIso8601String(),
            'parish' => [
                'id' => $parish->id,
                'code' => $parish->code,
                'name' => $parish->name,
            ],
            'data' => $data,
        ]);
    }

    /** @return array<string, mixed> */
    private function membership(Parish $parish): array
    {
        $members = $parish->members();

        return [
            'members_total' => (clone $members)->count(),
            'members_by_status' => (clone $members)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'members_by_gender' => (clone $members)->selectRaw('gender, COUNT(*) as total')->groupBy('gender')->pluck('total', 'gender'),
            'families_total' => $parish->families()->count(),
            'zones_total' => $parish->zones()->count(),
            'jumuiyas_total' => $parish->jumuiyas()->count(),
            'outstations_total' => $parish->outstations()->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function contributions(Parish $parish): array
    {
        $campaigns = ContributionCampaign::query()->where('parish_id', $parish->id);
        $payments = ContributionPayment::query()
            ->whereHas('campaign', fn (Builder $query) => $query->where('parish_id', $parish->id));

        return [
            'campaigns_total' => (clone $campaigns)->count(),
            'campaigns_by_status' => (clone $campaigns)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'target_amount' => (float) (clone $campaigns)->sum('target_amount'),
            'confirmed_amount' => (float) (clone $payments)->where('status', 'confirmed')->sum('amount'),
            'pending_amount' => (float) (clone $payments)->where('status', 'pending')->sum('amount'),
            'payments_by_status' => (clone $payments)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
        ];
    }

    /** @return array<string, mixed> */
    private function sacraments(Parish $parish): array
    {
        $sacraments = MemberSacrament::query()
            ->whereHas('member', fn (Builder $query) => $query->where('parish_id', $parish->id));
        $requests = MemberServiceRequest::query()
            ->whereHas('member', fn (Builder $query) => $query->where('parish_id', $parish->id));

        return [
            'sacraments_total' => (clone $sacraments)->count(),
            'sacraments_by_name' => (clone $sacraments)->selectRaw('name, COUNT(*) as total')->groupBy('name')->pluck('total', 'name'),
            'sacraments_by_status' => (clone $sacraments)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'requests_total' => (clone $requests)->count(),
            'requests_by_status' => (clone $requests)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'requests_by_type' => (clone $requests)->selectRaw('type, COUNT(*) as total')->groupBy('type')->pluck('total', 'type'),
        ];
    }
}
