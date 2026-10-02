<?php

namespace Database\Factories;

use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * A per-process counter folded into every address. `fake()->unique()`
     * guarantees nothing across tests (tests/Support/UniqueFixtureHelpers.php),
     * and it reddened PR #658 with `Duplicate entry 'hlittel@example.com'` on
     * a commit that touched no PHP.
     */
    protected static int $sequence = 0;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $email = fake()->safeEmail();

        return [
            'name' => fake()->name(),
            'email' => sprintf('%s.%05d@%s', Str::before($email, '@'), ++static::$sequence, Str::after($email, '@')),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
