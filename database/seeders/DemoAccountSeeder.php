<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds repeatable demo/review accounts for staging and Google Play review.
 *
 * Idempotent — keyed on email via updateOrCreate — so it can be re-run safely
 * (on every deploy, or whenever a new reviewer account is needed) without
 * creating duplicates. Re-running resets each account's password to its env
 * value, which is the intended behaviour for a reproducible demo set (the env
 * var is the source of truth, not a password typed into the UI).
 *
 * Passwords come ONLY from env vars — never hardcoded in source. Any account
 * whose password env var is unset is skipped with a warning, so this seeder
 * can never create a guessable default credential. Set the relevant var(s)
 * before running:
 *
 *   REVIEW_ACCOUNT_PASSWORD  — the Play Console "Sign-in details" login
 *   ADMIN_ACCOUNT_PASSWORD   — the Filament admin-panel login
 *
 * The `password` is auto-hashed by the User model's `'hashed'` cast, so the
 * plaintext never reaches the database.
 *
 * To add another account, add a row to $accounts — nothing else changes.
 */
class DemoAccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            // Google Play reviewer login — customer role, low privilege. This is
            // the account pasted into Play Console's "Sign-in details" field.
            [
                'name' => 'Play Store Review',
                'email' => 'playreview@drivewaymorocco.com',
                'role' => Role::Customer,
                'passwordEnv' => 'REVIEW_ACCOUNT_PASSWORD',
            ],
            // Admin panel login (Filament) — Staff/Admin role.
            [
                'name' => 'Admin',
                'email' => 'admin@drivewaymorocco.com',
                'role' => Role::Admin,
                'passwordEnv' => 'ADMIN_ACCOUNT_PASSWORD',
            ],
        ];

        foreach ($accounts as $account) {
            $password = env($account['passwordEnv']);

            if ($password === null || $password === '') {
                $this->command?->warn(
                    "Skipping {$account['email']}: set {$account['passwordEnv']} to seed this account."
                );

                continue;
            }

            User::query()->updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'email' => $account['email'],
                    // 'hashed' cast on User::password auto-hashes on assignment.
                    'password' => $password,
                    // The User implements MustVerifyEmail; stamp it verified so
                    // these accounts can log in without a verification round-trip.
                    'email_verified_at' => now(),
                    'role' => $account['role'],
                ],
            );
        }
    }
}
