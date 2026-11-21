<?php

/*
|--------------------------------------------------------------------------
| Platform settings schema
|--------------------------------------------------------------------------
| Every setting the admin can change from Admin > Settings. The value saved
| in the `settings` table wins; the `default` here is used until then.
| Types: text, textarea, code, number, decimal, bool, select
*/

return [
    'settings' => [
        'general' => [
            'label' => 'General',
            'fields' => [
                'site_name' => ['label' => 'Site name', 'type' => 'text', 'default' => 'PlayEarn'],
                'site_tagline' => ['label' => 'Tagline', 'type' => 'text', 'default' => 'Videos dekho, paise kamao'],
                'currency_symbol' => ['label' => 'Currency symbol', 'type' => 'text', 'default' => 'Rs'],
                'registration_open' => ['label' => 'Naye users register kar sakte hain', 'type' => 'bool', 'default' => '1'],
                'max_accounts_per_ip' => ['label' => 'Ek IP se max accounts (0 = unlimited)', 'type' => 'number', 'default' => '3'],
                'support_whatsapp' => ['label' => 'Support WhatsApp number', 'type' => 'text', 'default' => ''],
            ],
        ],
        'earning' => [
            'label' => 'Earning & Cycle',
            'fields' => [
                'videos_per_cycle' => ['label' => 'Ek cycle mein videos', 'type' => 'number', 'default' => '10'],
                'ads_per_cycle' => ['label' => 'Ek cycle mein ads', 'type' => 'number', 'default' => '10'],
                'ad_placement' => [
                    'label' => 'Ads kab dikhein',
                    'type' => 'select',
                    'default' => 'spread',
                    'options' => [
                        'spread' => 'Videos ke beech barabar (10/10 par har video ke baad 1 ad)',
                        'after_cycle' => 'Saari videos ke baad saare ads ek saath',
                    ],
                ],
                'rate_per_second' => ['label' => 'Video earning per second', 'type' => 'decimal', 'default' => '0.002', 'help' => 'User ko har second video dekhne par itne paise milenge'],
                'rate_per_ad' => ['label' => 'Fixed earning per ad', 'type' => 'decimal', 'default' => '0.05'],
                'min_watch_seconds' => ['label' => 'Video count hone ke liye kam az kam seconds', 'type' => 'number', 'default' => '5'],
                'ad_duration_seconds' => ['label' => 'Har ad kitne seconds dikhana hai', 'type' => 'number', 'default' => '15'],
                'heartbeat_seconds' => ['label' => 'Watch-time heartbeat (seconds)', 'type' => 'number', 'default' => '5', 'help' => 'Browser itne seconds baad server ko batata hai ke video chal rahi hai'],
                'earn_once_per_video' => ['label' => 'Ek video par sirf ek dafa earning', 'type' => 'bool', 'default' => '1'],
                'auto_next_video' => ['label' => 'Video khatam hone par agli video khud chale', 'type' => 'bool', 'default' => '1'],
            ],
        ],
        'payments' => [
            'label' => 'Subscription Payments',
            'fields' => [
                'payment_methods' => ['label' => 'Payment methods (comma se alag)', 'type' => 'text', 'default' => 'JazzCash,Easypaisa,Bank Transfer'],
                'payment_instructions' => ['label' => 'Payment instructions (user ko dikhengi)', 'type' => 'textarea', 'default' => "JazzCash: 03XX-XXXXXXX (Account Title: Your Name)\nEasypaisa: 03XX-XXXXXXX (Account Title: Your Name)\nPayment ke baad Transaction ID neeche likhein."],
            ],
        ],
        'withdrawals' => [
            'label' => 'Withdrawals',
            'fields' => [
                'withdrawals_enabled' => ['label' => 'Withdrawal requests on hain', 'type' => 'bool', 'default' => '1'],
                'withdraw_methods' => ['label' => 'Withdraw methods (comma se alag)', 'type' => 'text', 'default' => 'JazzCash,Easypaisa,Bank Transfer'],
            ],
        ],
        'scripts' => [
            'label' => 'Ad Network Scripts',
            'fields' => [
                'head_scripts' => ['label' => '<head> scripts', 'type' => 'code', 'default' => '', 'help' => 'Monetag/Adsterra verification meta tag, Social Bar ya koi global script yahan paste karein'],
                'body_scripts' => ['label' => '</body> se pehle scripts', 'type' => 'code', 'default' => ''],
            ],
        ],
    ],
];
