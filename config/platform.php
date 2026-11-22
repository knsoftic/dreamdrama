<?php

/*
|--------------------------------------------------------------------------
| Platform settings schema
|--------------------------------------------------------------------------
| Every setting the admin can change from Admin > Settings. The value saved
| in the `settings` table wins; the `default` here is used until then.
| Types: text, textarea, code, number, decimal, bool, select, color
*/

return [
    // Where the server fetches the APK from, to stream it under our own domain.
    // Kept in .env so the real link is never shown in any page or admin UI.
    'apk_source' => env('APK_SOURCE_URL', ''),

    'settings' => [
        'general' => [
            'label' => 'General',
            'fields' => [
                'site_name' => ['label' => 'Site name', 'type' => 'text', 'default' => env('APP_NAME', 'PlayEarn')],
                'site_tagline' => ['label' => 'Tagline', 'type' => 'text', 'default' => 'Watch videos, earn money'],
                'currency_symbol' => ['label' => 'Currency symbol', 'type' => 'text', 'default' => 'Rs'],
                'registration_open' => ['label' => 'New users can register', 'type' => 'bool', 'default' => '1'],
                'require_invite_code' => ['label' => 'Invite code required to register', 'type' => 'bool', 'default' => '1', 'help' => 'When on, every new user must enter a valid invite code.'],
                'max_accounts_per_ip' => ['label' => 'Max accounts per IP (0 = unlimited)', 'type' => 'number', 'default' => '3'],
                'support_whatsapp' => ['label' => 'Support WhatsApp number', 'type' => 'text', 'default' => ''],
            ],
        ],
        'earning' => [
            'label' => 'Earning & Cycle',
            'fields' => [
                'videos_per_cycle' => ['label' => 'Videos per cycle', 'type' => 'number', 'default' => '10'],
                'ads_per_cycle' => ['label' => 'Ads per cycle', 'type' => 'number', 'default' => '10'],
                'ad_placement' => [
                    'label' => 'When to show ads',
                    'type' => 'select',
                    'default' => 'spread',
                    'options' => [
                        'spread' => 'Spread evenly (with 10/10, one ad after each video)',
                        'after_cycle' => 'All ads together, after all videos',
                    ],
                ],
                'rate_per_second' => ['label' => 'Video earning per second', 'type' => 'decimal', 'default' => '0.002', 'help' => 'How much the user earns for each second of video watched'],
                'rate_per_ad' => ['label' => 'Fixed earning per ad', 'type' => 'decimal', 'default' => '0.05'],
                'min_watch_seconds' => ['label' => 'Minimum seconds for a video to count', 'type' => 'number', 'default' => '5'],
                'ad_duration_seconds' => ['label' => 'How many seconds to show each ad', 'type' => 'number', 'default' => '15'],
                'heartbeat_seconds' => ['label' => 'Watch-time heartbeat (seconds)', 'type' => 'number', 'default' => '5', 'help' => 'How often the browser tells the server the video is still playing'],
                'earn_once_per_video' => ['label' => 'Earn only once per video', 'type' => 'bool', 'default' => '1'],
                'auto_next_video' => ['label' => 'Auto-play the next video when one ends', 'type' => 'bool', 'default' => '1'],
            ],
        ],
        'payments' => [
            'label' => 'Subscription Payments',
            'fields' => [
                'payment_methods' => ['label' => 'Payment methods (comma separated)', 'type' => 'text', 'default' => 'JazzCash,Easypaisa,Bank Transfer'],
                'payment_instructions' => ['label' => 'Payment instructions (shown to the user)', 'type' => 'textarea', 'default' => "JazzCash: 03XX-XXXXXXX (Account Title: Your Name)\nEasypaisa: 03XX-XXXXXXX (Account Title: Your Name)\nAfter paying, enter the Transaction ID below."],
            ],
        ],
        'withdrawals' => [
            'label' => 'Withdrawals',
            'fields' => [
                'withdrawals_enabled' => ['label' => 'Withdrawal requests are on (master switch for all plans)', 'type' => 'bool', 'default' => '1'],
                'withdraw_methods' => ['label' => 'Withdraw methods (comma separated)', 'type' => 'text', 'default' => 'JazzCash,Easypaisa,Bank Transfer'],
                'subadmin_min_withdrawal' => ['label' => 'Sub-admin minimum withdrawal', 'type' => 'decimal', 'default' => '500'],
            ],
        ],
        'referral' => [
            'label' => 'Invites',
            'fields' => [
                'referral_min_cycles' => [
                    'label' => 'An invite counts as "active" once the new user completes this many cycles',
                    'type' => 'number',
                    'default' => '1',
                    'help' => 'Protects against fake accounts: just registering does not count an invite. 0 = counts on register. The number of invites required to withdraw is set per plan (Admin → Plans).',
                ],
            ],
        ],
        'landing' => [
            'label' => 'Landing Page',
            'fields' => [
                'brand_color' => ['label' => 'Primary color', 'type' => 'color', 'default' => '#8b5cf6', 'help' => 'The whole app theme color (buttons, highlights)'],
                'brand_color_2' => ['label' => 'Secondary color (gradient)', 'type' => 'color', 'default' => '#ec4899'],
                'hero_badge' => ['label' => 'Small line at the top (badge)', 'type' => 'text', 'default' => 'Watch videos, earn money'],
                'hero_title' => ['label' => 'Main heading (first line)', 'type' => 'text', 'default' => 'Watch short videos,'],
                'hero_highlight' => ['label' => 'Coloured part of the heading (second line)', 'type' => 'text', 'default' => 'earn every second.'],
                'hero_subtitle' => ['label' => 'Text below the heading', 'type' => 'textarea', 'default' => 'Scroll entertaining short videos, watch the ad breaks, and withdraw your earnings to JazzCash / Easypaisa.'],
                'hero_primary_btn' => ['label' => 'First button (text)', 'type' => 'text', 'default' => 'Create free account'],
                'hero_secondary_btn' => ['label' => 'Second button (text)', 'type' => 'text', 'default' => 'Login'],
                'show_how' => ['label' => 'Show the "How it works" section', 'type' => 'bool', 'default' => '1'],
                'how_title' => ['label' => '"How it works" title', 'type' => 'text', 'default' => 'How does it work?'],
                'show_plans' => ['label' => 'Show Plans on the landing page', 'type' => 'bool', 'default' => '1'],
                'footer_text' => ['label' => 'Footer text (empty = site name)', 'type' => 'text', 'default' => ''],
            ],
        ],
        'download' => [
            'label' => 'Android App',
            'fields' => [
                'app_download_enabled' => ['label' => 'Show the "App download" button on the landing page', 'type' => 'bool', 'default' => '1'],
                'app_version' => ['label' => 'App version (for display)', 'type' => 'text', 'default' => '1.0.0'],
                'app_size_mb' => ['label' => 'App size (MB)', 'type' => 'text', 'default' => '6'],
                'app_min_android' => ['label' => 'Minimum Android version', 'type' => 'text', 'default' => '6.0'],
            ],
        ],
        'scripts' => [
            'label' => 'Ad Network Scripts',
            'fields' => [
                'head_scripts' => ['label' => '<head> scripts', 'type' => 'code', 'default' => '', 'help' => 'Paste a Monetag/Adsterra verification meta tag, Social Bar, pop-under or any global script here'],
                'body_scripts' => ['label' => 'Scripts before </body>', 'type' => 'code', 'default' => ''],
            ],
        ],
    ],
];
