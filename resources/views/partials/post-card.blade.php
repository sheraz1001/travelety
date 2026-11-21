<a class="post-card" href="{{ $post->url() }}">
  <div class="img" @if($post->coverUrl()) style="background-image:url('{{ $post->coverUrl() }}')" @endif role="img" aria-label="{{ $post->cover_alt }}"></div>
  <div class="in">
    @if($post->category)<span class="cat">{{ $post->category->name }}</span>@endif
    <h3>{{ $post->title }}</h3>
    <p>{{ \Illuminate\Support\Str::limit($post->excerpt, 120) }}</p>
    <span class="meta">{{ $post->published_at?->format('j M Y') }} · {{ $post->readingMinutes() }} min read</span>
  </div>
</a>
