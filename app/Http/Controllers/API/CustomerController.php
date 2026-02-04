<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index()
    {
        return response()->json(
            Customer::with('user')->latest()->paginate(20)
        );
    }

    public function show(Customer $customer)
    {
        return response()->json($customer->load('user'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            // Flow A: create new user
            'name'       => ['required_without:user_id', 'string', 'max:100'],
            'email'      => ['required_without:user_id', 'email', 'max:150', 'unique:users,email'],
            'phone'      => ['nullable', 'string', 'max:30'],
            'password'   => ['nullable', 'string', 'min:5'],

            // Flow B: link existing user
            'user_id'    => ['nullable', 'exists:users,id'],

            // Customer fields
            'document_no'  => ['nullable', 'string', 'max:100'],
            'preferences'  => ['nullable', 'array'],
            'status'       => ['nullable', 'string', Rule::in(['active','inactive'])],
            // We generate code, but allow override if you *really* want to pass it:
            'code'         => ['sometimes', 'string', 'max:50', 'unique:customers,code'],
        ]);

        $result = DB::transaction(function () use ($data) {
            // Prepare or fetch the user
            if (!empty($data['user_id'])) {
                $user = User::find($data['user_id']);
            } else {
                $user = User::create([
                    'name'     => $data['name'],
                    'email'    => $data['email'],
                    'phone'    => $data['phone'] ?? null,
                    'password' => Hash::make($data['password'] ?? 'customer123'),
                ]);
                if (method_exists($user, 'assignRole')) {
                    // Will throw if role 'customer' doesn't exist
                    try { $user->assignRole('customer'); } catch (\Throwable $e) { /* ignore if role missing */ }
                }
            }

            // Create customer first to get its ID
            $customer = Customer::create([
                'user_id'     => $user->id,
                'document_no' => $data['document_no'] ?? null,
                'preferences' => $data['preferences'] ?? null,
                'status'      => $data['status'] ?? 'active',
                // temporary; we'll update with final code after we have the ID
                'code'        => $data['code'] ?? '',
            ]);

            // If no code provided, generate from ID (guaranteed unique)
            if (empty($data['code'])) {
                $generated = 'CUS-'.now()->format('Y').'-'.str_pad((string)$customer->id, 6, '0', STR_PAD_LEFT);
                $customer->update(['code' => $generated]);
            }

            return [$user, $customer];
        });

        /** @var \App\Models\User $user */
        /** @var \App\Models\Customer $customer */
        [$user, $customer] = $result;

        return response()->json([
            'user'     => $user->load('roles'),
            'customer' => $customer,
        ], 201);
    }

    public function update(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'document_no' => ['nullable', 'string', 'max:100'],
            'preferences' => ['nullable', 'array'],
            'status'      => ['nullable', Rule::in(['active','inactive'])],
            'code'        => ['sometimes', 'string', 'max:50', 'unique:customers,code,'.$customer->id],
        ]);

        $customer->update($data);

        return response()->json($customer);
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();
        return response()->json(['deleted' => true]);
    }
}
