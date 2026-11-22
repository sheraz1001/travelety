<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Order extends Model
{
    protected $guarded = [];

    protected $casts = [
        'details' => 'array', 'fulfilment' => 'array', 'amount' => 'float', 'cost_amount' => 'float',
        'paid_at' => 'datetime', 'fulfilled_at' => 'datetime', 'emailed_at' => 'datetime',
    ];

    public const STATUSES = [
        'pending_payment' => 'Awaiting payment', 'paid' => 'Paid', 'processing' => 'Processing',
        'fulfilled' => 'Confirmed', 'needs_attention' => 'Needs attention', 'failed' => 'Payment failed',
        'cancelled' => 'Cancelled', 'refunded' => 'Refunded',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $o) {
            $o->token ??= Str::random(40);
            $o->number ??= 'TRV-'.strtoupper(Str::random(3)).now()->format('ymd').random_int(100, 999);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function isPaid(): bool
    {
        return in_array($this->status, ['paid', 'processing', 'fulfilled', 'needs_attention', 'refunded']);
    }

    public function title(): string
    {
        $d = $this->details ?? [];

        return match ($this->type) {
            'esim' => ($d['name'] ?? 'eSIM').' eSIM · '.($d['data_label'] ?? '').' · '.($d['days'] ?? '').' days',
            'flight' => 'Flight '.collect($d['journey']['legs'] ?? [])->map(fn ($l) => $l['from'].'–'.$l['to'])->implode(', '),
            'hotel' => ($d['hotel_name'] ?? 'Hotel').' · '.($d['nights'] ?? '').' nights',
            'transfer' => 'Transfer · '.($d['vehicle_label'] ?? '').' · '.($d['airport'] ?? ''),
            default => ucfirst($this->type),
        };
    }

    public function money(): string
    {
        return $this->currency.' '.number_format($this->amount, 2);
    }

    public function url(): string
    {
        return match ($this->type) { 'hotel' => route('hotels.booking', $this), 'flight' => route('flights.booking', $this), default => route('orders.show', $this) };
    }
}
