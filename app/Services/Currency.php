<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Visitor currency: chosen in the header (cookie "cur"), defaulting by country.
 * Live supplier prices (hotels, flights) are requested in this currency;
 * stored prices (route fares, eSIM) are converted with daily rates.
 */
class Currency
{
    public const COOKIE = 'cur';

    /** code => [name, symbol shown in the picker] — ordered for the picker. */
    public const LIST = [
        'USD' => ['US dollar', '$'], 'EUR' => ['Euro', '€'], 'GBP' => ['British pound', '£'], 'AED' => ['UAE dirham', 'د.إ'],
        'SAR' => ['Saudi riyal', '﷼'], 'QAR' => ['Qatari riyal', 'QR'], 'KWD' => ['Kuwaiti dinar', 'KD'], 'BHD' => ['Bahraini dinar', 'BD'],
        'OMR' => ['Omani rial', 'OMR'], 'EGP' => ['Egyptian pound', 'E£'], 'JOD' => ['Jordanian dinar', 'JD'], 'TRY' => ['Turkish lira', '₺'],
        'INR' => ['Indian rupee', '₹'], 'PKR' => ['Pakistani rupee', 'Rs'], 'BDT' => ['Bangladeshi taka', '৳'], 'LKR' => ['Sri Lankan rupee', 'Rs'],
        'NPR' => ['Nepalese rupee', 'Rs'], 'PHP' => ['Philippine peso', '₱'], 'IDR' => ['Indonesian rupiah', 'Rp'], 'MYR' => ['Malaysian ringgit', 'RM'],
        'SGD' => ['Singapore dollar', 'S$'], 'THB' => ['Thai baht', '฿'], 'VND' => ['Vietnamese dong', '₫'], 'CNY' => ['Chinese yuan', '¥'],
        'HKD' => ['Hong Kong dollar', 'HK$'], 'JPY' => ['Japanese yen', '¥'], 'KRW' => ['South Korean won', '₩'], 'AUD' => ['Australian dollar', 'A$'],
        'NZD' => ['New Zealand dollar', 'NZ$'], 'CAD' => ['Canadian dollar', 'C$'], 'CHF' => ['Swiss franc', 'CHF'], 'SEK' => ['Swedish krona', 'kr'],
        'NOK' => ['Norwegian krone', 'kr'], 'DKK' => ['Danish krone', 'kr'], 'PLN' => ['Polish złoty', 'zł'], 'CZK' => ['Czech koruna', 'Kč'],
        'HUF' => ['Hungarian forint', 'Ft'], 'RON' => ['Romanian leu', 'lei'], 'ZAR' => ['South African rand', 'R'], 'NGN' => ['Nigerian naira', '₦'],
        'KES' => ['Kenyan shilling', 'KSh'], 'MAD' => ['Moroccan dirham', 'MAD'], 'BRL' => ['Brazilian real', 'R$'], 'MXN' => ['Mexican peso', 'MX$'],
        'AZN' => ['Azerbaijani manat', '₼'], 'GEL' => ['Georgian lari', '₾'], 'KZT' => ['Kazakhstani tenge', '₸'], 'UZS' => ['Uzbekistani som', 'soʻm'],
    ];

    /** Currencies with no minor unit in everyday use. */
    public const NO_DECIMALS = ['JPY', 'KRW', 'IDR', 'VND', 'HUF', 'PKR', 'LKR', 'NPR', 'BDT', 'NGN', 'KES', 'UZS', 'KZT', 'INR', 'PHP', 'CLP', 'TWD'];

    /** Visitor country (Cloudflare) → default currency. */
    public const GEO = [
        'AE' => 'AED', 'SA' => 'SAR', 'QA' => 'QAR', 'KW' => 'KWD', 'BH' => 'BHD', 'OM' => 'OMR', 'EG' => 'EGP', 'JO' => 'JOD', 'TR' => 'TRY',
        'IN' => 'INR', 'PK' => 'PKR', 'BD' => 'BDT', 'LK' => 'LKR', 'NP' => 'NPR', 'PH' => 'PHP', 'ID' => 'IDR', 'MY' => 'MYR', 'SG' => 'SGD',
        'TH' => 'THB', 'VN' => 'VND', 'CN' => 'CNY', 'HK' => 'HKD', 'JP' => 'JPY', 'KR' => 'KRW', 'AU' => 'AUD', 'NZ' => 'NZD', 'CA' => 'CAD',
        'CH' => 'CHF', 'SE' => 'SEK', 'NO' => 'NOK', 'DK' => 'DKK', 'PL' => 'PLN', 'CZ' => 'CZK', 'HU' => 'HUF', 'RO' => 'RON', 'ZA' => 'ZAR',
        'NG' => 'NGN', 'KE' => 'KES', 'MA' => 'MAD', 'BR' => 'BRL', 'MX' => 'MXN', 'AZ' => 'AZN', 'GE' => 'GEL', 'KZ' => 'KZT', 'UZ' => 'UZS',
        'GB' => 'GBP', 'DE' => 'EUR', 'FR' => 'EUR', 'IT' => 'EUR', 'ES' => 'EUR', 'NL' => 'EUR', 'BE' => 'EUR', 'AT' => 'EUR', 'IE' => 'EUR',
        'PT' => 'EUR', 'FI' => 'EUR', 'GR' => 'EUR', 'CY' => 'EUR', 'MT' => 'EUR', 'LU' => 'EUR', 'SK' => 'EUR', 'SI' => 'EUR', 'EE' => 'EUR',
        'LV' => 'EUR', 'LT' => 'EUR', 'HR' => 'EUR',
    ];

    /** Fallback rates per 1 USD if the rates service is unreachable. */
    protected const FALLBACK = [
        'USD' => 1, 'EUR' => 0.86, 'GBP' => 0.76, 'AED' => 3.6725, 'SAR' => 3.75, 'QAR' => 3.64, 'KWD' => 0.307, 'BHD' => 0.376, 'OMR' => 0.3845,
        'EGP' => 48.5, 'JOD' => 0.709, 'TRY' => 41.5, 'INR' => 88.5, 'PKR' => 281, 'BDT' => 122, 'LKR' => 300, 'NPR' => 141, 'PHP' => 58.5,
        'IDR' => 16500, 'MYR' => 4.2, 'SGD' => 1.3, 'THB' => 32.5, 'VND' => 26300, 'CNY' => 7.1, 'HKD' => 7.78, 'JPY' => 150, 'KRW' => 1400,
        'AUD' => 1.52, 'NZD' => 1.72, 'CAD' => 1.39, 'CHF' => 0.8, 'SEK' => 9.4, 'NOK' => 10.1, 'DKK' => 6.4, 'PLN' => 3.65, 'CZK' => 21,
        'HUF' => 335, 'RON' => 4.35, 'ZAR' => 17.5, 'NGN' => 1500, 'KES' => 129, 'MAD' => 9.1, 'BRL' => 5.4, 'MXN' => 18.5, 'AZN' => 1.7,
        'GEL' => 2.7, 'KZT' => 520, 'UZS' => 12000,
    ];

    protected static ?string $current = null;

    public static function valid(?string $code): bool
    {
        return $code !== null && isset(self::LIST[strtoupper($code)]);
    }

    public static function current(): string
    {
        if (self::$current) {
            return self::$current;
        }
        $req = request();
        $c = strtoupper((string) $req->cookie(self::COOKIE, ''));
        if (! self::valid($c)) {
            $geo = strtoupper((string) $req->header('CF-IPCountry', ''));
            $c = self::GEO[$geo] ?? 'USD';
        }

        return self::$current = $c;
    }

    public static function set(string $code): void
    {
        self::$current = strtoupper($code);
    }

    /** Rates per 1 USD, refreshed twice a day. */
    public static function rates(): array
    {
        $cached = Cache::get('fx.usd.v1');
        if (is_array($cached) && ! empty($cached['rates'])) {
            return $cached['rates'];
        }

        return self::refresh() ?? self::FALLBACK;
    }

    public static function refresh(): ?array
    {
        try {
            $r = Http::timeout(8)->acceptJson()->get('https://open.er-api.com/v6/latest/USD');
            $rates = $r->json('rates');
            if ($r->successful() && is_array($rates) && isset($rates['AED'])) {
                Cache::put('fx.usd.v1', ['rates' => $rates, 'at' => now()->toIso8601String()], now()->addHours(13));

                return $rates;
            }
        } catch (\Throwable $e) {
            Log::warning('FX refresh failed: '.$e->getMessage());
        }
        Cache::put('fx.usd.v1', ['rates' => self::FALLBACK, 'at' => null], now()->addMinutes(30));

        return null;
    }

    public static function convert(float $amount, string $from, ?string $to = null): float
    {
        $from = strtoupper($from);
        $to = strtoupper($to ?? self::current());
        if ($from === $to) {
            return $amount;
        }
        $r = self::rates();
        $f = (float) ($r[$from] ?? self::FALLBACK[$from] ?? 0);
        $t = (float) ($r[$to] ?? self::FALLBACK[$to] ?? 0);
        if ($f <= 0 || $t <= 0) {
            return $amount;
        }

        return $amount / $f * $t;
    }

    /** Format an amount already in $code. */
    public static function format(float $amount, ?string $code = null, ?int $decimals = null): string
    {
        $code = strtoupper($code ?? self::current());
        if ($decimals === null) {
            $decimals = in_array($code, self::NO_DECIMALS) || $amount >= 1000 ? 0 : (fmod(round($amount, 2), 1.0) == 0.0 ? 0 : 2);
        }

        return $code.' '.number_format($amount, $decimals);
    }

    /** Convert from $from into the visitor's currency and format. */
    public static function show(?float $amount, string $from = 'USD', ?int $decimals = null): string
    {
        if ($amount === null) {
            return '—';
        }

        return self::format(self::convert($amount, $from), null, $decimals);
    }

    /** Fares stored from the flight data API are in the site's base fare currency. */
    public static function base(): string
    {
        return strtoupper((string) Setting::get('currency', 'aed'));
    }
}
