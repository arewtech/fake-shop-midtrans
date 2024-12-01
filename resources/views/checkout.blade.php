@extends('layouts.app')

@section('title', 'Checkout')

@section('content')
    <div class="d-flex justify-content-center">
        <div class="card">
            <div class="card-body text-center d-flex flex-column justify-content-center align-items-center">
                Anda akan melakukan pembelian produk <strong>{{ $product['name'] }}</strong> dengan harga
                <strong>Rp{{ number_format($product['price'], 0, ',', '.') }}</strong>
                <button type="button" class="btn btn-primary mt-3" id="pay-button">
                    Bayar Sekarang
                </button>
            </div>
        </div>
    </div>
    {{-- {{ dd($transaction->snap_token) }} --}}
@endsection

@section('scripts')
    <script src="https://app.sandbox.midtrans.com/snap/snap.js" data-client-key="{{ env('MIDTRANS_CLIENT_KEY') }}"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script type="text/javascript">
        $(document).ready(function() {
            const snapToken = @json($transaction->snap_token);
            console.log('snap', snapToken);
            // For example trigger on button clicked, or any time you need
            var payButton = document.getElementById('pay-button');
            payButton.addEventListener('click', function() {
                snap.pay('{{ $transaction->snap_token }}', {
                    onSuccess: function(result) {
                        console.log('success', result);
                        // alert("Pembayaran berhasil!", result);
                        $.ajax({
                            url: '{{ route('payment.notification') }}',
                            method: 'POST',
                            data: {
                                _token: '{{ csrf_token() }}',
                                result: result
                            },
                            success: function(response) {
                                alert("Pembayaran berhasil!", response);
                                console.log('Notification sent', response);
                                // window.location.href =
                                //     '{{ route('checkout-success', $transaction->id) }}';
                            },
                            error: function(xhr, status, error) {
                                alert("notification error", error);
                                console.log('Notification error', error);
                                // window.location.href =
                                //     '{{ route('transactions') }}';
                            }
                        });
                    },
                    onPending: function(result) {
                        console.log('pending', result);
                        alert("Pembayaran dalam proses! Silahkan selesaikan pembayaran Anda",
                            result);
                        // window.location.href = '{{ route('transactions') }}';
                    },
                    onError: function(result) {
                        console.log('error', result);
                        alert("Pembayaran gagal!", result);
                        // window.location.href = '{{ route('transactions') }}';
                    },
                    onClose: function() {
                        console.log('customer closed the popup without finishing the payment');
                        // window.location.href = '{{ route('transactions') }}';
                    }
                });
            });
        });
    </script>
@endsection
