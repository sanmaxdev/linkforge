<?php

namespace App\Actions\Fortify;

use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;
use App\Rules\Turnstile;
use App\Services\Affiliate\ReferralService;
use App\Services\Mail\Postman;
use App\Services\Safety\LinkSafety;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
            'cf-turnstile-response' => [new Turnstile],
        ])->validate();

        // Honeypot: a hidden field that only bots fill in.
        if (! empty($input['company'])) {
            throw ValidationException::withMessages(['email' => 'Registration could not be completed.']);
        }

        // Block throwaway / disposable email domains.
        if (app(LinkSafety::class)->isDisposableEmail($input['email'])) {
            throw ValidationException::withMessages(['email' => 'Please sign up with a permanent email address.']);
        }

        // Operator email-domain blocklist (Settings -> General).
        $blocked = collect(preg_split('/[\s,]+/', (string) Setting::get('signup_blocked_domains'), -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn ($d) => ltrim(mb_strtolower(trim($d)), '@'))->filter()->all();
        $emailDomain = mb_strtolower((string) substr((string) strrchr($input['email'], '@'), 1));
        if ($emailDomain !== '' && in_array($emailDomain, $blocked, true)) {
            throw ValidationException::withMessages(['email' => 'Registrations from this email domain are not allowed.']);
        }

        // Starting plan: the operator-selected default (Settings -> General), else Free.
        $defaultPlanId = Setting::get('signup_default_plan');
        $free = ($defaultPlanId ? Plan::find($defaultPlanId) : null) ?: Plan::where('slug', 'free')->first();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
            'plan_id' => $free?->id,
            'ai_credits' => (int) ($free?->limit('ai_credits') ?? 0),
        ]);

        // Attribute the signup to a referrer if they arrived via a referral link.
        app(ReferralService::class)
            ->attributeSignup($user, request()->cookie('affiliate_ref'));

        $postman = app(Postman::class);
        $postman->send('welcome', $user->email, [
            'name' => $user->name, 'email' => $user->email, 'action_url' => route('dashboard'),
        ]);
        $postman->send('admin_new_user', User::where('role', 'admin')->pluck('email')->all(), [
            'customer_name' => $user->name, 'customer_email' => $user->email, 'action_url' => route('admin.users.show', $user),
        ]);

        return $user;
    }
}
