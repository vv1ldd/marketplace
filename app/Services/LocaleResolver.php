<?php

namespace App\Services;

use App\Models\LegalEntity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LocaleResolver
{
    public function supportedLocales(): array
    {
        return config('app.supported_locales', ['ru', 'en']);
    }

    public function localeLabels(): array
    {
        return config('app.locale_labels', [
            'ru' => 'Русский',
            'en' => 'English',
        ]);
    }

    public function resolve(Request $request): array
    {
        foreach ($this->candidates($request) as $source => $candidate) {
            $locale = $this->normalize($candidate);

            if ($locale) {
                return ['locale' => $locale, 'source' => $source];
            }
        }

        return [
            'locale' => $this->normalize(config('app.locale')) ?? 'en',
            'source' => 'fallback',
        ];
    }

    public function normalize(?string $locale): ?string
    {
        $locale = strtolower(str_replace('_', '-', trim((string) $locale)));

        if ($locale === '') {
            return null;
        }

        $base = explode('-', $locale)[0];

        return in_array($base, $this->supportedLocales(), true) ? $base : null;
    }

    public function persistUserLocale(User $user, string $locale): void
    {
        $locale = $this->normalize($locale);

        if (! $locale) {
            return;
        }

        $meta = $user->meta ?? [];
        $meta['preferred_locale'] = $locale;
        $user->meta = $meta;
        $user->save();
    }

    private function candidates(Request $request): array
    {
        $user = $request->user() ?: Auth::guard('sellers')->user();
        $legalEntity = $this->legalEntityFor($request, $user);

        return [
            'query' => $request->query('locale') ?: $request->query('lang'),
            'session' => $request->session()->get('locale'),
            'profile' => $user instanceof User ? $this->profileLocale($user) : null,
            'legal_entity_region' => $this->localeForCountry($legalEntity?->country_code),
            'profile_region' => $this->localeForCountry($this->profileCountry($user)),
            'browser' => $this->browserLocale($request),
            'app' => config('app.locale'),
        ];
    }

    private function legalEntityFor(Request $request, mixed $user): ?LegalEntity
    {
        $activeId = $request->session()->get('active_legal_entity_id');
        if ($activeId) {
            return LegalEntity::find($activeId);
        }

        if ($user instanceof User) {
            return $user->legalEntities()->first() ?: $user->managedLegalEntities()->first();
        }

        if (is_object($user) && method_exists($user, 'managedLegalEntities')) {
            return $user->managedLegalEntities()->first();
        }

        return null;
    }

    private function profileLocale(User $user): ?string
    {
        $meta = $user->meta ?? [];

        return $meta['preferred_locale']
            ?? $meta['locale']
            ?? data_get($meta, 'profile.locale')
            ?? null;
    }

    private function profileCountry(mixed $user): ?string
    {
        if (! $user instanceof User) {
            return null;
        }

        $meta = $user->meta ?? [];

        return $meta['country_code']
            ?? data_get($meta, 'profile.country_code')
            ?? null;
    }

    private function browserLocale(Request $request): ?string
    {
        $header = $request->header('Accept-Language');
        if (! $header) {
            return null;
        }

        foreach (explode(',', $header) as $part) {
            $locale = trim(explode(';', $part)[0] ?? '');
            if ($this->normalize($locale)) {
                return $locale;
            }
        }

        return null;
    }

    private function localeForCountry(?string $countryCode): ?string
    {
        $countryCode = strtoupper(trim((string) $countryCode));

        return match ($countryCode) {
            'RU', 'BY' => 'ru',
            'KZ' => 'kk',
            'UZ' => 'uz',
            'GE' => 'ka',
            'AM' => 'hy',
            'TM' => 'tk',
            'TR' => 'tr',
            'ES', 'MX', 'AR', 'CL', 'CO', 'PE' => 'es',
            default => null,
        };
    }
}
