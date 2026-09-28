<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProvisionMemberAccount extends Command
{
    protected $signature = 'member:account
        {member_code : Existing active member code}
        {email : Email used to sign in}
        {--name= : Account display name; defaults to the member name}
        {--password= : Password; omit this option to enter it securely}';

    protected $description = 'Create a login account linked to an existing parish member';

    public function handle(AuditService $audit): int
    {
        $memberCode = trim((string) $this->argument('member_code'));
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $password = (string) ($this->option('password') ?: $this->secret('Password'));
        $member = Member::query()->where('member_code', $memberCode)->where('status', 'active')->first();

        if (! $member) {
            $this->error('No active member exists with that member code.');

            return self::FAILURE;
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Enter a valid email address.');

            return self::FAILURE;
        }
        if (mb_strlen($password) < 8) {
            $this->error('The password must contain at least 8 characters.');

            return self::FAILURE;
        }
        if (User::query()->where('email', $email)->orWhere('member_id', $member->id)->exists()) {
            $this->error('That email or member record already has an account.');

            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($audit, $email, $member, $password): User {
            $defaultName = collect([$member->first_name, $member->middle_name, $member->last_name])
                ->filter()
                ->implode(' ');
            $user = new User;
            $user->name = trim((string) ($this->option('name') ?: $defaultName));
            $user->email = $email;
            $user->password = $password;
            $user->email_verified_at = now();
            $user->member()->associate($member);
            $user->save();
            $audit->record(null, 'member_account.created', 'users', $user->id);

            return $user;
        });

        $this->info("Member account {$user->email} created successfully.");

        return self::SUCCESS;
    }
}
