<?php

namespace App\Http\Controllers\Api\V1\Payments;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentIntentController extends Controller
{
    public function store(Request $request, Order $order): JsonResponse
    {
        abort_unless((int) $order->user_id === (int) $request->user()->id, 404);
        abort_unless($order->payment_status === 'pending', 409, 'Order is not awaiting payment.');

        return ApiResponse::error(
            'PAYMENT_PROVIDER_NOT_CONFIGURED',
            'Payment intent creation is disabled until a selected payment provider adapter, credentials and marketplace settlement arrangement are configured and tested.',
            503
        );
    }
}
