<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use App\Notifications\CustomerCredentialsNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

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

    public function me(Request $request)
    {
        $user = $request->user();

        $customer = Customer::with('user')
            ->where('user_id', $user->id)
            ->orWhereHas('user', function ($q) use ($user) {
                $q->where('id', $user->id);

                if (!empty($user->email)) {
                    $q->orWhere('email', $user->email);
                }
            })
            ->first();

        if (!$customer) {
            return response()->json([
                'message' => 'Customer profile not found.'
            ], 404);
        }

        return response()->json($customer);
    }

    private function tempCode(): string
    {
        return 'CUS-TMP-' . Str::uuid()->toString();
    }

    private function generatePassword(): string
    {
        return strtoupper(Str::random(10));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => ['required_without:user_id', 'string', 'max:100'],
            'email'       => ['required_without:user_id', 'email', 'max:150', 'unique:users,email'],
            'phone'       => ['nullable', 'string', 'max:30'],
            'password'    => ['nullable', 'string', 'min:5'],
            'user_id'     => ['nullable', 'exists:users,id'],
            'document_no' => ['nullable', 'string', 'max:100'],
            'preferences' => ['nullable', 'array'],
            'status'      => ['nullable', 'string', Rule::in(['active', 'inactive'])],
            'code'        => ['sometimes', 'string', 'max:50', 'unique:customers,code'],
            'notify'      => ['nullable', 'boolean'],
        ]);

        $plainPassword = null;
        $createdNewUser = false;

        $result = DB::transaction(function () use ($data, &$plainPassword, &$createdNewUser) {
            if (!empty($data['user_id'])) {
                $user = User::findOrFail($data['user_id']);
            } else {
                $plainPassword = $data['password'] ?? $this->generatePassword();
                $createdNewUser = true;

                $user = User::create([
                    'name'     => $data['name'],
                    'email'    => $data['email'],
                    'phone'    => $data['phone'] ?? null,
                    'role'     => 'customer',
                    'password' => Hash::make($plainPassword),
                ]);

                if (method_exists($user, 'assignRole')) {
                    try {
                        $user->assignRole('customer');
                    } catch (\Throwable $e) {
                    }
                }
            }

            $customer = Customer::create([
                'user_id'     => $user->id,
                'document_no' => $data['document_no'] ?? null,
                'preferences' => $data['preferences'] ?? null,
                'status'      => $data['status'] ?? 'active',
                'code'        => $data['code'] ?? $this->tempCode(),
            ]);

            if (empty($data['code'])) {
                $generated = 'CUS-' . now()->format('Y') . '-' . str_pad((string) $customer->id, 6, '0', STR_PAD_LEFT);
                $customer->update(['code' => $generated]);
                $customer->refresh();
            }

            return [$user, $customer];
        });

        [$user, $customer] = $result;

        $shouldNotify = $request->boolean('notify', true);
        $mailSent = false;

        if ($createdNewUser && $shouldNotify && !empty($user->email)) {
            try {
                $user->notifyNow(new CustomerCredentialsNotification(
                    user: $user,
                    customer: $customer,
                    plainPassword: $plainPassword,
                    isTemporary: empty($data['password'])
                ));
                $mailSent = true;
            } catch (\Throwable $e) {
                \Log::warning('Customer credentials email failed', [
                    'user_id' => $user->id,
                    'email'   => $user->email,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'user'              => $user->load('roles'),
            'customer'          => $customer,
            'notification_sent' => $mailSent,
            'message'           => $mailSent
                ? 'Customer created successfully. Credentials email sent.'
                : 'Customer created successfully.',
        ], 201);
    }

    public function update(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'document_no' => ['nullable', 'string', 'max:100'],
            'preferences' => ['nullable', 'array'],
            'status'      => ['nullable', Rule::in(['active', 'inactive'])],
            'code'        => ['sometimes', 'string', 'max:50', 'unique:customers,code,' . $customer->id],
        ]);

        $customer->update($data);

        return response()->json($customer->fresh('user'));
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();

        return response()->json(['deleted' => true]);
    }
}