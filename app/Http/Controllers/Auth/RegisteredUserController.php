<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Student;
use App\Models\Account;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Show the registration page.
     */
    public function create(): Response
    {
        return Inertia::render('auth/Register', [
            'courses' => $this->allCourses(),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'last_name'      => 'required|string|max:255',
            'first_name'     => 'required|string|max:255',
            'middle_initial' => 'nullable|string|max:10',
            'email'          => 'required|string|lowercase|email|max:255|unique:' . User::class,
            'password'       => ['required', 'confirmed', Rules\Password::defaults()],
            'birthday'       => 'required|date',
            'year_level'     => 'required|string|max:50',
            'course'         => 'required|string|max:255',
            'address'        => 'required|string|max:255',
            'phone'          => 'required|string|max:20',
        ]);

        DB::beginTransaction();
        try {
            $accountId = $this->generateUniqueAccountId();

            $user = User::create([
                'last_name'      => $request->last_name,
                'first_name'     => $request->first_name,
                'middle_initial' => $request->middle_initial,
                'email'          => $request->email,
                'password'       => Hash::make($request->password),
                'birthday'       => $request->birthday,
                'year_level'     => $request->year_level,
                'course'         => $request->course,
                'address'        => $request->address,
                'phone'          => $request->phone,
                'account_id'     => $accountId,
                'status'         => User::STATUS_ACTIVE,
                'role'           => 'student',
            ]);

            // FIX: total_balance removed from students table (migration 2026_03_17_000001).
            // Balance is owned exclusively by accounts.balance via AccountService.
            Student::create([
                'user_id'           => $user->id,
                'student_id'        => $accountId,
                'enrollment_status' => 'active',
            ]);

            // Create Account record — authoritative balance starts at 0.
            Account::create([
                'user_id' => $user->id,
                'balance' => 0,
            ]);

            DB::commit();

            event(new Registered($user));

            Auth::login($user);

            return to_route('dashboard');

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Generate a unique student account ID in format YYYY-NNNN.
     *
     * NOTE: lockForUpdate() requires an active transaction to hold the lock.
     * This method is called inside the DB::transaction() block in store().
     */
    private function generateUniqueAccountId(): string
    {
        $year = now()->year;

        $lastStudent = User::where('account_id', 'like', "{$year}-%")
            ->lockForUpdate()
            ->orderByRaw('CAST(SUBSTRING(account_id, 6) AS UNSIGNED) DESC')
            ->first();

        if ($lastStudent) {
            $lastNumber = intval(substr($lastStudent->account_id, -4));
            $newNumber  = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        $newAccountId = "{$year}-{$newNumber}";

        $attempts = 0;
        while (User::where('account_id', $newAccountId)->exists() && $attempts < 10) {
            $lastNumber   = intval($newNumber);
            $newNumber    = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
            $newAccountId = "{$year}-{$newNumber}";
            $attempts++;
        }

        if ($attempts >= 10) {
            throw new \Exception('Unable to generate unique account ID after multiple attempts.');
        }

        return $newAccountId;
    }

    /**
     * Get all available courses from the system.
     *
     * Reads from a hardcoded list. This should be moved to a config or database
     * table (Courses model) in a future refactor.
     */
    private function allCourses(): array
    {
        // All CCDI courses available per CCDI brochure
        return [
            // Computer Science & IT Programs
            'BS Computer Science',
            'BS Information Technology',
            'BS Information Systems',
            
            // Associate Program
            'Associate in Computer Technology - Programming',
            'Associate in Computer Technology - Networking',
            'Associate in Computer Technology - Animation',
            
            // Engineering Technology Programs
            'BS Electronics Engineering Technology',
            'BS Electrical Engineering Technology',
        ];
    }
}