<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Illuminate\Validation\Rule;
use App\Models\Student;

class ProfileController extends Controller
{
    /**
     * Show profile edit form.
     */
    public function edit(Request $request)
    {
        return Inertia::render('Settings/Profile', [
            'user' => $request->user()->load('student'),
            'mustVerifyEmail' => method_exists($request->user(), 'hasVerifiedEmail')
                ? !$request->user()->hasVerifiedEmail()
                : false,
        ]);
    }

    /**
     * Update profile information.
     */
    public function update(Request $request)
    {
        $user = $request->user();

        // Base validation rules for all users
        $rules = [
            'last_name' => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_initial' => ['nullable', 'string', 'max:10'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'birthday' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^([0-9\s\-\+\(\)]*)$/'],
            'address' => ['nullable', 'string', 'max:500'],
        ];

        // Add student-specific validation rules
        if ($user->role === 'student') {
            $rules['account_id'] = [
                'nullable',
                'string',
                'max:50',
                Rule::unique('users', 'account_id')->ignore($user->id),
            ];
            $rules['course'] = ['required', 'string', 'max:255'];
            $rules['year_level'] = ['required', 'string', 'max:50', 'in:1st Year,2nd Year,3rd Year,4th Year'];
            
            // Only admin can change student status
            if ($request->user()->role === 'admin') {
                $rules['status'] = ['required', Rule::in(['active', 'graduated', 'dropped'])];
            }
        }

        // Add faculty field for accounting/admin users
        if (in_array($user->role, ['accounting', 'admin'])) {
            $rules['faculty'] = ['nullable', 'string', 'max:255'];
        }

        $data = $request->validate($rules);

        // Prevent non-admin from changing status
        if ($request->has('status') && $user->role !== 'admin') {
            unset($data['status']);
        }

        // Update users table - common fields for all roles
        $userUpdateData = [
            'last_name' => $data['last_name'],
            'first_name' => $data['first_name'],
            'middle_initial' => $data['middle_initial'] ?? null,
            'email' => $data['email'],
            'birthday' => $data['birthday'] ?? $user->birthday,
            'phone' => $data['phone'] ?? $user->phone,
            'address' => $data['address'] ?? $user->address,
        ];

        // Add role-specific fields to users table
        if ($user->role === 'student') {
            $userUpdateData['account_id'] = $data['account_id'] ?? $user->account_id;
            $userUpdateData['course'] = $data['course'];
            $userUpdateData['year_level'] = $data['year_level'];
            
            // Update status in users table if admin is updating
            if (isset($data['status']) && $request->user()->role === 'admin') {
                $userUpdateData['status'] = $data['status'];
            }
        }

        // Add faculty for accounting/admin
        if (in_array($user->role, ['accounting', 'admin'])) {
            $userUpdateData['faculty'] = $data['faculty'] ?? $user->faculty;
        }

        // Update the user
        $user->fill($userUpdateData);
        $user->save();

        // Log the update for debugging
        Log::info('User profile updated', [
            'user_id' => $user->id,
            'updated_fields' => array_keys($userUpdateData),
            'birthday' => $user->birthday,
            'course' => $user->course ?? 'N/A',
            'year_level' => $user->year_level ?? 'N/A',
        ]);

        // Update students table if user is a student
        if ($user->role === 'student' && $user->student) {
            $statusMap = [
                'active' => 'enrolled',
                'graduated' => 'graduated',
                'dropped' => 'inactive',
            ];

            $studentData = [
                'last_name' => $data['last_name'],
                'first_name' => $data['first_name'],
                'middle_initial' => $data['middle_initial'] ?? null,
                'student_id' => $data['student_id'] ?? $user->student->student_id,
                'email' => $data['email'],
                'birthday' => $data['birthday'] ?? $user->student->birthday,
                'phone' => $data['phone'] ?? $user->student->phone,
                'address' => $data['address'] ?? $user->student->address,
                'course' => $data['course'],
                'year_level' => $data['year_level'],
            ];

            // Update status if provided and user is admin
            if (isset($data['status']) && $request->user()->role === 'admin') {
                $studentData['status'] = $statusMap[$data['status']] ?? $user->student->status;
            }

            $user->student->update($studentData);

            // Log student table update
            Log::info('Student record updated', [
                'student_id' => $user->student->id,
                'course' => $studentData['course'],
                'year_level' => $studentData['year_level'],
                'birthday' => $studentData['birthday'],
            ]);
        }

        return back()->with('success', 'Profile updated successfully.');
    }

    /**
     * Update profile picture.
     */
    public function updateProfilePicture(Request $request)
    {
        $request->validate([
            'profile_picture' => 'required|image|max:2048',
        ]);

        $user = $request->user();

        // Delete old file if exists
        if ($user->profile_picture) {
            Storage::disk('public')->delete($user->profile_picture);
        }

        $path = $request->file('profile_picture')->store('profile_pictures', 'public');

        $user->update(['profile_picture' => $path]);

        return back()->with('success', 'Profile picture updated.');
    }

    /**
     * Remove profile picture.
     */
    public function removeProfilePicture(Request $request)
    {
        $user = $request->user();

        if ($user->profile_picture) {
            Storage::disk('public')->delete($user->profile_picture);
            $user->update(['profile_picture' => null]);
        }

        return back()->with('success', 'Profile picture removed.');
    }

    /**
     * 🚫 Account deletion is prohibited.
     */
    public function destroy()
    {
        abort(403, 'User deletion is prohibited.');
    }
}