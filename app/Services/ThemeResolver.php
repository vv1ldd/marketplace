<?php

namespace App\Services;

use App\Models\LegalEntity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ThemeResolver
{
    public function supportedThemes(): array
    {
        return config('app.supported_themes', ['consortium', 'partner', 'retro']);
    }

    public function themeLabels(): array
    {
        return config('app.theme_labels', [
            'consortium' => 'Flagship',
            'partner' => 'Partner',
            'retro' => 'Retro',
        ]);
    }

    public function resolve(Request $request): array
    {
        foreach ($this->candidates($request) as $source => $candidate) {
            $theme = $this->normalize($candidate);

            if ($theme) {
                return ['theme' => $theme, 'source' => $source];
            }
        }

        return [
            'theme' => $this->normalize(config('app.theme_fallback')) ?? 'consortium',
            'source' => 'fallback',
        ];
    }

    public function normalize(?string $theme): ?string
    {
        $theme = strtolower(trim((string) $theme));

        return in_array($theme, $this->supportedThemes(), true) ? $theme : null;
    }

    public function persistUserTheme(User $user, string $theme): void
    {
        $theme = $this->normalize($theme);

        if (! $theme) {
            return;
        }

        $meta = $user->meta ?? [];
        $meta['preferred_theme'] = $theme;
        $user->forceFill([
            'theme' => $theme,
            'meta' => $meta,
        ])->save();
    }

    private function candidates(Request $request): array
    {
        $user = $request->user() ?: Auth::guard('sellers')->user();
        $legalEntity = $this->legalEntityFor($request, $user);

        return [
            'query' => $request->query('theme'),
            'cookie' => $request->cookie('theme'),
            'session' => $request->session()->get('theme'),
            'profile' => $user instanceof User ? $this->profileTheme($user) : null,
            'profile_demographic' => $user instanceof User ? $this->themeForDemographic($user) : null,
            'legal_entity_region' => $this->themeForCountry($legalEntity?->country_code),
            'browser_context' => $this->themeForBrowserContext($request),
            'app' => config('app.theme_fallback'),
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

    private function profileTheme(User $user): ?string
    {
        $meta = $user->meta ?? [];

        return $user->theme
            ?: ($meta['preferred_theme'] ?? null)
            ?: data_get($meta, 'profile.theme')
            ?: null;
    }

    private function themeForDemographic(User $user): ?string
    {
        $meta = $user->meta ?? [];
        $signals = array_filter([
            $meta['age_band'] ?? null,
            $meta['generation'] ?? null,
            $meta['profession'] ?? null,
            $meta['persona'] ?? null,
            data_get($meta, 'profile.age_band'),
            data_get($meta, 'profile.persona'),
        ]);

        $text = strtolower(implode(' ', $signals));

        if ($text === '') {
            return null;
        }

        if (str_contains($text, 'retro') || str_contains($text, 'gen-x') || str_contains($text, 'engineer') || str_contains($text, 'developer')) {
            return 'retro';
        }

        if (str_contains($text, 'creator') || str_contains($text, 'young') || str_contains($text, 'gen-z')) {
            return 'synthwave';
        }

        if (str_contains($text, 'enterprise') || str_contains($text, 'finance') || str_contains($text, 'executive')) {
            return 'carbon';
        }

        return null;
    }

    private function themeForCountry(?string $countryCode): ?string
    {
        $countryCode = strtoupper(trim((string) $countryCode));

        return match ($countryCode) {
            'NO', 'SE', 'FI', 'DK', 'IS' => 'nordic',
            'GE', 'AM', 'UZ', 'KZ', 'TM' => 'partner',
            default => null,
        };
    }

    private function themeForBrowserContext(Request $request): ?string
    {
        $ua = strtolower($request->userAgent() ?? '');
        $colorScheme = strtolower((string) $request->header('Sec-CH-Prefers-Color-Scheme'));

        if (str_contains($ua, 'iphone') || str_contains($ua, 'android')) {
            return 'partner';
        }

        if (str_contains($colorScheme, 'light')) {
            return 'retro';
        }

        return null;
    }
}
