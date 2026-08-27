<?php

namespace App\Domains\Website\Actions;

use App\Domains\Settings\Actions\GetSettingAction;

class ComposeOrganizationJsonLdAction
{
    /**
     * Sitewide schema.org Organization graph for the public layout.
     *
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        $settings = app(GetSettingAction::class);
        $name = $this->present($settings->execute('institute_name', '')) ?? 'Akuru Institute';
        $url = rtrim((string) config('app.url'), '/');
        $address = $this->present($settings->execute('address', 'Malé, Republic of Maldives'));
        $phone = $this->present($settings->execute('phone', ''));
        $email = $this->present($settings->execute('email', ''));

        $json = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $name !== '' ? $name : 'Akuru Institute',
            'url' => $url !== '' ? $url : url('/'),
            'logo' => asset('images/apple-touch-icon.png'),
        ];

        if ($address !== null) {
            $json['address'] = [
                '@type' => 'PostalAddress',
                'streetAddress' => $address,
                'addressLocality' => 'Malé',
                'addressCountry' => 'MV',
            ];
        }
        if ($phone !== null) {
            $json['telephone'] = $phone;
        }
        if ($email !== null) {
            $json['email'] = $email;
        }

        return $json;
    }

    private function present(mixed $value): ?string
    {
        if ($value === null || is_array($value)) {
            return null;
        }
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
