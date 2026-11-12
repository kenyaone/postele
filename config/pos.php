<?php

return [
    'cafe_name'    => env('POS_CAFE_NAME', "Tele Caf\u{00E9}"),
    'till_number'  => env('POS_TILL_NUMBER', 'TBD'),
    'phone'        => env('POS_PHONE', '0797 953 918'),
    'thank_you_wa' => env('POS_WA', 'wa.me/254797953918'),
    'bridge_token' => env('BRIDGE_TOKEN'),

    // Manager login OTP over WhatsApp (switched on in Manage > Settings)
    'otp_driver'     => env('POS_OTP_DRIVER', 'log'),
    'at_username'    => env('AT_USERNAME'),
    'at_api_key'     => env('AT_API_KEY'),
    'at_wa_number'   => env('AT_WA_NUMBER'),
    'at_wa_template' => env('AT_WA_TEMPLATE_ID'),
    'at_wa_url'      => env('AT_WA_URL'),
];
