<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * php artisan eazy:make-admin
 *
 * Creates an admin account interactively. The password is typed at a hidden
 * prompt, hashed by the User model, and never printed, logged or stored in
 * plain text. The admin role is assigned here on the server only.
 * Nothing creates admins automatically (no seeder, migration or deploy step).
 */
class MakeAdmin extends Command
{
    protected $signature = 'eazy:make-admin';

    protected $description = 'Create an Eazy Communication admin account (interactive, hidden password prompt)';

    public function handle(): int
    {
        $existingAdmins = User::withTrashed()->where('role', User::ROLE_ADMIN)->count();
        if ($existingAdmins > 0
            && ! $this->confirm("{$existingAdmins} admin account(s) already exist. Create another admin account?", false)) {
            $this->info('No account was created.');

            return self::SUCCESS;
        }

        $name = trim((string) $this->ask('Admin full name'));
        $email = Str::lower(trim((string) $this->ask('Admin email address')));

        $details = Validator::make(['name' => $name, 'email' => $email], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);
        if ($details->fails()) {
            return $this->failWith($details->errors()->all());
        }

        // Closed (soft-deleted) accounts count too: emails are unique in the table.
        if (User::withTrashed()->where('email', $email)->exists()) {
            return $this->failWith(['An account with this email address already exists. No account was created.']);
        }

        $password = (string) $this->secret('Password (hidden)');
        $confirmation = (string) $this->secret('Confirm password (hidden)');

        $check = Validator::make(
            ['password' => $password, 'password_confirmation' => $confirmation],
            ['password' => ['required', 'confirmed', Password::defaults()]],
        );
        if ($check->fails()) {
            return $this->failWith($check->errors()->all());
        }

        $user = new User;
        $user->name = $name;
        $user->email = $email;
        $user->password = $password;           // hashed by the model's "hashed" cast
        $user->role = User::ROLE_ADMIN;        // assigned on the server only
        $user->save();

        unset($password, $confirmation);

        $this->info("Admin account created for {$email} (user #{$user->id}). Sign in at /login, then open /admin.");

        return self::SUCCESS;
    }

    /** @param  list<string>  $messages */
    private function failWith(array $messages): int
    {
        foreach ($messages as $message) {
            $this->error($message);
        }

        return self::FAILURE;
    }
}
