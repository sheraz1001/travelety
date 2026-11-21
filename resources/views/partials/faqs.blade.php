@if(!empty($faqs))
  @if(empty($hideHeading))<h2>{{ $heading ?? "Frequently asked questions" }}</h2>@endif
  @foreach($faqs as $f)
    <details><summary>{{ $f['q'] }}</summary><p>{{ $f['a'] }}</p></details>
  @endforeach
  @push('schema')
  <script type="application/ld+json">{!! json_encode(['@context'=>'https://schema.org','@type'=>'FAQPage','mainEntity'=>collect($faqs)->map(fn($f)=>['@type'=>'Question','name'=>$f['q'],'acceptedAnswer'=>['@type'=>'Answer','text'=>$f['a']]])->values()], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) !!}</script>
  @endpush
@endif
