@php $g = $d['guest'] ?? []; $in = \Carbon\Carbon::parse($d['checkin']); $out = \Carbon\Carbon::parse($d['checkout']); $site = \App\Models\Setting::get('site_name'); @endphp
<!DOCTYPE html><html><body style="margin:0;background:#f4f6fb;font-family:Arial,Helvetica,sans-serif;color:#0f1a2e">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6fb;padding:24px 0"><tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#fff;border-radius:14px;overflow:hidden">
<tr><td style="background:#1463ff;padding:22px 28px;color:#fff;font-size:22px;font-weight:bold">travel<span style="color:#cfe0ff">ety</span></td></tr>
<tr><td style="padding:28px">
<h1 style="margin:0 0 8px;font-size:22px">Your hotel is booked, {{ $g['first_name'] ?? '' }}!</h1>
<p style="margin:0 0 20px;color:#3b4660">Booking reference <b>{{ $o->number }}</b>@if(!empty($f['hotel_confirmation'])) · Hotel confirmation <b>{{ $f['hotel_confirmation'] }}</b>@endif</p>
<table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e9f2;border-radius:10px">
<tr><td style="padding:16px">
<b style="font-size:17px">{{ $d['hotel_name'] }}</b><br><span style="color:#6b7590;font-size:13px">{{ $d['hotel_address'] }}</span>
<p style="margin:14px 0 0;font-size:14px"><b>Check-in:</b> {{ $in->format('D j M Y') }}<br><b>Check-out:</b> {{ $out->format('D j M Y') }} ({{ $d['nights'] }} night{{ $d['nights']>1?'s':'' }})<br><b>Guests:</b> {{ $d['adults'] }} adult{{ $d['adults']>1?'s':'' }}, {{ $d['rooms'] }} room{{ $d['rooms']>1?'s':'' }}<br><b>Room:</b> {{ $d['room_name'] }} · {{ $d['board'] }}<br><b>Cancellation:</b> {{ $d['refundable'] ? 'Free cancellation (see policy on your booking page)' : 'Non-refundable' }}<br><b>Total paid:</b> {{ $o->money() }}</p>
</td></tr></table>
<p style="margin:22px 0"><a href="{{ route('hotels.booking', $o) }}" style="background:#ff6b2c;color:#fff;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:bold">View your booking</a></p>
<p style="font-size:13px;color:#6b7590">Show this email and a photo ID at check-in. Some hotels ask for a card at check-in for incidentals or a security deposit. To change or cancel, reply to this email with your booking reference.</p>
</td></tr>
<tr><td style="padding:16px 28px;background:#0b1426;color:#aab4cc;font-size:12px">© {{ date('Y') }} {{ $site }} · {{ parse_url(config('app.url'), PHP_URL_HOST) }}</td></tr>
</table></td></tr></table></body></html>
