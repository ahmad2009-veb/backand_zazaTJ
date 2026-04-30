<?php

namespace App\Http\Controllers\Payments;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Throwable;

class AlifPayController extends Controller
{
    /**
     * Response example:
     *
     * {
     *   'orderId': '100024',
     *   'transactionId': '100424674',
     *   'status': 'ok',
     *   'token': 'd7751b7e1ebcf35072f3bbad09874bc24464395816126e33748e8c5d873b0cdb',
     *   'amount': 1,
     *   'phone': '+992900339955',
     * }
     */
    public function callback(Request $request, Order $order, string $token)
    {
        if ($request->status != 'ok') {
            logger()->warning('Payment | Alif | Bad status', $request->all());

            return;
        }

        if ($order->transaction_reference != $token) {
            logger()->error('Payment | Alif | Transaction reference is not related to the order', [
                'order'         => $order->id,
                'transaction'   => $token,
                'transactionId' => $request->transactionId,
            ]);

            return;
        }

        $order->order_status          = 'confirmed';
        $order->payment_method        = 'alifpay';
        $order->transaction_reference = $request->transactionId;
        $order->payment_status        = 'paid';
        $order->confirmed             = now();
        $order->save();

        try {
            Helpers::send_order_notification($order);
        } catch (Throwable $e) {
        }

        return response()->json(['message' => 'ok']);
    }
}
