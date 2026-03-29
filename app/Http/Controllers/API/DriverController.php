<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\User;
use App\Notifications\RegistrationNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DriverController extends Controller
{
    public function index(Request $request)
    {
        $drivers = Driver::query()
            ->with(['user', 'vehicle'])
            ->orderByDesc('id')
            ->paginate(10);

        return response()->json($drivers);
    }

    public function show(Driver $driver)
    {
        $driver->load(['user', 'vehicle']);
        return response()->json($driver);
    }

    public function me(Request $request)
    {
        $user = $request->user();

        $driver = Driver::with(['user', 'vehicle'])
            ->where('user_id', $user->id)
            ->orWhereHas('user', function ($q) use ($user) {
                $q->where('id', $user->id);

                if (!empty($user->email)) {
                    $q->orWhere('email', $user->email);
                }
            })
            ->first();

        if (!$driver) {
            return response()->json([
                'message' => 'Driver profile not found.'
            ], 404);
        }

        return response()->json($driver);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'notify' => ['nullable', 'boolean'],

            'profile_image' => ['nullable', 'image', 'max:4096'],
            'gender' => ['nullable', 'string', 'max:20'],
            'marital_status' => ['nullable', 'string', 'max:20'],

            'license_no' => ['nullable', 'string', 'max:100'],
            'license_expiry' => ['nullable', 'date'],
            'license_category' => ['nullable', 'string', 'max:50'],
            'experience_years' => ['nullable', 'numeric', 'min:0'],

            'status' => ['nullable', 'string', 'max:20'],
            'is_verified' => ['nullable'],
            'is_available' => ['nullable'],

            'current_lat' => ['nullable'],
            'current_lng' => ['nullable'],
            'current_address' => ['nullable', 'string', 'max:255'],

            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
        ]);

        $result = DB::transaction(function () use ($request, $validated) {
            $plainPassword = strtoupper(Str::random(10));

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'password' => Hash::make($plainPassword),
                'role' => 'driver',
            ]);

            if (method_exists($user, 'assignRole')) {
                try {
                    $user->assignRole('driver');
                } catch (\Throwable $e) {
                }
            }

            $path = null;
            if ($request->hasFile('profile_image')) {
                $path = $request->file('profile_image')->store('drivers/profile', 'public');
            }

            $driver = Driver::create([
                'user_id' => $user->id,
                'vehicle_id' => $validated['vehicle_id'] ?? null,

                'profile_image' => $path,

                'gender' => $validated['gender'] ?? null,
                'marital_status' => $validated['marital_status'] ?? null,

                'license_no' => $validated['license_no'] ?? null,
                'license_expiry' => $validated['license_expiry'] ?? null,
                'license_category' => $validated['license_category'] ?? null,
                'experience_years' => $validated['experience_years'] ?? 0,

                'status' => $validated['status'] ?? 'active',
                'is_verified' => $request->boolean('is_verified'),
                'is_available' => $request->boolean('is_available', true),

                'current_lat' => $validated['current_lat'] ?? null,
                'current_lng' => $validated['current_lng'] ?? null,
                'current_address' => $validated['current_address'] ?? null,
            ]);

            $driver->load(['user', 'vehicle']);

            return [
                'driver' => $driver,
                'user' => $user,
                'plain_password' => $plainPassword,
            ];
        });

        $credentialsSent = false;
        $shouldNotify = $request->boolean('notify', true);

        if ($shouldNotify) {
            try {
                $result['user']->notifyNow(new RegistrationNotification(
                    user: $result['user'],
                    plainPassword: $result['plain_password'],
                    isTemporary: true
                ));

                $credentialsSent = true;
            } catch (\Throwable $e) {
                Log::warning('Driver registration email failed', [
                    'user_id' => $result['user']->id,
                    'email' => $result['user']->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'driver' => $result['driver'],
            'credentials_sent' => $credentialsSent,
            'message' => $credentialsSent
                ? 'Driver created and credentials sent to email.'
                : 'Driver created, but email could not be sent.',
        ], 201);
    }

    public function update(Request $request, Driver $driver)
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email,' . $driver->user_id],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['nullable', 'string', 'min:6'],

            'profile_image' => ['nullable', 'image', 'max:4096'],
            'gender' => ['nullable', 'string', 'max:20'],
            'marital_status' => ['nullable', 'string', 'max:20'],

            'license_no' => ['nullable', 'string', 'max:100'],
            'license_expiry' => ['nullable', 'date'],
            'license_category' => ['nullable', 'string', 'max:50'],
            'experience_years' => ['nullable', 'numeric', 'min:0'],

            'status' => ['nullable', 'string', 'max:20'],
            'is_verified' => ['nullable'],
            'is_available' => ['nullable'],

            'current_lat' => ['nullable'],
            'current_lng' => ['nullable'],
            'current_address' => ['nullable', 'string', 'max:255'],

            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
        ]);

        return DB::transaction(function () use ($request, $driver, $validated) {
            $u = $driver->user;

            if (isset($validated['name'])) $u->name = $validated['name'];
            if (isset($validated['email'])) $u->email = $validated['email'];
            if (isset($validated['phone'])) $u->phone = $validated['phone'];
            if (!empty($validated['password'])) $u->password = Hash::make($validated['password']);
            $u->save();

            if ($request->hasFile('profile_image')) {
                if ($driver->profile_image) {
                    Storage::disk('public')->delete($driver->profile_image);
                }
                $driver->profile_image = $request->file('profile_image')->store('drivers/profile', 'public');
            }

            foreach ([
                'gender',
                'marital_status',
                'license_no',
                'license_expiry',
                'license_category',
                'experience_years',
                'status',
                'current_lat',
                'current_lng',
                'current_address',
                'vehicle_id',
            ] as $f) {
                if (array_key_exists($f, $validated)) {
                    $driver->$f = $validated[$f];
                }
            }

            if ($request->has('is_verified')) $driver->is_verified = $request->boolean('is_verified');
            if ($request->has('is_available')) $driver->is_available = $request->boolean('is_available');

            $driver->save();
            $driver->load(['user', 'vehicle']);

            return response()->json($driver);
        });
    }

    public function destroy(Driver $driver)
    {
        return DB::transaction(function () use ($driver) {
            if ($driver->profile_image) {
                Storage::disk('public')->delete($driver->profile_image);
            }

            $user = $driver->user;
            $driver->delete();

            if ($user) {
                $user->delete();
            }

            return response()->json(['success' => true]);
        });
    }
}