<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EnquiryFactory extends Factory
{
    public function definition(): array
    {
        $seenAt = $this->faker->optional(0.6)->dateTimeBetween('-30 days', 'now');

        return [
            'source' => $this->faker->randomElement([
                'contact-form', 'landing-page', 'pricing-page',
                'blog', 'demo-request', 'support-form',
            ]),
            'source_url' => $this->faker->optional(0.7)->url(),
            'status' => $this->faker->randomElement(['new', 'seen', 'pending', 'closed']),
            'seen_at' => $seenAt,
            'seen_by' => $seenAt ? User::inRandomOrder()->value('id') : null,
            'data' => [
                'name' => $this->faker->name(),
                'email' => $this->faker->safeEmail(),
                'phone' => $this->faker->optional(0.6)->phoneNumber(),
                'company' => $this->faker->optional(0.4)->company(),
                'message' => $this->faker->optional(0.8)->paragraph(2),
            ],
        ];
    }
}
