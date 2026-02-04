<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class DriverController extends Controller
{
    // GET /api/drivers
    public function index(Request $request)
    {
        $drivers = Driver::query()
            ->with(['user', 'vehicle'])   // ✅ vehicle works after adding relationship + vehicle_id column
            ->orderByDesc('id')
            ->paginate(10);

        // ✅ No need to add profile_image_url manually (Driver model accessor already appends it)
        return response()->json($drivers);
    }

    // GET /api/drivers/{driver}
    public function show(Driver $driver)
    {
        $driver->load(['user', 'vehicle']); // ✅ removed customer (unless you add customer relationship)

        return response()->json($driver);
    }

    // POST /api/drivers
    public function store(Request $request)
    {
        $validated = $request->validate([
            // user fields
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['nullable', 'string', 'min:6'],

            // driver fields
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

            // optional assignment
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'], // ✅ if you want assign on create
        ]);

        return DB::transaction(function () use ($request, $validated) {

            // create user
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'password' => Hash::make($validated['password'] ?? 'password123'),
                'role' => 'driver',
            ]);

            // upload image
            $path = null;
            if ($request->hasFile('profile_image')) {
                $path = $request->file('profile_image')->store('drivers/profile', 'public');
            }

            // create driver
            $driver = Driver::create([
                'user_id' => $user->id,
                'vehicle_id' => $validated['vehicle_id'] ?? null, // ✅ optional

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

            return response()->json([
                'success' => true,
                'driver' => $driver
            ], 201);
        });
    }

    // PUT /api/drivers/{driver}
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

            // optional assignment update
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'], // ✅ allow change vehicle
        ]);

        return DB::transaction(function () use ($request, $driver, $validated) {

            // update user
            $u = $driver->user;
            if (isset($validated['name'])) $u->name = $validated['name'];
            if (isset($validated['email'])) $u->email = $validated['email'];
            if (isset($validated['phone'])) $u->phone = $validated['phone'];
            if (!empty($validated['password'])) $u->password = Hash::make($validated['password']);
            $u->save();

            // update image
            if ($request->hasFile('profile_image')) {
                if ($driver->profile_image) {
                    Storage::disk('public')->delete($driver->profile_image);
                }
                $driver->profile_image = $request->file('profile_image')->store('drivers/profile', 'public');
            }

            // update driver fields
            foreach ([
                'gender','marital_status','license_no','license_expiry','license_category',
                'experience_years','status','current_lat','current_lng','current_address',
                'vehicle_id' // ✅ allow updating vehicle_id
            ] as $f) {
                if (array_key_exists($f, $validated)) $driver->$f = $validated[$f];
            }

            if ($request->has('is_verified')) $driver->is_verified = $request->boolean('is_verified');
            if ($request->has('is_available')) $driver->is_available = $request->boolean('is_available');

            $driver->save();
            $driver->load(['user', 'vehicle']);

            return response()->json($driver);
        });
    }

    // DELETE /api/drivers/{driver}
    public function destroy(Driver $driver)
    {
        return DB::transaction(function () use ($driver) {
            if ($driver->profile_image) {
                Storage::disk('public')->delete($driver->profile_image);
            }

            $user = $driver->user;
            $driver->delete();
            if ($user) $user->delete();

            return response()->json(['success' => true]);
        });
    }
}