<?php

namespace App\Console\Commands;

use App\Models\Plan;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Creates (or updates) the admin account. Used by deploy/install.sh.
 * The password is read from the SETUP_ADMIN_PASSWORD environment variable
 * (so it never shows up in the process list) or asked interactively.
 */
class SetupAdmin extends Command
{
    protected $signature = 'app:admin
        {--name= : Admin name}
        {--email= : Admin email (used to log in)}
        {--phone= : Admin mobile number (optional)}
        {--site-name= : Also set the site name shown in the app}
        {--check : Only check whether an admin exists (exit code 0 = yes)}';

    protected $description = 'Create or update the admin account';

    public function handle(): int
    {
        if ($this->option('check')) {
            return User::query()->where('role', 'admin')->exists() ? self::SUCCESS : self::FAILURE;
        }

        $email = strtolower(trim((string) ($this->option('email') ?: $this->ask('Admin email'))));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid email.');

            return self::FAILURE;
        }

        $password = (string) (getenv('SETUP_ADMIN_PASSWORD') ?: $this->secret('Admin password'));
        if (strlen($password) < 8) {
            $this->error('Password must be at least 8 characters.');

            return self::FAILURE;
        }

        $phone = preg_replace('/\D+/', '', (string) $this->option('phone'));
        if (str_starts_with($phone, '92') && strlen($phone) === 12) {
            $phone = '0'.substr($phone, 2);
        }

        $user = User::query()->firstOrNew(['email' => $email]);

        if ($phone !== '' && User::query()->where('phone', $phone)->where('id', '!=', $user->id ?? 0)->exists()) {
            $this->error("Phone {$phone} is already used by another account.");

            return self::FAILURE;
        }

        $user->name = $this->option('name') ?: ($user->name ?: 'Admin');
        if ($phone !== '') {
            $user->phone = $phone;
        }
        $user->password = $password;
        $user->role = 'admin';
        $user->status = 'active';
        $user->save();

        $plan = Plan::defaultPlan();
        if ($plan->exists && ! $user->subscriptions()->exists()) {
            Subscription::query()->create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'starts_at' => now(),
                'status' => 'active',
            ]);
        }

        if ($siteName = trim((string) $this->option('site-name'))) {
            Setting::put(['site_name' => $siteName]);
        }

        $this->info("Admin ready: {$email}");

        return self::SUCCESS;
    }
}
