<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CheckoutController extends Controller
{

    public function process(Request $request)
    {
        Log::info('Midtrans Notification:', ['payload process' => $request->all()]);
        $data = $request->all();
        // Generate order ID yang unique
        $data['order_id'] = 'TRX-' . time() . '-' . auth()->id();

        $transaction = Transaction::create([
            'user_id' => Auth::user()->id,
            'product_id' => $data['product_id'],
            'price' => $data['price'],
            'status' => 'pending',
            'order_id' => $data['order_id'],
        ]);

        // dd($transaction);
        // Set Midtrans configuration
        \Midtrans\Config::$serverKey = config('midtrans.server_key');
        \Midtrans\Config::$isProduction = false;
        \Midtrans\Config::$isSanitized = true;
        \Midtrans\Config::$is3ds = true;

        $params = array(
            'transaction_details' => array(
                'order_id' => $data['order_id'],
                // 'order_id' => rand(),
                'gross_amount' => $data['price'],
            ),
            'customer_details' => array(
                'first_name' => auth()->user()->name,
                'email' => auth()->user()->email,
            )
        );

        $snapToken = \Midtrans\Snap::getSnapToken($params);

        $transaction->snap_token = $snapToken;
        $transaction->save();

        return redirect()->route('checkout', $transaction->id);
    }

    public function checkout(Transaction $transaction)
    {
        // dd($transaction);
        $products = config('products');
        $product = collect($products)->firstWhere('id', $transaction->product_id);

        return view('checkout',  compact('transaction', 'product'));
    }
    public function success(Transaction $transaction)
    {
        // Cek apakah transaksi milik user yang login
        if ($transaction->user_id !== Auth::id()) {
            abort(403);
        }
        // dd($transaction);
        if ($transaction->status !== 'success') {
            // dd('Status pembayaran masih dalam proses atau gagal');
            return redirect()->route('transactions')
                ->with('error', 'Status pembayaran masih dalam proses atau gagal');
        }

        // dd('Status pembayaran sukses');
        return view('success', compact('transaction'));
    }

    // Undefined array key "order_id""

    public function notification(Request $request)
    {
        Log::info('Midtrans Notification:', ['payload' => $request->all()]);

        $payload = $request->all();
        dd($payload);
        $validSignatureKey = hash('sha512', $payload['result']['order_id'] . $payload['result']['status_code'] . $payload['result']['gross_amount'] . config('midtrans.server_key'));
        Log::info('Midtrans Notification:', ['validSignatureKey' => $validSignatureKey]);

        $transaction = Transaction::where('order_id', $payload['result']['order_id'])->first();
        // dd($transaction);
        if (!$transaction) {
            return response()->json(['status' => 'error', 'message' => 'Order ID not found'], 404);
        }

        if ($payload['result']['status_code'] == 200) {
            $transaction->status = 'success';
        } else {
            $transaction->status = 'failed';
        }

        $transaction->save();

        return response()->json(['status' => 'success', 'message' => 'Payment status updated']);
    }

}
