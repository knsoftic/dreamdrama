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
                'site_name' => ['label' => 'Site name', 'type' => 'text', 'default' => env('APP_NAME', 'PlayEarn')],
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
                'withdrawals_enabled' => ['label' => 'Withdrawal requests on hain (sab plans ke liye master switch)', 'type' => 'bool', 'default' => '1'],
                'withdraw_methods' => ['label' => 'Withdraw methods (comma se alag)', 'type' => 'text', 'default' => 'JazzCash,Easypaisa,Bank Transfer'],
            ],
        ],
        'referral' => [
            'label' => 'Invites',
            'fields' => [
                'referral_min_cycles' => [
                    'label' => 'Invite "active" tab gina jaye jab naya user itni cycles poori kare',
                    'type' => 'number',
                    'default' => '1',
                    'help' => 'Fake accounts se bachao: sirf register karne se invite count nahi hota. 0 = register karte hi count. Withdrawal ke liye zaroori invites har plan mein alag set hote hain (Admin → Plans).',
                ],
            ],
        ],
        'landing' => [
            'label' => 'Landing Page',
            'fields' => [
                'brand_color' => ['label' => 'Primary color', 'type' => 'color', 'default' => '#8b5cf6', 'help' => 'Poori app ka theme color (buttons, highlights)'],
                'brand_color_2' => ['label' => 'Secondary color (gradient)', 'type' => 'color', 'default' => '#ec4899'],
                'hero_badge' => ['label' => 'Upar chhoti line (badge)', 'type' => 'text', 'default' => 'Videos dekho, paise kamao'],
                'hero_title' => ['label' => 'Bari heading (pehli line)', 'type' => 'text', 'default' => 'Short videos dekho,'],
                'hero_highlight' => ['label' => 'Heading ka colored hissa (doosri line)', 'type' => 'text', 'default' => 'har second kamao.'],
                'hero_subtitle' => ['label' => 'Heading ke neeche text', 'type' => 'textarea', 'default' => 'Entertaining short videos scroll karein, ad breaks dekhein aur apni earning JazzCash / Easypaisa par withdraw karein.'],
                'hero_primary_btn' => ['label' => 'Pehla button (text)', 'type' => 'text', 'default' => 'Free account banayein'],
                'hero_secondary_btn' => ['label' => 'Doosra button (text)', 'type' => 'text', 'default' => 'Login'],
                'show_how' => ['label' => '"Kaise kaam karta hai" section dikhayein', 'type' => 'bool', 'default' => '1'],
                'how_title' => ['label' => '"Kaise kaam karta hai" ka title', 'type' => 'text', 'default' => 'Kaise kaam karta hai?'],
                'show_plans' => ['label' => 'Landing par Plans dikhayein', 'type' => 'bool', 'default' => '1'],
                'footer_text' => ['label' => 'Footer text (khali = site ka naam)', 'type' => 'text', 'default' => ''],
            ],
        ],
        'download' => [
            'label' => 'Android App',
            'fields' => [
                'app_download_enabled' => ['label' => 'Landing par "App download" button dikhayein', 'type' => 'bool', 'default' => '1'],
                'apk_url' => [
                    'label' => 'APK download link',
                    'type' => 'text',
                    'default' => 'https://github.com/knsoftic/dreamdrama/releases/latest/download/dramaearn.apk',
                    'help' => 'GitHub release ka link (har nayi release par khud update). Chahein to apne server ka link daal dein.',
                ],
                'app_version' => ['label' => 'App version (dikhane ke liye)', 'type' => 'text', 'default' => '1.0.0'],
                'app_size_mb' => ['label' => 'App size (MB)', 'type' => 'text', 'default' => '6'],
                'app_min_android' => ['label' => 'Kam az kam Android version', 'type' => 'text', 'default' => '6.0'],
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
