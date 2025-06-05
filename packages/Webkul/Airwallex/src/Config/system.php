<?php

return [
    [
        'key'    => 'sales.payment_methods.airwallex',
        'name'   => 'airwallex::app.system.title',
        'info'   => 'airwallex::app.system.info',
        'sort'   => 5,
        'fields' => [
            [
                'name'          => 'clientEmail',
                'title'         => 'airwallex::app.system.clientEmail',
                'type'          => 'text',
                'channel_based' => true,
                'locale_based'  => false,
                'value_key'     => 'sales.payment_methods.airwallex.clientEmail',
            ],
            [
                'name'          => 'clientPassword',
                'title'         => 'airwallex::app.system.clientPassword',
                'type'          => 'text',
                'info'          => 'airwallex::app.system.clientPassword',
                'channel_based' => true,
                'locale_based'  => false,
                'value_key'     => 'sales.payment_methods.airwallex.clientPassword',
            ],
            [
                'name'          => 'accountId',
                'title'         => 'airwallex::app.system.accountId',
                'type'          => 'text',
                'info'          => 'airwallex::app.system.accountId',
                'channel_based' => true,
                'locale_based'  => false,
                'value_key'     => 'sales.payment_methods.airwallex.accountId',
            ],
            [
                'name'          => 'clientId',
                'title'         => 'airwallex::app.system.clientId',
                'type'          => 'text',
                'validation'    => 'required_if:active,1',
                'info'          => 'airwallex::app.system.clientId',
                'channel_based' => true,
                'locale_based'  => false,
                'value_key'     => 'sales.payment_methods.airwallex.clientId',
            ],
            [
                'name'          => 'apiKey',
                'title'         => 'airwallex::app.system.apiKey',
                'type'          => 'text',
                'validation'    => 'required_if:active,1',
                'info'          => 'airwallex::app.system.apiKey',
                'channel_based' => true,
                'locale_based'  => false,
                'value_key'     => 'sales.payment_methods.airwallex.apiKey',
            ],
            [
                'name'          => 'paDC',
                'title'         => 'airwallex::app.system.paDC',
                'type'          => 'text',
                'info'          => 'airwallex::app.system.paDC',
                'channel_based' => true,
                'locale_based'  => false,
                'value_key'     => 'sales.payment_methods.airwallex.paDC',
            ],
            [
                'name'          => 'accountDC',
                'title'         => 'airwallex::app.system.accountDC',
                'type'          => 'text',
                'info'          => 'airwallex::app.system.accountDC',
                'channel_based' => true,
                'locale_based'  => false,
                'value_key'     => 'sales.payment_methods.airwallex.accountDC',
            ],
            [
                'name'          => 'production',
                'title'         => 'airwallex::app.system.production',
                'type'          => 'boolean',
                'channel_based' => true,
                'locale_based'  => false,
                'value_key'     => 'sales.payment_methods.airwallex.production',
            ],
            [
                'name'          => 'active',
                'title'         => 'admin::app.configuration.index.sales.payment-methods.status',
                'type'          => 'boolean',
                'channel_based' => true,
                'locale_based'  => false,
                'value_key'     => 'sales.payment_methods.airwallex.active',
            ]
        ]
    ]
];