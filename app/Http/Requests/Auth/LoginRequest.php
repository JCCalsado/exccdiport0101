<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'role'     => ['required', 'string', 'in:admin,accounting,student'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     * Enforces: credentials match → role match → account is active.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $credentials  = $this->only('email', 'password');
        $selectedRole = $this->input('role');

        // Step 1 — Verify credentials.
        if (! Auth::attempt($credentials, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        $user     = Auth::user();
        $userRole = $this->getUserRole($user);

        // Step 2 — Verify the selected role matches the user's actual role.
        if ($userRole !== $selectedRole) {
            Auth::logout();
            RateLimiter::hit($this->throttleKey());

            $roleLabel = $this->getRoleLabel($selectedRole);

            throw ValidationException::withMessages([
                'email' => "Invalid {$roleLabel} credentials. Please check your role selection or use the correct login portal.",
            ]);
        }

        // Step 3 — Verify the account is active.
        // Deactivated users must not be allowed to create a new session.
        if (! $user->is_active) {
            Auth::logout();
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => 'Your account has been deactivated. Please contact an administrator.',
            ]);
        }

        // All checks passed — clear rate limiter.
        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Get the user's role, handling both string and enum types.
     */
    protected function getUserRole($user): string
    {
        $role = $user->role;

        if (is_object($role)) {
            return $role->value ?? (string) $role;
        }

        return (string) $role;
    }

    /**
     * Get human-readable role label.
     */
    protected function getRoleLabel(string $role): string
    {
        return match ($role) {
            'admin'      => 'Administrator',
            'accounting' => 'Accounting Staff',
            'student'    => 'Student',
            default      => ucfirst($role),
        };
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     * Role is included to prevent role enumeration attacks.
     */
    public function throttleKey(): string
    {
        return $this->string('email')
            ->lower()
            ->append('|' . $this->input('role'))
            ->append('|' . $this->ip())
            ->transliterate()
            ->value();
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'role.required'     => 'Please select a role to continue.',
            'role.in'           => 'Invalid role selected.',
            'email.required'    => 'Email address is required.',
            'email.email'       => 'Please enter a valid email address.',
            'password.required' => 'Password is required.',
        ];
    }
}