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
        return Inertia::render('auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'last_name' => 'required|string|max:255',
            'first_name' => 'required|string|max:255',
            'middle_initial' => 'nullable|string|max:10',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'birthday' => 'required|date',
            'year_level' => 'required|string|max:50',
            'course' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
        ]);

        DB::beginTransaction();
        try {
            // Auto-generate Account ID
            $accountId = $this->generateUniqueAccountId();

            // Create user record
            $user = User::create([
                'last_name' => $request->last_name,
                'first_name' => $request->first_name,
                'middle_initial' => $request->middle_initial,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'birthday' => $request->birthday,
                'year_level' => $request->year_level,
                'course' => $request->course,
                'address' => $request->address,
                'phone' => $request->phone,
                'account_id' => $accountId,
                'status' => User::STATUS_ACTIVE,
                'role' => 'student', // default new users to student role
            ]);

            // Create Student record with only student-specific fields
            // Personal info is already in the User record
            Student::create([
                'user_id' => $user->id,
                'student_id' => $accountId,
                'enrollment_status' => 'active',
                'total_balance' => 0,
            ]);

            // Create Account record for new student
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
     * Generate unique student account ID
     * 
     * Note: This method is called BEFORE the main transaction, so it doesn't
     * create nested transactions which can cause issues in tests.
     */
    private function generateUniqueAccountId(): string
    {
        $year = now()->year;
        
        // Lock the table to prevent concurrent ID generation
        $lastStudent = User::where('account_id', 'like', "{$year}-%")
            ->lockForUpdate()
            ->orderByRaw('CAST(SUBSTRING(account_id, 6) AS UNSIGNED) DESC')
            ->first();

        if ($lastStudent) {
            // Extract the number part and increment
            $lastNumber = intval(substr($lastStudent->account_id, -4));
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        $newAccountId = "{$year}-{$newNumber}";
        
        // Double-check uniqueness
        $attempts = 0;
        while (User::where('account_id', $newAccountId)->exists() && $attempts < 10) {
            $lastNumber = intval($newNumber);
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
            $newAccountId = "{$year}-{$newNumber}";
            $attempts++;
        }
        
        if ($attempts >= 10) {
            throw new \Exception('Unable to generate unique account ID after multiple attempts.');
        }
        
        return $newAccountId;
    }
}