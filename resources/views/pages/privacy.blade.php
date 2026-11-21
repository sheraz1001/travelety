<p>Last updated: {{ now()->format('j F Y') }}</p>
<p>This policy explains what information {{ $site }} collects and how we use it.</p>
<h2>Information we collect</h2>
<ul><li><strong>Searches:</strong> the routes, dates and number of travellers you search, used to show results and improve the site.</li><li><strong>Email address:</strong> only if you sign up for price alerts or newsletters.</li><li><strong>Usage data:</strong> pages visited, device and browser type, collected through cookies and analytics tools.</li></ul>
<h2>Partners</h2>
<p>When you click through to book, you leave {{ $site }} and the partner's own privacy policy applies. Partners may use cookies to recognise that you came from us, so we can be paid a commission.</p>
<h2>Your choices</h2>
<p>You can unsubscribe from emails at any time using the link in every email, and you can block or delete cookies in your browser settings. To ask about or delete your data, contact {{ \App\Models\Setting::get('contact_email') }}.</p>
