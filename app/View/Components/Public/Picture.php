<?php

namespace App\View\Components\Public;

use App\Services\WebPImageService;
use Illuminate\View\Component;

class Picture extends Component
{
    public function __construct(
        public string $src,
        public string $alt = '',
        public string $class = '',
        public string $loading = 'lazy',
        public bool $webp = true,
    ) {}

    public function webpUrl(): ?string
    {
        if (! $this->webp) {
            return null;
        }

        $service = app(WebPImageService::class);
        $webpPath = $service->getWebPPath($this->src);

        if ($webpPath === null) {
            return null;
        }

        return asset('storage/' . $webpPath);
    }

    public function imgUrl(): string
    {
        return asset('storage/' . $this->src);
    }

    public function render()
    {
        return view('components.public.picture', [
            'webpUrl' => $this->webpUrl(),
            'imgUrl' => $this->imgUrl(),
        ]);
    }
}
