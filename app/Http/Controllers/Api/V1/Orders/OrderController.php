<?php

namespace App\Http\Controllers\Api\V1\Orders;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SellerOrder;
use App\Models\StockReservation;
use App\Services\Inventory\StockReservationService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success(Order::where('user_id', $request->user()->id)->with('sellerOrders.items')->latest()->paginate(20));
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        abort_unless((int) $order->user_id === (int) $request->user()->id, 404);

        return ApiResponse::success($order->load(['sellerOrders.items', 'histories']));
    }

    public function sellerIndex(Request $request, int $sellerAccount): JsonResponse
    {
        $this->assertSellerMember($request, $sellerAccount);

        return ApiResponse::success(SellerOrder::where('seller_account_id', $sellerAccount)->with(['items', 'order'])->latest()->paginate(20));
    }

    public function sellerStatus(Request $request, int $sellerAccount, SellerOrder $sellerOrder, StockReservationService $reservations): JsonResponse
    {
        $this->assertSellerMember($request, $sellerAccount, true);
        abort_unless((int) $sellerOrder->seller_account_id === $sellerAccount, 404);

        $data = $request->validate([
            'status' => ['required', Rule::in(['accepted', 'processing', 'packed', 'shipped', 'delivered', 'cancelled'])],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $updated = DB::transaction(function () use ($sellerOrder, $data, $request, $reservations) {
            // Lock the parent first so updates to different seller groups of the same order serialize.
            $parentOrder = Order::query()->lockForUpdate()->findOrFail($sellerOrder->order_id);
            $lockedSellerOrder = SellerOrder::query()->lockForUpdate()->findOrFail($sellerOrder->id);

            $allowed = [
                'confirmed' => ['accepted', 'cancelled'],
                'accepted' => ['processing', 'cancelled'],
                'processing' => ['packed', 'cancelled'],
                'packed' => ['shipped'],
                'shipped' => ['delivered'],
                'delivered' => [],
                'cancelled' => [],
            ];

            abort_unless(in_array($data['status'], $allowed[$lockedSellerOrder->status] ?? [], true), 409, 'Invalid seller order status transition.');

            if ($data['status'] === 'shipped') {
                abort_unless($lockedSellerOrder->shipments()->exists(), 409, 'Create a shipment before marking this seller order shipped.');
            }

            $from = $lockedSellerOrder->status;
            $lockedSellerOrder->update(['status' => $data['status']]);

            if ($data['status'] === 'shipped') {
                $lockedSellerOrder->shipments()->update(['status' => 'in_transit', 'shipped_at' => now()]);
            }

            if ($data['status'] === 'delivered') {
                $lockedSellerOrder->shipments()->update(['status' => 'delivered', 'delivered_at' => now()]);
            }

            if ($data['status'] === 'cancelled') {
                StockReservation::query()
                    ->where('reference_type', 'seller_order')
                    ->where('reference_id', (string) $lockedSellerOrder->id)
                    ->whereIn('status', ['reserved', 'committed'])
                    ->lockForUpdate()
                    ->get()
                    ->each(function (StockReservation $reservation) use ($reservations, $request): void {
                        if ($reservation->status === 'reserved') {
                            $reservations->release($reservation);
                        } elseif ($reservation->status === 'committed') {
                            $reservations->reverseCommitted($reservation, $request->user()->id);
                        }
                    });
            }

            $parentOrder->histories()->create([
                'seller_order_id' => $lockedSellerOrder->id,
                'actor_id' => $request->user()->id,
                'from_status' => $from,
                'to_status' => $data['status'],
                'source' => 'seller',
                'reason' => $data['reason'] ?? null,
            ]);

            $sellerOrders = SellerOrder::query()->where('order_id', $parentOrder->id)->get();
            $nextParentStatus = null;

            if ($sellerOrders->every(fn (SellerOrder $group): bool => $group->status === 'delivered')) {
                $nextParentStatus = 'delivered';
            } elseif ($sellerOrders->every(fn (SellerOrder $group): bool => $group->status === 'cancelled')) {
                $nextParentStatus = 'cancelled';
            }

            // Mixed seller outcomes are deliberately not mapped to a new parent status
            // until the marketplace defines an approved partial-fulfilment status.
            if ($nextParentStatus !== null && $parentOrder->status !== $nextParentStatus) {
                $previous = $parentOrder->status;
                $parentOrder->update(['status' => $nextParentStatus]);
                $parentOrder->histories()->create([
                    'actor_id' => $request->user()->id,
                    'from_status' => $previous,
                    'to_status' => $nextParentStatus,
                    'source' => 'system',
                    'reason' => $nextParentStatus === 'delivered'
                        ? 'All seller fulfilment groups delivered.'
                        : 'All seller fulfilment groups cancelled.',
                ]);
            }

            return $lockedSellerOrder->fresh(['items', 'order']);
        }, 3);

        return ApiResponse::success($updated);
    }

    public function createShipment(Request $request, int $sellerAccount, SellerOrder $sellerOrder): JsonResponse
    {
        $this->assertSellerMember($request, $sellerAccount, true);
        abort_unless((int) $sellerOrder->seller_account_id === $sellerAccount, 404);

        $data = $request->validate([
            'carrier_name' => ['required', 'string', 'max:100'],
            'tracking_number' => ['nullable', 'string', 'max:150'],
        ]);

        $shipment = DB::transaction(function () use ($sellerOrder, $data) {
            $lockedSellerOrder = SellerOrder::query()->lockForUpdate()->findOrFail($sellerOrder->id);
            abort_unless($lockedSellerOrder->status === 'packed', 409, 'Only packed seller orders can be shipped.');
            abort_if($lockedSellerOrder->shipments()->exists(), 409, 'A shipment already exists for this seller order.');

            $shipment = $lockedSellerOrder->shipments()->create([
                'carrier_name' => $data['carrier_name'],
                'tracking_number' => $data['tracking_number'] ?? null,
                'status' => 'created',
            ]);

            foreach ($lockedSellerOrder->items as $item) {
                $shipment->items()->create([
                    'order_item_id' => $item->id,
                    'quantity' => $item->quantity,
                ]);
            }

            return $shipment;
        }, 3);

        return ApiResponse::success($shipment->load('items'), 201);
    }

    private function assertSellerMember(Request $request, int $sellerId, bool $manage = false): void
    {
        abort_unless($request->user()->sellerAccounts()->where('seller_accounts.id', $sellerId)->wherePivot('status', 'active')->exists(), 404);
        abort_unless($manage ? $request->user()->hasPermissionTo('orders.manage') : ($request->user()->hasPermissionTo('orders.view') || $request->user()->hasPermissionTo('orders.manage')), 403);
    }
}
