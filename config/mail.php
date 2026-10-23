<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send all email
    | messages unless another mailer is explicitly specified when sending
    | the message. All additional mailers can be configured within the
    | "mailers" array. Examples of each type of mailer are provided.
    |
    */

    'default' => env('MAIL_MAILER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of mail "transport" drivers that can be used
    | when delivering an email. You may specify which one you're using for
    | your mailers below. You may also add additional mailers if needed.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses", "ses-v2",
    |            "postmark", "resend", "log", "array",
    |            "failover", "roundrobin"
    |
    */

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME'),
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
            // 'message_stream_id' => env('POSTMARK_MESSAGE_STREAM_ID'),
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'resend' => [
            'transport' => 'resend',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
            'retry_after' => 60,
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
            'retry_after' => 60,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all emails sent by your application to be sent from
    | the same address. Here you may specify a name and address that is
    | used globally for all emails that are sent by your application.
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'Laravel')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pengalihan Email Dummy (HUMANIX)
    |--------------------------------------------------------------------------
    |
    | Data demo memakai alamat yang tidak bisa menerima email. Email ke alamat
    | seperti itu dialihkan ke `to` (subjek diberi awalan "[Untuk: ...]").
    | Di luar production, default `to` adalah akun SMTP (MAIL_USERNAME);
    | di production pengalihan hanya aktif bila MAIL_REDIRECT_TO diisi.
    |
    | `all` = alihkan SEMUA email (default di luar production), karena alamat
    | pelamar uji seperti nama@gmail.com tampak asli padahal akunnya tidak ada.
    | Alamat di `except` (dipisah koma) tetap dikirim langsung.
    | Bila `all` mati, hanya alamat dummy yang dialihkan: domain di daftar
    | di bawah atau (bila check_mx aktif) domain tanpa server email (MX).
    |
    */

    'redirect' => [
        'to' => env('MAIL_REDIRECT_TO', env('APP_ENV') === 'production' ? null : env('MAIL_USERNAME')),
        'all' => (bool) env('MAIL_REDIRECT_ALL', env('APP_ENV') !== 'production'),
        'except' => array_filter(array_map('trim', explode(',', (string) env('MAIL_REDIRECT_EXCEPT', '')))),
        'dummy_domains' => [
            'example.com', 'example.org', 'example.net', 'contoh.com', 'contoh.co.id', 'contoh.id',
            'humanix.test', 'localhost', '*.test', '*.example', '*.invalid', '*.local', '*.localhost',
        ],
        'check_mx' => (bool) env('MAIL_REDIRECT_CHECK_MX', true),
    ],

];
