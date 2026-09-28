<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\MemberSacrament;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MemberSacrament> */
class MemberSacramentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'name' => fake()->randomElement(['Baptism', 'First Holy Communion', 'Confirmation', 'Marriage']),
            'received_on' => fake()->date(),
            'place' => fake()->city().' Parish',
            'status' => 'verified',
        ];
    }
}
