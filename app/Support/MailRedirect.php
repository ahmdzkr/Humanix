<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Menentukan alamat tujuan sebenarnya untuk email keluar.
 * Di lingkungan uji semua email (mode `all`), atau hanya alamat dummy, dialihkan ke `mail.redirect.to`
 * agar email tetap bisa diperiksa. Lihat config/mail.php bagian "redirect".
 */
class MailRedirect
{
    /** Alamat tujuan sebenarnya: alamat asli, atau alamat pengalihan bila aslinya dummy. */
    public static function resolve(string $email): string
    {
        $target = self::target();

        if ($target === null || strcasecmp($email, $target) === 0 || self::isExcepted($email)) {
            return $email;
        }

        return config('mail.redirect.all') || self::isDummy($email) ? $target : $email;
    }

    private static function isExcepted(string $email): bool
    {
        foreach ((array) config('mail.redirect.except', []) as $address) {
            if (strcasecmp($email, (string) $address) === 0) {
                return true;
            }
        }

        return false;
    }

    public static function target(): ?string
    {
        $to = config('mail.redirect.to');

        return filled($to) && filter_var($to, FILTER_VALIDATE_EMAIL) ? (string) $to : null;
    }

    public static function isDummy(string $email): bool
    {
        $domain = Str::lower(Str::after($email, '@'));

        if ($domain === '' || ! str_contains($email, '@')) {
            return true;
        }

        foreach ((array) config('mail.redirect.dummy_domains', []) as $pattern) {
            if (Str::is(Str::lower($pattern), $domain)) {
                return true;
            }
        }

        if (! config('mail.redirect.check_mx')) {
            return false;
        }

        // Domain tanpa server email tidak akan pernah menerima pesan; hasil DNS disimpan sehari.
        return ! Cache::remember("mail-mx:{$domain}", now()->addDay(), fn () => checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A'));
    }
}
