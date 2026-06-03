<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\User;
use App\Models\Department;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

class UtilityController extends Controller
{
    /**
     * Check if current user is superadmin.
     */
    private function checkSuperadmin(Request $request): ?JsonResponse
    {
        if ($request->user()->role !== 'superadmin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only superadmins can access this utility.',
            ], 403);
        }
        return null;
    }

    /**
     * Get a setting from storage/app/settings.json.
     */
    private function getSetting(string $key, $default = null)
    {
        if (!Storage::disk('local')->exists('settings.json')) {
            return $default;
        }

        try {
            $contents = Storage::disk('local')->get('settings.json');
            $data = json_decode($contents, true);
            if (is_array($data) && array_key_exists($key, $data)) {
                return $data[$key];
            }
        } catch (\Exception $e) {
            // Log or fallback
        }

        return $default;
    }

    /**
     * Set a setting in storage/app/settings.json.
     */
    private function setSetting(string $key, $value): void
    {
        $data = [];
        if (Storage::disk('local')->exists('settings.json')) {
            try {
                $contents = Storage::disk('local')->get('settings.json');
                $decoded = json_decode($contents, true);
                if (is_array($decoded)) {
                    $data = $decoded;
                }
            } catch (\Exception $e) {
                // Ignore and overwrite/reset if corrupt
            }
        }

        $data[$key] = $value;
        Storage::disk('local')->put('settings.json', json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * Unlock utilities.
     */
    public function unlockUtilities(Request $request): JsonResponse
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $correctPassword = $this->getSetting('unlock_password', 'Sut@1204');

        if ($request->input('password') === $correctPassword) {
            return response()->json([
                'success' => true,
                'message' => 'Utilities unlocked.',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Incorrect passcode. Please try again.',
        ], 422);
    }

    /**
     * Get the unlock password (superadmin only).
     */
    public function getUnlockPassword(Request $request): JsonResponse
    {
        if ($err = $this->checkSuperadmin($request)) return $err;

        $password = $this->getSetting('unlock_password', 'Sut@1204');

        return response()->json([
            'success' => true,
            'password' => $password,
        ]);
    }

    /**
     * Save the unlock password (superadmin only).
     */
    public function saveUnlockPassword(Request $request): JsonResponse
    {
        if ($err = $this->checkSuperadmin($request)) return $err;

        $request->validate([
            'password' => 'required|string|min:4|max:255',
        ]);

        $this->setSetting('unlock_password', $request->input('password'));

        return response()->json([
            'success' => true,
            'message' => 'Unlock passcode updated successfully.',
        ]);
    }

    /**
     * Get all users.
     */
    public function getUsers(Request $request): JsonResponse
    {
        if ($err = $this->checkSuperadmin($request)) return $err;

        $users = User::with('department')->orderBy('id')->get();
        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    /**
     * Create or update a user.
     */
    public function saveUser(Request $request): JsonResponse
    {
        if ($err = $this->checkSuperadmin($request)) return $err;

        $userId = $request->input('id');

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'string',
                'max:255',
                Rule::unique('users')->ignore($userId),
            ],
            'password' => $userId ? 'nullable|string|min:8' : 'required|string|min:8',
            'role' => 'required|string|in:superadmin,user',
            'department_id' => 'nullable|integer|exists:departments,id',
        ]);

        if ($userId) {
            $user = User::findOrFail($userId);
        } else {
            $user = new User();
        }

        $user->name = $request->input('name');
        $user->email = $request->input('email');
        $user->role = $request->input('role');
        $user->department_id = $request->input('department_id');

        if ($request->filled('password')) {
            $user->password = Hash::make($request->input('password'));
        }

        $user->save();

        return response()->json([
            'success' => true,
            'message' => $userId ? 'User updated successfully.' : 'User created successfully.',
            'data' => $user->load('department'),
        ]);
    }

    /**
     * Delete a user.
     */
    public function deleteUser(Request $request, User $user): JsonResponse
    {
        if ($err = $this->checkSuperadmin($request)) return $err;

        // Prevent self-deletion
        if ($request->user()->id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own account.',
            ], 422);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully.',
        ]);
    }

    /**
     * Get all departments.
     */
    public function getDepartments(Request $request): JsonResponse
    {
        if ($err = $this->checkSuperadmin($request)) return $err;

        $departments = Department::withCount('users')->orderBy('name')->get();
        return response()->json([
            'success' => true,
            'data' => $departments,
        ]);
    }

    /**
     * Create or update a department.
     */
    public function saveDepartment(Request $request): JsonResponse
    {
        if ($err = $this->checkSuperadmin($request)) return $err;

        $deptId = $request->input('id');

        $request->validate([
            'name' => 'required|string|max:255',
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('departments')->ignore($deptId),
            ],
        ]);

        if ($deptId) {
            $dept = Department::findOrFail($deptId);
        } else {
            $dept = new Department();
        }

        $dept->name = $request->input('name');
        $dept->code = $request->input('code') ?: null;
        $dept->save();

        return response()->json([
            'success' => true,
            'message' => $deptId ? 'Department updated successfully.' : 'Department created successfully.',
            'data' => $dept->loadCount('users'),
        ]);
    }

    /**
     * Delete a department.
     */
    public function deleteDepartment(Request $request, Department $department): JsonResponse
    {
        if ($err = $this->checkSuperadmin($request)) return $err;

        // Optionally, check if any users are assigned to this department
        if ($department->users()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete department. There are users assigned to it.',
            ], 422);
        }

        $department->delete();

        return response()->json([
            'success' => true,
            'message' => 'Department deleted successfully.',
        ]);
    }
}
