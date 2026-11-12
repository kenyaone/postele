<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class SetPassword extends Command
{
    protected $signature = 'pos:set-password {user : User id or exact name}';
    protected $description = 'Set (or reset) a manager password. Prompts without echoing it.';

    public function handle(): int
    {
        $arg  = $this->argument('user');
        $user = ctype_digit($arg) ? User::find($arg) : User::where('name', $arg)->first();
        if (! $user) {
            $this->error("No user found for '$arg'.");
            return 1;
        }

        $p1 = (string) $this->secret("New password for {$user->name} (8+ chars, letters and numbers)");
        $p2 = (string) $this->secret('Repeat it');

        if ($p1 !== $p2) { $this->error('The two passwords do not match.'); return 1; }
        if (strlen($p1) < 8 || ! preg_match('/[A-Za-z]/', $p1) || ! preg_match('/[0-9]/', $p1)) {
            $this->error('Use at least 8 characters with letters and numbers.');
            return 1;
        }

        $user->forceFill(['password' => $p1, 'password_set_at' => now()])->save();
        $this->info("Password set for {$user->name}. They now sign in with it instead of a PIN.");
        return 0;
    }
}
