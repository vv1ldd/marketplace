<?php

namespace App\Support;

use App\Services\SovereignCalendar;
use Carbon\Carbon;
use Illuminate\Http\Request;

class StorefrontSl1Theme
{
    public static function mapToSl1(?string $theme): string
    {
        return match (strtolower((string) $theme)) {
            'retro', 'consortium' => 'neobrutalism',
            'nordic' => 'light',
            'synthwave', 'carbon', 'partner', 'dark' => 'dark',
            'neobrutalism', 'light' => strtolower((string) $theme),
            default => 'neobrutalism',
        };
    }

    public static function resolveHoliday(?Request $request = null): ?string
    {
        $request ??= request();

        $cookie = (string) $request->cookie('holiday', '');
        if ($cookie === 'none') {
            return null;
        }
        if ($cookie !== '' && $cookie !== 'auto') {
            return strtolower($cookie);
        }

        $manual = (string) $request->query('holiday', '');
        if ($manual !== '' && $manual !== 'none') {
            return strtolower($manual);
        }

        return SovereignCalendar::resolve(Carbon::now());
    }

    /**
     * Holiday accents in SL1 authorize are only enabled for Maestrooo storefront flows.
     * Meanly uses a fixed retro palette and should not inherit calendar/cookie holidays.
     */
    public static function usesMaestroooAuthorizeContext(?Request $request = null): bool
    {
        $request ??= request();

        if (strtolower(trim((string) $request->query('client_app', ''))) === 'maestrooo') {
            return true;
        }

        if (strtolower(trim((string) $request->header('X-App-Client', ''))) === 'maestrooo') {
            return true;
        }

        $host = strtolower($request->getHost());
        if (in_array($host, ['maestrooo.test', 'api.maestrooo.test', 'maestrooo.one', 'api.maestrooo.one'], true)) {
            return true;
        }

        $clientName = strtolower(trim((string) ($request->query('client_name') ?? $request->query('clientName') ?? '')));
        if ($clientName === 'maestrooo') {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public static function augmentAuthorizeQuery(array $query, ?Request $request = null): array
    {
        $request ??= request();
        $embedded = in_array((string) ($query['iframe'] ?? ''), ['1', 'true'], true);

        if (empty($query['ui_theme']) && empty($query['uiTheme'])) {
            $sl1Theme = self::mapToSl1($request->cookie('theme') ?: 'retro');
            $query['ui_theme'] = $sl1Theme;
            $query['uiTheme'] = $sl1Theme;
        }

        if (self::usesMaestroooAuthorizeContext($request)) {
            $holiday = self::resolveHoliday($request);
            if ($holiday) {
                $query['holiday'] = $holiday;
            }
        } else {
            unset($query['holiday']);
        }

        if ($embedded) {
            $query['iframe'] = '1';
        } else {
            unset($query['iframe']);
        }

        return $query;
    }

    public static function appendAuthorizeDisplayParams(?string $url, ?Request $request = null): string
    {
        if (! is_string($url) || $url === '') {
            return '';
        }

        $request ??= request();
        $parts = parse_url($url);
        if ($parts === false) {
            return $url;
        }

        parse_str($parts['query'] ?? '', $query);
        $query = self::augmentAuthorizeQuery($query, $request);
        $parts['query'] = http_build_query($query);

        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = $parts['path'] ?? '';
        $queryString = $parts['query'] !== '' ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        return $scheme.'://'.$host.$port.$path.$queryString.$fragment;
    }

    public static function embeddedAuthorizeStyleTag(): string
    {
        return <<<'HTML'
<style id="meanly-embedded-authorize-theme">
  html { color-scheme: light !important; background: #ffffff !important; }
  body,
  body.is-iframe,
  body.is-iframe main {
    background: #ffffff !important;
    color: #090909 !important;
    color-scheme: light !important;
  }
  body.is-iframe h1,
  body h1,
  .vault-card-title {
    color: #090909 !important;
  }
  body.is-iframe p,
  body p,
  .vault-card-label,
  .vault-card-sub,
  #status,
  .handoff-help {
    color: #535353 !important;
  }
  .eyebrow,
  .other-device-link {
    color: #7c3aed !important;
  }
  .vault-card {
    background: #ffffff !important;
    border: 2px solid #090909 !important;
    box-shadow: 4px 4px 0 #090909 !important;
    color: #090909 !important;
  }
  .vault-card:hover {
    background: #f7f3ff !important;
    border-color: #7c3aed !important;
    box-shadow: 4px 4px 0 #7c3aed !important;
  }
  .vault-card-icon {
    background: rgba(124, 58, 237, 0.12) !important;
    color: #7c3aed !important;
  }
  .btn-create {
    background: #ffffff !important;
    color: #090909 !important;
    border: 2px solid #090909 !important;
    box-shadow: 3px 3px 0 #090909 !important;
  }
  button:not(.btn-create):not(.other-device-link),
  .standalone-link,
  .action-link {
    background: linear-gradient(135deg, #7c3aed, #a855f7) !important;
    color: #ffffff !important;
    border: 3px solid #090909 !important;
    box-shadow: 4px 4px 0 #090909 !important;
  }
</style>
HTML;
    }
}
