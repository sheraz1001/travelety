<p>Last updated: {{ now()->format('j F Y') }}</p>
<p>By using {{ $site }} you agree to these terms.</p>
<h2>Our service</h2>
<p>{{ $site }} is a search and comparison service. We are not a travel agent or airline and we do not sell travel. Any booking you make is a contract between you and the provider you book with, under their terms and conditions.</p>
<h2>Prices and information</h2>
<p>Prices on our pages are based on recent searches and are provided for information only. They can change at any time and are confirmed only by the provider at the time of booking. Destination information, including visa and entry guidance, is general and may change; always check official sources before you travel.</p>
<h2>Liability</h2>
<p>We work hard to keep information accurate but cannot guarantee it is complete or current. We are not responsible for the services, prices or conduct of third-party providers.</p>
<h2>Contact</h2>
<p>Questions about these terms: {{ \App\Models\Setting::get('contact_email') }}.</p>
