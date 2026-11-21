@php
$paths = [
 'plane' => '<path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z"/>',
 'hotel' => '<path d="M3 21V7l9-4 9 4v14"/><path d="M9 21v-6h6v6M8 10h.01M12 10h.01M16 10h.01"/>',
 'car' => '<path d="M5 17h14M3 17V11l2-5h14l2 5v6M3 11h18"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/>',
 'compass' => '<circle cx="12" cy="12" r="9"/><path d="m15.5 8.5-2 5-5 2 2-5z"/>',
 'sim' => '<rect x="6" y="2" width="12" height="20" rx="2"/><path d="M10 6h4M9 18h6"/>',
 'key' => '<path d="M14 16H9m10 0h2v-3.2a2 2 0 0 0-.6-1.4L18 9l-1.6-3.2A2 2 0 0 0 14.6 5H9.4a2 2 0 0 0-1.8 1.1L6 9l-2.4 2.4a2 2 0 0 0-.6 1.4V16h2"/><circle cx="7" cy="16" r="2"/><circle cx="17" cy="16" r="2"/>',
 'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
 'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
 'check' => '<path d="M20 6 9 17l-5-5"/>',
 'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
 'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
 'money' => '<path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
 'bell' => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0"/>',
];
@endphp
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{{ $sw ?? 2 }}" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $paths[$n] ?? '' !!}</svg>
