<?php

namespace Nicelizhi\Airwallex;

use Webkul\Core\Core as WebkulCore;

class Core extends WebkulCore
{
    /**
     * Retrieve information from payment configuration.
     *
     * @param  string  $field
     * @param  int|string|null  $channelId
     * @param  string|null  $locale
     * @return mixed
     */
    public function getConfigData($field, $channel = null, $locale = null)
    {
        if (
            $field === "sales.payment_methods.airwallex.title"
            && request()->id
            && (
                (
                    str_contains(request()->route()->getName(), 'invoice')
                    && ($invoice = app('\Webkul\Sales\Repositories\InvoiceRepository')->find(request()->id))
                    && ($order = $invoice->order)
                ) || (
                    str_contains(request()->route()->getName(), 'order')
                    && ($order = app('\Webkul\Sales\Repositories\OrderRepository')->find(request()->id))
                )
            )
            && ($additionalPaymentInfo = $order->payment->additional['payment'] ?? false)
            && $additionalPaymentInfo
        ) {
            return $additionalPaymentInfo['payment_method_title'];
        } else {
            return parent::getConfigData($field, $channel = null, $locale = null);
        }
    }
}
