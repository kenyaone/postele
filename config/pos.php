<?php

return [
    'cafe_name'    => env('POS_CAFE_NAME', "Tele Caf\u{00E9}"),
    'address'      => env('POS_ADDRESS'),
    'logo'         => null,
    'print_logo'   => false,
    'till_number'  => env('POS_TILL_NUMBER', 'TBD'),
    'phone'        => env('POS_PHONE', '0797 953 918'),
    'thank_you_wa' => env('POS_WA', 'wa.me/254797953918'),
    'bridge_token' => env('BRIDGE_TOKEN'),

    // Kopo Kopo (M-Pesa). Values live in .env only.
    'kk_base_url'      => env('KK_BASE_URL'),
    'kk_client_id'     => env('KK_CLIENT_ID'),
    'kk_client_secret' => env('KK_CLIENT_SECRET'),
    'kk_api_key'       => env('KK_API_KEY'),
    'kk_till'          => env('KK_TILL_NUMBER'),
    'kk_stk_till'      => env('KK_STK_TILL'),

];
