<section class="alerts" id="alerts">
  <div class="wrap alert-box">
    <div>
      <span class="eyebrow">Never overpay</span>
      <h2>{{ isset($route) ? 'Get an alert when '.$route->origin()?->city.' – '.$route->destination()?->city.' gets cheaper' : 'Get fare drops in your inbox' }}</h2>
      <p>Weekly deals from your nearest airport, plus alerts when a route you're watching gets cheaper. Unsubscribe anytime.</p>
    </div>
    <div>
      @if(session('subscribed'))<div class="flash">You're on the list. Watch your inbox for the best fares.</div>@endif
      <form class="alert-form" method="post" action="{{ route('subscribe') }}">
        @csrf
        <input type="email" name="email" required placeholder="Your email address" aria-label="Email">
        @isset($route)<input type="hidden" name="origin" value="{{ $route->origin_iata }}"><input type="hidden" name="destination" value="{{ $route->destination_iata }}">@endisset
        <input type="text" name="website" style="display:none" tabindex="-1" autocomplete="off">
        <button class="btn" type="submit">Notify me</button>
      </form>
      @error('email')<div class="alert-msg" style="color:#c0392b">{{ $message }}</div>@enderror
    </div>
  </div>
</section>
