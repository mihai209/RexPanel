<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

#[Signature('create:user')]
#[Description('Create a new user interactively')]
class CreateUser extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Create a new RA-panel User');

        $username = $this->ask('Enter username');
        $email = $this->ask('Enter email address');
        $password = $this->secret('Enter password (min 6 characters)');
        $is_admin = $this->confirm('Should this user be an administrator?', false);

        $validator = Validator::make([
            'username' => $username,
            'email' => $email,
            'password' => $password,
        ], [
            'username' => ['required', 'string', 'unique:users,username'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return 1;
        }

        $user = User::create([
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'is_admin' => $is_admin,
        ]);

        if ($user) {
            $this->info('User created successfully');
            $this->line("User ID:  {$user->id}");
            $this->line("Username: {$user->username}");
        }

        return 0;
    }
}
