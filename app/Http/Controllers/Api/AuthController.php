<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\CustomerProfile;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use ApiResponse;

    /**
     * Public self-registration. Only customer / agent roles may be requested here;
     * staff accounts are provisioned by admins.
     */
    public function register(Request $request)
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30', 'unique:users,phone'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'account_type' => ['required', 'in:customer,agent'],
        ]);

        $user = User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'status' => 'active',
        ]);

        $user->assignRole($data['account_type']);

        if ($data['account_type'] === 'customer') {
            CustomerProfile::create(['user_id' => $user->id]);
        }

        AuditLogger::record('user.registered', $user);

        $token = $user->createToken('spa')->plainTextToken;

        return $this->created([
            'token' => $token,
            'user' => new UserResource($user->load('agent')),
        ], 'Registration successful');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        if ($user->status === 'suspended') {
            return $this->fail('Your account has been suspended.', null, 403);
        }

        $user->forceFill(['last_login_at' => now()])->save();
        AuditLogger::record('user.login', $user);

        $token = $user->createToken($data['device_name'] ?? 'spa')->plainTextToken;

        return $this->ok([
            'token' => $token,
            'user' => new UserResource($user->load('agent')),
        ], 'Login successful');
    }

    public function exchangeSsoTicket(Request $request)
    {
        $data = $request->validate([
            'ticket' => ['required', 'string', 'size:64', 'regex:/^[A-Za-z0-9]+$/'],
        ]);
        $ticketHash = hash('sha256', $data['ticket']);

        $user = DB::transaction(function () use ($ticketHash) {
            $ticket = DB::table('portal_login_tickets')
                ->where('ticket_hash', $ticketHash)
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if (! $ticket) {
                return null;
            }

            DB::table('portal_login_tickets')->where('ticket_hash', $ticketHash)->delete();
            $user = User::find($ticket->user_id);

            return $user && $user->status === 'active' && $user->hasAnyRole(['customer', 'agent'])
                ? $user
                : null;
        });

        if (! $user) {
            return $this->fail('This dashboard login link is invalid or has expired. Return to the storefront and try again.', null, 401);
        }

        $token = $user->createToken('storefront-dashboard')->plainTextToken;
        AuditLogger::record('user.dashboard_sso', $user);

        return $this->ok([
            'token' => $token,
            'user' => new UserResource($user->load('agent')),
        ], 'Dashboard login successful');
    }

    public function me(Request $request)
    {
        return $this->ok(new UserResource($request->user()->load('agent')));
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return $this->ok(null, 'Logged out');
    }
}
