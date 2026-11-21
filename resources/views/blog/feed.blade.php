{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
  <title>{{ $site }} travel blog</title>
  <link>{{ route('blog.index') }}</link>
  <atom:link href="{{ route('blog.feed') }}" rel="self" type="application/rss+xml"/>
  <description>Travel guides, flight deals and tips for travellers from the UAE.</description>
  @foreach($posts as $p)
  <item><title>{{ $p->title }}</title><link>{{ $p->url() }}</link><guid>{{ $p->url() }}</guid><pubDate>{{ $p->published_at->toRssString() }}</pubDate><description>{{ $p->excerpt }}</description></item>
  @endforeach
</channel>
</rss>
