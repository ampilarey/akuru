{{--
    NAME_TAGS_PLAN §5.2 (slice NT0): one A4 sheet of tags, laid out for print
    and for the proof picture from the same markup. Each tag is placed at its
    layout cell in millimetres; each element inside it is placed in
    millimetres on the tag. A text's direction is its own script's
    (`dir="auto"`), so a Thaana name on a Latin design reads right to left
    inside its box. Fonts come as @font-face rules the caller built for the
    reader (the browser, or Chrome opening this page from disk). Images are
    data: URIs — nothing here is fetched over a network.

    $fontCss   string   the @font-face rules
    $page      array    {w, h} mm (A4: 210 × 297)
    $tags      list     [{x, y, w, h, background, elements: [{type, x, y, w, h, ...}]}]
    $sheets    ?list    several sheets' tags in one document (the print PDF, NT3); else [$tags]
    $grayscale bool     a black-and-white job: the sheet is printed in grey (NT3)
    $cropMarks bool     draw marks in the gutters
    $watermark ?string  diagonal text across the sheet (the customer's proof)
--}}
<!DOCTYPE html>
<html lang="{{ $lang ?? 'en' }}" dir="ltr">
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Print sheet' }}</title>
    <style>
        {!! $fontCss !!}
        @page { size: {{ $page['w'] }}mm {{ $page['h'] }}mm; margin: 0; }
        html, body { margin: 0; padding: 0; background: #FFFFFF; }
        .sheet { position: relative; width: {{ $page['w'] }}mm; height: {{ $page['h'] }}mm; overflow: hidden; page-break-after: always; break-after: page; }
        .sheet:last-child { page-break-after: auto; break-after: auto; }
        .sheet.grey { filter: grayscale(100%); }
        .tag { position: absolute; overflow: hidden; box-sizing: border-box; }
        .el { position: absolute; box-sizing: border-box; display: flex; align-items: center; }
        .el.text { white-space: pre-wrap; line-height: 1.15; }
        .el.text.left { justify-content: flex-start; text-align: start; }
        .el.text.center { justify-content: center; text-align: center; }
        .el.text.right { justify-content: flex-end; text-align: end; }
        .el img { width: 100%; height: 100%; object-fit: contain; display: block; }
        .el img.cover { object-fit: cover; }
        .crop { position: absolute; background: #999; }
        .watermark { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; pointer-events: none; }
        .watermark span { transform: rotate(-35deg); font: 700 48px/1 sans-serif; color: rgba(122, 31, 43, .28); letter-spacing: .06em; white-space: nowrap; }
    </style>
</head>
<body>
@foreach (($sheets ?? null) ?: [$tags] as $sheetTags)
<div class="sheet{{ ! empty($grayscale) ? ' grey' : '' }}">
    @foreach ($sheetTags as $tag)
        <div class="tag" style="left: {{ $tag['x'] }}mm; top: {{ $tag['y'] }}mm; width: {{ $tag['w'] }}mm; height: {{ $tag['h'] }}mm; background: {{ $tag['background'] ?? '#FFFFFF' }};">
            @foreach ($tag['elements'] as $el)
                @php
                    $box = sprintf('left: %smm; top: %smm; width: %smm; height: %smm;', $el['x'], $el['y'], $el['w'], $el['h']);
                    $rotate = empty($el['rotate']) ? '' : sprintf(' transform: rotate(%sdeg);', $el['rotate']);
                @endphp
                @if ($el['type'] === 'text')
                    <div class="el text {{ $el['align'] ?? 'center' }}" dir="{{ $el['dir'] ?? 'auto' }}" style="{{ $box }}{{ $rotate }} font-family: @isset($el['stack']){{ $el['stack'] }}@else'{{ $el['family'] }}'@endisset; font-size: {{ $el['size'] }}pt; font-weight: {{ $el['weight'] ?? 400 }}; color: {{ $el['color'] ?? '#111111' }};">{{ $el['text'] }}</div>
                @elseif ($el['type'] === 'image')
                    <div class="el" style="{{ $box }}{{ $rotate }}"><img class="{{ ($el['fit'] ?? 'contain') === 'cover' ? 'cover' : '' }}" src="{{ $el['src'] }}" alt=""></div>
                @elseif ($el['type'] === 'rect')
                    <div class="el" style="{{ $box }}{{ $rotate }} background: {{ $el['fill'] ?? 'transparent' }}; border-radius: {{ $el['radius'] ?? 0 }}mm;{{ empty($el['stroke']) ? '' : ' border: '.($el['stroke_mm'] ?? 0.3).'mm solid '.$el['stroke'].';' }}"></div>
                @elseif ($el['type'] === 'ellipse')
                    <div class="el" style="{{ $box }}{{ $rotate }} background: {{ $el['fill'] ?? 'transparent' }}; border-radius: 50%;{{ empty($el['stroke']) ? '' : ' border: '.($el['stroke_mm'] ?? 0.3).'mm solid '.$el['stroke'].';' }}"></div>
                @elseif ($el['type'] === 'line')
                    <div class="el" style="{{ $box }}{{ $rotate }} background: {{ $el['stroke'] ?? '#111111' }};"></div>
                @endif
            @endforeach
        </div>
        @if (! empty($cropMarks))
            @foreach ([[$tag['x'], $tag['y']], [$tag['x'] + $tag['w'], $tag['y']], [$tag['x'], $tag['y'] + $tag['h']], [$tag['x'] + $tag['w'], $tag['y'] + $tag['h']]] as [$cx, $cy])
                <div class="crop" style="left: {{ $cx - 2 }}mm; top: {{ $cy }}mm; width: 4mm; height: 0.2mm;"></div>
                <div class="crop" style="left: {{ $cx }}mm; top: {{ $cy - 2 }}mm; width: 0.2mm; height: 4mm;"></div>
            @endforeach
        @endif
    @endforeach
    @if (! empty($watermark))
        <div class="watermark"><span>{{ $watermark }}</span></div>
    @endif
</div>
@endforeach
</body>
</html>
