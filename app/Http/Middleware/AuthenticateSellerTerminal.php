<?php

namespace App\Http\Middleware;

use App\Models\SellerTerminal;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Аутентификация продавца (LegalEntity) по API-терминалу.
 *
 * Запрос должен содержать заголовки:
 *   X-Terminal-Id:  SL-202506-A3F9K1
 *   X-Terminal-Pin: 123456
 *
 * После успешной аутентификации в атрибуты запроса добавляются:
 *   $request->sellerTerminal   — SellerTerminal модель
 *   $request->legalEntity      — LegalEntity модель
 */
class AuthenticateSellerTerminal
{
    public function handle(Request $request, Closure $next): Response
    {
        $terminalId = $request->header('X-Terminal-Id');
        $terminalPin = $request->header('X-Terminal-Pin');

        // ── Validate presence ─────────────────────────────────────────────────
        if (empty($terminalId) || empty($terminalPin)) {
            return response()->json([
                'success' => false,
                'code'    => 'TERMINAL_CREDENTIALS_MISSING',
                'message' => 'Требуются заголовки X-Terminal-Id и X-Terminal-Pin.',
            ], 401);
        }

        // ── Look up terminal ──────────────────────────────────────────────────
        $terminal = SellerTerminal::with('legalEntity')
            ->where('terminal_id', $terminalId)
            ->first();

        if (!$terminal) {
            return $this->unauthorized('TERMINAL_NOT_FOUND', 'Терминал не найден.');
        }

        // ── Validate PIN ──────────────────────────────────────────────────────
        if (!$terminal->verifyPin($terminalPin)) {
            return $this->unauthorized('INVALID_PIN', 'Неверный PIN терминала.');
        }

        // ── Check terminal validity ───────────────────────────────────────────
        if (!$terminal->isValid()) {
            return $this->unauthorized('TERMINAL_INACTIVE', 'Терминал деактивирован или истёк срок действия.');
        }

        // ── Check seller status ───────────────────────────────────────────────
        $legalEntity = $terminal->legalEntity;

        if (!$legalEntity || !$legalEntity->is_active) {
            return $this->unauthorized('SELLER_INACTIVE', 'Продавец неактивен или не найден.');
        }

        // ── Audit ─────────────────────────────────────────────────────────────
        $terminal->recordUsage($request->ip());

        // ── Inject into request ───────────────────────────────────────────────
        $request->attributes->set('seller_terminal', $terminal);
        $request->attributes->set('legal_entity', $legalEntity);

        // Also bind as macros for convenience: $request->sellerTerminal()
        $request->macro('sellerTerminal', fn() => $request->attributes->get('seller_terminal'));
        $request->macro('legalEntity',    fn() => $request->attributes->get('legal_entity'));

        return $next($request);
    }

    private function unauthorized(string $code, string $message): Response
    {
        return response()->json([
            'success' => false,
            'code'    => $code,
            'message' => $message,
        ], 401);
    }
}
