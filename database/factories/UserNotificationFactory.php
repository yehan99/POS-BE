<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\UserNotification>
 */
class UserNotificationFactory extends Factory
{
    protected $model = UserNotification::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'body' => fake()->sentence(12),
            'category' => fake()->randomElement(['system', 'inventory', 'sales']),
            'severity' => fake()->randomElement(['info', 'success', 'warning', 'critical']),
            'data' => [
                'link' => '/dashboard',
            ],
            'is_read' => false,
            'read_at' => null,
        ];
    }
}
