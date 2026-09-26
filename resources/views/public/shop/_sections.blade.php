{{-- BOOKSHOP_PLAN §6.3 (slice B5): the storefront's sections, in the vendor's order — on a phone in
     the mobile order when one was set (CSS `order`, never a second markup). Everything here was
     resolved by RenderSectionsAction: text in the visitor's language, URLs from the shop's own
     library and catalogue, embeds built from an allowed video URL or a pair of coordinates. The
     only raw render is the rich body, cleaned to the prose profile on every save (SectionTypes). --}}
@php($preview = $preview ?? false)
@php($currency = config('bookshop.currency', 'MVR'))
<div class="sf-sections" data-testid="storefront-sections">
@foreach($sections as $section)
    @php($grid = 'grid grid-cols-2 gap-4 md:grid-cols-4')
    <section
        class="sf-section sf-{{ $section['type'] }} {{ $section['draft_hidden'] ? 'sf-draft-hidden' : '' }} {{ in_array($section['type'], ['hero', 'announcement'], true) ? '' : 'py-8' }}"
        style="--sf-mobile-order: {{ $section['mobile_order'] ?? $loop->index }}"
        data-section="{{ $section['id'] }}"
        data-section-type="{{ $section['type'] }}"
        @if($section['draft_hidden']) data-draft-hidden="1" @endif
    >
        @if($section['draft_hidden'])
            <p class="container mx-auto px-4 pt-2 text-xs font-semibold text-amber-800">{{ __('shop.hidden_in_preview') }}</p>
        @endif

        @switch($section['type'])
            @case('hero')
                <div class="sf-hero relative overflow-hidden {{ count($section['images']) > 0 ? 'sf-has-image' : '' }}" style="--n: {{ max(1, count($section['images'])) }}">
                    @if(count($section['images']) > 0)
                        <div class="sf-hero-slides absolute inset-0" aria-hidden="true">
                            @foreach($section['images'] as $image)
                                <img src="{{ $image }}" alt="" class="sf-slide" style="--i: {{ $loop->index }}" loading="{{ $loop->first ? 'eager' : 'lazy' }}">
                            @endforeach
                        </div>
                    @endif
                    <div class="sf-hero-copy relative container mx-auto px-4 py-14 md:py-20 {{ $section['align'] === 'center' ? 'text-center' : '' }}">
                        @if($section['heading'])<h2 class="text-3xl md:text-5xl font-bold" dir="auto">{{ $section['heading'] }}</h2>@endif
                        @if($section['subheading'])<p class="mt-3 max-w-2xl text-lg {{ $section['align'] === 'center' ? 'mx-auto' : '' }}" dir="auto">{{ $section['subheading'] }}</p>@endif
                        @if(count($section['buttons']) > 0)
                            <p class="mt-6 flex flex-wrap gap-3 {{ $section['align'] === 'center' ? 'justify-center' : '' }}">
                                @foreach($section['buttons'] as $button)
                                    <a href="{{ $button['url'] }}" class="{{ $loop->first ? 'btn-primary' : 'btn-secondary' }}" dir="auto">{{ $button['label'] }}</a>
                                @endforeach
                            </p>
                        @endif
                    </div>
                </div>
                @break

            @case('announcement')
                <div class="sf-announce px-4 py-2 text-center text-sm">
                    @if($section['url'])<a href="{{ $section['url'] }}" class="font-semibold underline" dir="auto">{{ $section['text'] }}</a>@else<span dir="auto">{{ $section['text'] }}</span>@endif
                    @if($section['ends_at'])<span class="ms-2 opacity-80">{{ __('shop.until_date', ['date' => $section['ends_at']]) }}</span>@endif
                </div>
                @break

            @case('featured_products')
            @case('new_arrivals')
            @case('best_sellers')
                @if(count($section['cards']) > 0 || $preview)
                    <div class="container mx-auto px-4">
                        <h2 class="mb-3 text-xl font-semibold" dir="auto">{{ $section['heading'] ?? __('shop.section_'.$section['type']) }}</h2>
                        @if(count($section['cards']) === 0)
                            <p class="text-sm opacity-70">{{ __('shop.no_products_yet') }}</p>
                        @else
                            <div class="{{ ($section['layout'] ?? 'grid') === 'carousel' ? 'sf-carousel' : $grid }}">
                                @foreach($section['cards'] as $card)
                                    @include('public.shop._card', ['card' => $card])
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
                @break

            @case('collection')
                <div class="container mx-auto px-4">
                    <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                        <h2 class="text-xl font-semibold" dir="auto">{{ $section['heading'] ?? $section['collection']['name'] }}</h2>
                        @if($section['see_all'])<a href="{{ $section['collection']['url'] }}" class="sf-link text-sm underline">{{ __('shop.see_all') }} →</a>@endif
                    </div>
                    @if(count($section['cards']) === 0)
                        <p class="text-sm opacity-70">{{ __('shop.no_products_yet') }}</p>
                    @else
                        <div class="{{ $grid }}">
                            @foreach($section['cards'] as $card)
                                @include('public.shop._card', ['card' => $card])
                            @endforeach
                        </div>
                    @endif
                </div>
                @break

            @case('category_tiles')
                @if(count($section['tiles']) > 0)
                    <div class="container mx-auto px-4">
                        @if($section['heading'])<h2 class="mb-3 text-xl font-semibold" dir="auto">{{ $section['heading'] }}</h2>@endif
                        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                            @foreach($section['tiles'] as $tile)
                                <a href="{{ $tile['url'] }}" class="sf-tile relative flex aspect-square flex-col justify-end overflow-hidden p-3 hover:shadow-md" data-tile>
                                    @if($tile['image'])<img src="{{ $tile['image'] }}" alt="" class="absolute inset-0 h-full w-full object-cover opacity-70" loading="lazy">@endif
                                    <span class="relative font-semibold" dir="auto">{{ $tile['name'] }}</span>
                                    <span class="relative text-xs opacity-80">{{ __('shop.result_count', ['count' => $tile['count']]) }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
                @break

            @case('text_image')
                <div class="container mx-auto grid items-center gap-8 px-4 md:grid-cols-2">
                    <div class="{{ $section['image_side'] === 'start' ? 'md:order-2' : '' }}">
                        @if($section['heading'])<h2 class="mb-3 text-2xl font-semibold" dir="auto">{{ $section['heading'] }}</h2>@endif
                        @if($section['body'])<div class="prose max-w-none" dir="auto">{!! $section['body'] !!}</div>@endif
                    </div>
                    @if($section['image'])
                        <img src="{{ $section['image'] }}" alt="{{ $section['image_alt'] }}" class="sf-card w-full object-cover {{ $section['image_side'] === 'start' ? 'md:order-1' : '' }}" loading="lazy">
                    @endif
                </div>
                @break

            @case('gallery')
                @if(count($section['images']) > 0)
                    <div class="container mx-auto px-4">
                        @if($section['heading'])<h2 class="mb-3 text-xl font-semibold" dir="auto">{{ $section['heading'] }}</h2>@endif
                        <div class="grid grid-cols-2 gap-3 md:grid-cols-4" data-testid="gallery">
                            @foreach($section['images'] as $image)
                                <a href="{{ $image['large'] }}" target="_blank" rel="noopener"><img src="{{ $image['card'] }}" alt="{{ $image['alt'] }}" class="sf-card aspect-square w-full object-cover" loading="lazy"></a>
                            @endforeach
                        </div>
                    </div>
                @endif
                @break

            @case('testimonials')
                @if(count($section['items']) > 0)
                    <div class="container mx-auto px-4">
                        @if($section['heading'])<h2 class="mb-3 text-xl font-semibold" dir="auto">{{ $section['heading'] }}</h2>@endif
                        <div class="grid gap-4 md:grid-cols-3">
                            @foreach($section['items'] as $item)
                                <blockquote class="sf-card p-4">
                                    <p class="italic" dir="auto">“{{ $item['quote'] }}”</p>
                                    @if($item['name'] ?? null)<footer class="mt-2 text-sm font-semibold" dir="auto">— {{ $item['name'] }}</footer>@endif
                                </blockquote>
                            @endforeach
                        </div>
                    </div>
                @endif
                @break

            @case('faq')
                @if(count($section['items']) > 0)
                    <div class="container mx-auto max-w-3xl px-4">
                        @if($section['heading'])<h2 class="mb-3 text-xl font-semibold" dir="auto">{{ $section['heading'] }}</h2>@endif
                        <div class="divide-y">
                            @foreach($section['items'] as $item)
                                <details class="sf-faq py-3">
                                    <summary dir="auto">{{ $item['question'] }}</summary>
                                    @if($item['answer'] ?? null)<p class="mt-2 whitespace-pre-line text-sm opacity-90" dir="auto">{{ $item['answer'] }}</p>@endif
                                </details>
                            @endforeach
                        </div>
                    </div>
                @endif
                @break

            @case('delivery_returns')
                <div class="container mx-auto max-w-3xl px-4">
                    <h2 class="mb-3 text-xl font-semibold" dir="auto">{{ $section['heading'] ?? __('shop.section_delivery_returns') }}</h2>
                    @if($section['body'])<div class="prose mb-4 max-w-none" dir="auto">{!! $section['body'] !!}</div>@endif
                    <ul class="sf-card divide-y text-sm" data-testid="delivery-methods">
                        @foreach($section['methods'] as $method)
                            <li class="flex flex-wrap items-baseline justify-between gap-2 p-3">
                                <span dir="auto">{{ $method['name'] }} <span class="opacity-70">· {{ __('shop.handling_days_n', ['days' => $method['handling_days']]) }}</span>@if($method['note']) <span class="block text-xs opacity-70" dir="auto">{{ $method['note'] }}</span>@endif</span>
                                <span class="font-semibold">
                                    @if($method['carrier_paid']){{ __('shop.carrier_paid_on_arrival') }}
                                    @elseif((float) $method['fee'] === 0.0){{ __('shop.free') }}
                                    @else{{ $section['currency'] }} {{ $method['fee'] }}@if($method['free_over']) <span class="text-xs font-normal opacity-70">· {{ __('shop.free_over_amount', ['amount' => $section['currency'].' '.$method['free_over']]) }}</span>@endif
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                    <p class="mt-3 text-sm" data-testid="returns-line">{{ __('shop.returns_within_days', ['days' => $section['return_window_days']]) }}@if($section['return_conditions']) <span class="whitespace-pre-line opacity-90" dir="auto">{{ $section['return_conditions'] }}</span>@endif</p>
                </div>
                @break

            @case('contact_map')
                <div class="container mx-auto grid gap-6 px-4 md:grid-cols-2">
                    <div>
                        <h2 class="mb-3 text-xl font-semibold" dir="auto">{{ $section['heading'] ?? __('shop.contact_heading') }}</h2>
                        <ul class="space-y-1 text-sm">
                            @if($section['contact']['phone'] ?? null)<li><a class="sf-link" href="tel:{{ preg_replace('/\s+/', '', $section['contact']['phone']) }}">{{ $section['contact']['phone'] }}</a></li>@endif
                            @if($section['contact']['viber'] ?? null)<li>Viber: {{ $section['contact']['viber'] }}</li>@endif
                            @if($section['contact']['email'] ?? null)<li><a class="sf-link" href="mailto:{{ $section['contact']['email'] }}">{{ $section['contact']['email'] }}</a></li>@endif
                            @if($section['contact']['address'] ?? null)<li dir="auto">{{ $section['contact']['address'] }}</li>@endif
                        </ul>
                        @if($section['hours'])<h3 class="mb-1 mt-3 font-semibold">{{ __('shop.opening_hours') }}</h3><p class="whitespace-pre-line text-sm" dir="auto">{{ $section['hours'] }}</p>@endif
                        @if($section['map'])<p class="mt-3 text-sm"><a class="sf-link underline" href="{{ $section['map']['link'] }}" target="_blank" rel="noopener">{{ __('shop.open_map') }}</a></p>@endif
                    </div>
                    @if($section['map'])
                        <iframe src="{{ $section['map']['embed'] }}" title="{{ __('shop.map') }}" class="sf-card h-72 w-full" loading="lazy" referrerpolicy="no-referrer-when-downgrade" data-testid="map-embed"></iframe>
                    @endif
                </div>
                @break

            @case('video')
                @if($section['embed'])
                    <div class="container mx-auto max-w-4xl px-4">
                        @if($section['heading'])<h2 class="mb-3 text-xl font-semibold" dir="auto">{{ $section['heading'] }}</h2>@endif
                        <div class="sf-card aspect-video overflow-hidden">
                            <iframe src="{{ $section['embed'] }}" title="{{ $section['heading'] ?? 'Video' }}" class="h-full w-full" loading="lazy" allow="encrypted-media; picture-in-picture; fullscreen" referrerpolicy="strict-origin-when-cross-origin" data-testid="video-embed"></iframe>
                        </div>
                    </div>
                @endif
                @break

            @case('newsletter')
                {{-- B9c: the shop's news by email, with consent; the shop sends it and links the unsubscribe page. --}}
                <div class="container mx-auto max-w-2xl px-4">
                    <div class="sf-card p-5" data-testid="newsletter-signup">
                        <h2 class="mb-1 text-xl font-semibold" dir="auto">{{ $section['heading'] ?? __('shop.newsletter_heading') }}</h2>
                        <p class="mb-3 text-sm opacity-90" dir="auto">{{ $section['body'] ?? __('shop.newsletter_body') }}</p>
                        @if(session('newsletter_joined') === $section['vendor_slug'])
                            <p class="rounded bg-green-50 p-2 text-sm text-green-800" data-testid="newsletter-thanks">{{ __('shop.newsletter_thanks') }}</p>
                        @elseif($preview)
                            <p class="text-xs opacity-70">{{ __('shop.newsletter_preview_note') }}</p>
                        @else
                            <form method="POST" action="{{ $section['action'] }}" class="flex flex-wrap items-end gap-2">
                                @csrf
                                <label class="text-sm">{{ __('shop.newsletter_email') }}<input type="email" name="email" required maxlength="255" class="form-input block w-64" data-testid="newsletter-email"></label>
                                <label class="text-sm">{{ __('shop.newsletter_name') }}<input name="name" maxlength="120" class="form-input block w-48" data-testid="newsletter-name"></label>
                                <label class="flex w-full items-start gap-2 text-xs"><input type="checkbox" name="consent" value="1" required data-testid="newsletter-consent"> <span>{{ __('shop.newsletter_consent', ['shop' => $section['vendor_name']]) }}</span></label>
                                <button type="submit" class="btn-primary" data-testid="newsletter-submit">{{ __('shop.newsletter_join') }}</button>
                            </form>
                            @error('email')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
                        @endif
                    </div>
                </div>
                @break
        @endswitch
    </section>
@endforeach
</div>
