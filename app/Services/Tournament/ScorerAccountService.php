<?php

namespace App\Services\Tournament;

use App\Helpers\CompanyAuth;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ScorerAccountService
{
    public function listing(): LengthAwarePaginator
    {
        $this->authorize();

        return User::where('is_scorer', true)->where('is_admin', false)->whereNull('company_id')
            ->orderByDesc('id')->paginate(10, ['id', 'name', 'email', 'email_verified_at']);
    }

    public function revoke(int $userId): void
    {
        $actor = $this->authorize();
        $key = 'tournament:scorer-account-revoke:'.$actor->id;
        if (RateLimiter::tooManyAttempts($key, 30)) {
            throw ValidationException::withMessages([
                'tournament' => 'Too many access changes. Please try again in '.RateLimiter::availableIn($key).' seconds.',
            ]);
        }
        RateLimiter::hit($key, 60);

        DB::transaction(function () use ($actor, $userId) {
            $current = User::whereKey($actor->id)->sharedLock()->first();
            abort_unless(config('tournament.enabled') === true, 404);
            abort_unless($current && $current->is_admin, 403);

            // Filter before locking: this action cannot edit admins, representatives,
            // pending accounts or an already revoked account, even via a direct call.
            $target = User::whereKey($userId)->where('is_scorer', true)
                ->where('is_admin', false)->whereNull('company_id')->lockForUpdate()->first();
            if (! $target) {
                throw ValidationException::withMessages([
                    'tournament' => 'Select an active dedicated scorer account. Reload the list and try again.',
                ]);
            }
            $target->is_scorer = false;
            $target->save();
        });
    }

    public function authorize(): User
    {
        abort_unless(config('tournament.enabled') === true, 404);
        $id = CompanyAuth::user()?->id;
        $actor = $id ? User::find($id) : null;
        abort_unless($actor && $actor->is_admin, 403);

        return $actor;
    }

    public function create(string $name, string $email): User
    {
        $actor = $this->authorize();
        $key = 'tournament:scorer-account-create:'.$actor->id;
        if (RateLimiter::tooManyAttempts($key, 10)) {
            throw ValidationException::withMessages([
                'tournament' => 'Too many account creation attempts. Please try again in '.RateLimiter::availableIn($key).' seconds.',
            ]);
        }
        RateLimiter::hit($key, 60);

        $data = Validator::make(['name' => trim($name), 'email' => mb_strtolower(trim($email))], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
        ])->validate();

        try {
            return DB::transaction(function () use ($actor, $data) {
                // Keep the actor's admin grant stable until this short write commits.
                $current = User::whereKey($actor->id)->sharedLock()->first();
                abort_unless(config('tournament.enabled') === true, 404);
                abort_unless($current && $current->is_admin, 403);

                if (User::whereRaw('LOWER(email) = ?', [$data['email']])->exists()) {
                    $this->duplicateEmail();
                }

                $user = new User;
                $user->name = $data['name'];
                $user->email = $data['email'];
                $user->company_id = null;
                $user->is_admin = false;
                // Deliberate explicit assignment; is_scorer stays outside $fillable.
                $user->is_scorer = true;
                $user->password = null;
                $user->email_verified_at = null;
                $user->jwt_token = null;
                $user->jwt_expires_at = null;
                $user->save();

                return $user->refresh();
            });
        } catch (UniqueConstraintViolationException $exception) {
            // Concurrent requests for the same normalized email: never update
            // the existing account or expose a database exception to the form.
            $this->duplicateEmail();
        }
    }

    private function duplicateEmail(): never
    {
        throw ValidationException::withMessages([
            'email' => 'This email already belongs to an account. Use a new email; existing accounts will not be changed.',
        ]);
    }
}
