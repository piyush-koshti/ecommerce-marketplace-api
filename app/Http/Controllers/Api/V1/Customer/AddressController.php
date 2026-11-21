<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $request->user()->addresses()->orderByDesc('is_default')->latest()->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatedData($request);

        $address = DB::transaction(function () use ($request, $validated): Address {
            if ($validated['is_default'] ?? false) {
                $request->user()->addresses()->update(['is_default' => false]);
            }

            return $request->user()->addresses()->create($validated);
        });

        return ApiResponse::success($address, 201);
    }

    public function show(Request $request, int $address): JsonResponse
    {
        return ApiResponse::success($this->ownedAddress($request, $address));
    }

    public function update(Request $request, int $address): JsonResponse
    {
        $record = $this->ownedAddress($request, $address);
        $validated = $this->validatedData($request);

        DB::transaction(function () use ($request, $record, $validated): void {
            if ($validated['is_default'] ?? false) {
                $request->user()->addresses()
                    ->where('id', '!=', $record->id)
                    ->update(['is_default' => false]);
            }

            $record->update($validated);
        });

        return ApiResponse::success($record->fresh());
    }

    public function destroy(Request $request, int $address): JsonResponse
    {
        $record = $this->ownedAddress($request, $address);
        $record->delete();

        return ApiResponse::success(['message' => 'Address deleted successfully.']);
    }

    private function ownedAddress(Request $request, int $id): Address
    {
        return $request->user()->addresses()->findOrFail($id);
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'label' => ['required', 'string', Rule::in(['home', 'work', 'other'])],
            'recipient_name' => ['required', 'string', 'max:150'],
            'mobile' => ['required', 'string', 'regex:/^[6-9][0-9]{9}$/'],
            'address_line_1' => ['required', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'landmark' => ['nullable', 'string', 'max:150'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:100'],
            'country_code' => ['required', 'string', 'size:2'],
            'postal_code' => ['required', 'string', 'max:12'],
            'is_default' => ['sometimes', 'boolean'],
        ]);
    }
}
