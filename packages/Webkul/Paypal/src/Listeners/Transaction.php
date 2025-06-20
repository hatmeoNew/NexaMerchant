<?php

namespace Webkul\Paypal\Listeners;

use Illuminate\Support\Facades\Log;
use Webkul\Paypal\Payment\SmartButton;
use Webkul\Sales\Repositories\OrderTransactionRepository;
use Illuminate\Support\Facades\Artisan;
use Nicelizhi\Shopify\Console\Commands\Order\Post;
use Nicelizhi\Shopify\Console\Commands\Order\PostOdoo;

class Transaction
{
    /**
     * Create a new listener instance.
     *
     * @return void
     */
    public function __construct(
        protected SmartButton $smartButton,
        protected OrderTransactionRepository $orderTransactionRepository
    ) {
    }

    /**
     * Save the transaction data for online payment.
     *
     * @param  \Webkul\Sales\Models\Invoice  $invoice
     * @return void
     */
    public function saveTransaction($invoice)
    {
        $data = request()->all();

        if ($invoice->order->payment->method == 'paypal_smart_button') {
            if (isset($data['orderData']['orderID'])) {
                $transactionDetails = $this->smartButton->getOrder($data['orderData']['orderID']);

                $transactionDetails = json_decode(json_encode($transactionDetails), true);

                Log::info('Paypal Smart Transaction Details: ' . json_encode($transactionDetails));
                if(isset($transactionDetails['result']['purchase_units'][0]['payments']['captures'][0]['id'])) Log::info('Paypal Smart Transaction ID'. $transactionDetails['result']['purchase_units'][0]['payments']['captures'][0]['id']);


                // save transaction vault
                $paypal_vault = [];
                $paypal_vault['vault'] = null;
                if(isset($transactionDetails['result']['payment_source']['paypal']['attributes']['vault'])) {
                    session()->put('paypal_vault', $transactionDetails['result']['payment_source']['paypal']['attributes']['vault']);
                    $paypal_vault['vault'] = $transactionDetails['result']['payment_source']['paypal']['attributes']['vault'];
                }


                if ($transactionDetails['statusCode'] == 200) {
                    $insertData = [];
                    $insertData['purchase_units'] = $transactionDetails['result']['purchase_units'];
                    $insertData['payer'] = isset($transactionDetails['result']['payer']) ? $transactionDetails['result']['payer'] : null;
                    $insertData['paypal_vault'] = $paypal_vault;
                    $this->orderTransactionRepository->create([
                        'transaction_id' => $transactionDetails['result']['id'],
                        'captures_id'    => isset($transactionDetails['result']['purchase_units'][0]['payments']['captures'][0]['id']) ? $transactionDetails['result']['purchase_units'][0]['payments']['captures'][0]['id'] : null,
                        'status'         => $transactionDetails['result']['status'],
                        'type'           => $transactionDetails['result']['intent'],
                        'amount'         => $transactionDetails['result']['purchase_units'][0]['amount']['value'],
                        'payment_method' => $invoice->order->payment->method,
                        'order_id'       => $invoice->order->id,
                        'invoice_id'     => $invoice->id,
                        'data'           => json_encode(
                            $insertData
                        ),
                    ]);

                    // send order to shopify
                    $queue = config('app.name'). ':orders';
                    if (config('onebuy.is_sync_erp')) {
                        Artisan::queue((new PostOdoo())->getName(), ['--order_id'=> $invoice->order->id])->onConnection('rabbitmq')->onQueue(config('app.name') . ':odoo_order');
                    } else {
                        Artisan::queue((new Post())->getName(), ['--order_id'=> $invoice->order->id])->onConnection('rabbitmq')->onQueue($queue);
                    }
                }
            }
        } elseif ($invoice->order->payment->method == 'paypal_standard') {
            $this->orderTransactionRepository->create([
                'transaction_id' => $data['txn_id'],
                'status'         => $data['payment_status'],
                'type'           => $data['payment_type'],
                'payment_method' => $invoice->order->payment->method,
                'order_id'       => $invoice->order->id,
                'invoice_id'     => $invoice->id,
                'data'           => json_encode($data),
            ]);
        }
    }
}
