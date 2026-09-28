<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\MemberSacrament;
use App\Models\MemberServiceRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MemberServiceRequest> */
class MemberServiceRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'member_sacrament_id' => MemberSacrament::factory(),
            'type' => 'certificate',
            'message' => null,
            'status' => 'pending',
            'requested_at' => now(),
        ];
    }
}
