{{-- Site SEO + social (Open Graph / Twitter) meta, from the admin SEO settings.
     Site-level defaults; public bio pages render their own per-page OG separately. --}}
@php
    $lfSiteName = config('linkforge.name');
    $lfMetaDesc = \App\Models\Setting::get('seo_meta_description') ?: config('linkforge.description');
    $lfOgTitle = \App\Models\Setting::get('seo_og_title') ?: trim($lfSiteName.' · '.config('linkforge.tagline'), ' ·');
    $lfOgDesc = \App\Models\Setting::get('seo_og_description') ?: $lfMetaDesc;
    $lfOgImage = (string) \App\Models\Setting::get('seo_og_image');
    $lfOgImage = $lfOgImage !== '' ? (\Illuminate\Support\Str::startsWith($lfOgImage, 'http') ? $lfOgImage : asset($lfOgImage)) : null;
    $lfTwitter = (string) \App\Models\Setting::get('seo_twitter_handle');
@endphp
<meta name="description" content="{{ $lfMetaDesc }}">

<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ $lfSiteName }}">
<meta property="og:title" content="{{ $lfOgTitle }}">
<meta property="og:description" content="{{ $lfOgDesc }}">
<meta property="og:url" content="{{ url()->current() }}">
@if ($lfOgImage)<meta property="og:image" content="{{ $lfOgImage }}">@endif

<meta name="twitter:card" content="{{ $lfOgImage ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $lfOgTitle }}">
<meta name="twitter:description" content="{{ $lfOgDesc }}">
@if ($lfOgImage)<meta name="twitter:image" content="{{ $lfOgImage }}">@endif
@if ($lfTwitter)<meta name="twitter:site" content="{{ $lfTwitter }}">@endif

{{-- Operator analytics. IDs are validated to [A-Za-z0-9-]. --}}
@php
    $gaId = \App\Models\Setting::get('seo_ga_id');
    $gtmId = \App\Models\Setting::get('seo_gtm_id');
@endphp
@if ($gtmId)
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','{{ $gtmId }}');</script>
@endif
@if ($gaId)
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $gaId }}"></script>
    <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','{{ $gaId }}');</script>
@endif
