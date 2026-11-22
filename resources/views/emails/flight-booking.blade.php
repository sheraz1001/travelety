@php $legs = $d['journey']['legs'] ?? []; $site = \App\Models\Setting::get('site_name'); $hm = fn($m) => $m ? floor($m/60).'h '.($m%60 ? ($m%60).'m' : '') : ''; @endphp
<!DOCTYPE html><html><body style="margin:0;background:#f4f6fb;font-family:Arial,Helvetica,sans-serif;color:#0f1a2e">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6fb;padding:24px 0"><tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#fff;border-radius:14px;overflow:hidden">
<tr><td style="background:#1463ff;padding:22px 28px;color:#fff;font-size:22px;font-weight:bold">travel<span style="color:#cfe0ff">ety</span></td></tr>
<tr><td style="padding:28px">
<h1 style="margin:0 0 8px;font-size:22px">Your flight is booked</h1>
<p style="margin:0 0 18px;color:#3b4660">Booking reference <b>{{ $o->number }}</b>@foreach($f['pnrs'] ?? [] as $p) · Airline reference <b>{{ $p }}</b>@endforeach</p>
@foreach($legs as $l)
<table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e9f2;border-radius:10px;margin-bottom:10px"><tr><td style="padding:14px 16px;font-size:14px">
<span style="color:#6b7590;font-size:12px">{{ $l['dir']==='INBOUND' ? 'RETURN' : (str_starts_with($l['dir'],'LEG') ? 'FLIGHT '.substr($l['dir'],3) : 'OUTBOUND') }} · {{ \Carbon\Carbon::parse($l['dep'])->format('D j M Y') }}</span><br>
@foreach($l['segments'] as $s)<b>{{ substr($s['dep'],11,5) }}</b> {{ $s['from'] }} → <b>{{ substr($s['arr'],11,5) }}</b> {{ $s['to'] }} · {{ $s['airline'] }} {{ $s['flight'] }} ({{ $hm($s['min']) }})<br>@endforeach
</td></tr></table>
@endforeach
<p style="font-size:14px"><b>Passengers:</b> @foreach($d['passengers'] ?? [] as $p){{ $p['firstName'] }} {{ $p['lastName'] }}@if(!$loop->last), @endif @endforeach<br><b>Fare:</b> {{ $d['offer']['family'] ?? '' }}@if(!empty($d['extras']))<br><b>Extras:</b> @foreach($d['extras'] as $x){{ $d['passengers'][$x['pax']]['firstName'] ?? '' }} – {{ $x['label'] }}@if(!$loop->last), @endif @endforeach @endif<br><b>Total paid:</b> {{ $o->money() }}</p>
<p style="margin:22px 0"><a href="{{ route('flights.booking', $o) }}" style="background:#ff6b2c;color:#fff;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:bold">View your booking</a></p>
<p style="font-size:13px;color:#6b7590">Check in online with the airline 24–48 hours before departure using the airline reference. Make sure your passport and visas are valid for your trip. For changes, reply to this email with your booking reference.</p>
</td></tr>
<tr><td style="padding:16px 28px;background:#0b1426;color:#aab4cc;font-size:12px">© {{ date('Y') }} {{ $site }}</td></tr>
</table></td></tr></table></body></html>
