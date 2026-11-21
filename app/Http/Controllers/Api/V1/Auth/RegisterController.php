<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RegisterController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'mobile' => ['required', 'string', 'regex:/^[6-9][0-9]{9}$/', Rule::unique('users', 'mobile')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $result = DB::transaction(function () use ($validated): array {
            $user = User::create([
                'name' => $validated['name'],
                'email' => strtolower(trim($validated['email'])),
                'mobile' => $validated['mobile'],
                'password' => $validated['password'],
            ]);

            $token = $user->createToken('customer-api');

            return [
                'user' => $user,
                'access_token' => $token->plainTextToken,
            ];
        });

        return ApiResponse::success([
            'user' => [
                'id' => $result['user']->id,
                'name' => $result['user']->name,
                'email' => $result['user']->email,
                'mobile' => $result['user']->mobile,
            ],
            'access_token' => $result['access_token'],
            'token_type' => 'Bearer',
        ], 201);
    }
}
