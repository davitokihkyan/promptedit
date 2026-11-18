<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:make-admin {email}')]
#[Description('Let an existing user see the operators\' spend report')]
final class MakeAdmin extends Command
{
    public function handle(): int
    {
        $user = User::query()->firstWhere('email', $this->argument('email'));

        if ($user === null) {
            $this->error('No user has that email address.');

            return self::FAILURE;
        }

        $user->forceFill(['is_admin' => true])->save();
        $this->info("{$user->email} is now an admin.");

        return self::SUCCESS;
    }
}
