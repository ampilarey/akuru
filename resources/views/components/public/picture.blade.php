@if($webpUrl)
<picture>
    <source srcset="{{ $webpUrl }}" type="image/webp">
    <img src="{{ $imgUrl }}" alt="{{ $alt }}" loading="{{ $loading }}" class="{{ $class }}" decoding="async">
</picture>
@else
<img src="{{ $imgUrl }}" alt="{{ $alt }}" loading="{{ $loading }}" class="{{ $class }}" decoding="async">
@endif
