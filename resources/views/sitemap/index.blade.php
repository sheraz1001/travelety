{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach($maps as $m)
  <sitemap><loc>{{ route('sitemap.show', $m) }}</loc><lastmod>{{ now()->toAtomString() }}</lastmod></sitemap>
@endforeach
</sitemapindex>
