{{-- Per-item share preview. Params: $ogType (flovatar|flobot|collectible|template), $ogId, $ogTitle, $ogUrl --}}
@php($og = \App\Support\ItemImages::og($ogType, $ogId))
<meta property="og:title" content="{{ $ogTitle }}" />
<meta property="og:description" content="{{ $ogDescription ?? 'Flovatar is a next generation NFT creativity platform where people can become creator and make their characters with any combination they like.' }}" />
<meta property="og:url" content="{{ $ogUrl }}" />
<meta property="og:site_name" content="Flovatar" />
<meta property="og:image" content="{{ $og['url'] }}" />
<meta property="og:image:type" content="{{ $og['type'] }}" />
<meta property="og:image:width" content="{{ $og['width'] }}" />
<meta property="og:image:height" content="{{ $og['height'] }}" />
<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:title" content="{{ $ogTitle }}" />
<meta name="twitter:image" content="{{ $og['url'] }}" />
