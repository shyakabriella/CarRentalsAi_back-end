<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;
use App\Notifications\RegistrationNotification;
use Illuminate\Support\Str;

class RegisterController extends BaseController
{
    /**
     * Admin/User/Owner registration
     * - ALWAYS generates a password automatically.
     * - Assign Spatie role when provided (role_id / role / primary_role);
     *   otherwise default to "customer".
     * - Also writes the plain `users.role` column to match the assigned role.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:100'],
            'email'        => ['required', 'email', 'max:150', 'unique:users,email'],
            'phone'        => ['nullable', 'string', 'max:30'],

            // Spatie role name or id
            'role'         => ['nullable', 'string', Rule::exists('roles', 'name')],
            'role_id'      => ['nullable', 'integer', Rule::exists('roles', 'id')],

            // Optional primary role (e.g. "owner" when coming from /host/register)
            'primary_role' => ['nullable', 'string', Rule::exists('roles', 'name')],

            // notify user by email (default true)
            'notify'       => ['nullable', 'boolean'],
        ]);

        // ---- Resolve which Spatie role to assign ----
        $defaultRoleName = 'customer';

        $role = null;

        if (!empty($validated['role_id'])) {
            $role = Role::find($validated['role_id']);
        } else {
            $roleName = $validated['role'] ?? $validated['primary_role'] ?? null;

            if (!empty($roleName)) {
                $role = Role::where('name', $roleName)->first();
            } else {
                $role = Role::where('name', $defaultRoleName)->first();
            }
        }

        // The string we want in `users.role`
        $resolvedRoleName = $validated['primary_role']
            ?? ($role ? $role->name : $defaultRoleName);

        // ✅ ALWAYS generate password
        // Option A (strong): random 10 chars
        $plainPassword = strtoupper(Str::random(10));

        // Option B (if you prefer 5-digit only), uncomment below and comment option A:
        // $plainPassword = str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);

        $isTemporary = true;

        try {
            $user = DB::transaction(function () use ($validated, $role, $plainPassword, $resolvedRoleName) {
                $user = User::create([
                    'name'     => $validated['name'],
                    'email'    => $validated['email'],
                    'phone'    => $validated['phone'] ?? null,
                    'role'     => $resolvedRoleName,
                    'password' => Hash::make($plainPassword),
                ]);

                if ($role) {
                    $user->assignRole($role);
                }

                return $user;
            });
        } catch (\Throwable $e) {
            \Log::error('Register failed', ['err' => $e->getMessage()]);

            return $this->sendError(
                'Could not create user.',
                ['error' => $e->getMessage()],
                500
            );
        }

        // ---- Notify the user by email (default true) ----
        $shouldNotify = $request->boolean('notify', true);

        if ($shouldNotify) {
            try {
                $user->notifyNow(new RegistrationNotification(
                    user: $user,
                    plainPassword: $plainPassword,
                    isTemporary: $isTemporary
                ));
            } catch (\Throwable $e) {
                \Log::warning('Registration email failed', ['err' => $e->getMessage()]);
                // don't fail the request
            }
        }

        // Issue token
        $token = $user->createToken('MyApp')->plainTextToken;

        return $this->sendResponse([
            'token' => $token,
            'user'  => $user->loadMissing('roles'),
        ], 'User registered successfully. Password sent to email.');
    }

    /**
     * Login
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            return $this->sendError('Unauthorised.', ['error' => 'Unauthorised'], 401);
        }

        /** @var \App\Models\User $user */
        $user  = Auth::user();
        $token = $user->createToken('MyApp')->plainTextToken;

        return $this->sendResponse([
            'token'       => $token,
            'user'        => $user->loadMissing('roles'),
            'roles'       => method_exists($user, 'getRoleNames') ? $user->getRoleNames() : [],
            'permissions' => method_exists($user, 'getAllPermissions')
                ? $user->getAllPermissions()->pluck('name')
                : [],
        ], 'User login successfully.');
    }
}
