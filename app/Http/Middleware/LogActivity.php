<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Audit trail: who opened which page / did which action, and when. Never stores form contents
 * (PINs, amounts, codes) - only the page, the record it acted on, the result and the device.
 */
class LogActivity
{
    /** Machine polling and public pages that would only add noise. */
    private const SKIP = ['api/*', 'p/*', 'clock', 'up', 'build/*', 'favicon.ico'];

    public function handle(Request $request, Closure $next): Response
    {
        $before = $request->user()?->id;   // so a logout is still attributed
        $response = $next($request);
        try { $this->record($request, $response, $before); } catch (\Throwable $e) { report($e); }
        return $response;
    }

    private function record(Request $r, Response $response, ?int $before): void
    {
        if ($r->is(...self::SKIP) || in_array($r->method(), ['HEAD', 'OPTIONS'], true)) { return; }
        $isLogin = $r->routeIs('login.store');
        $user = $r->user()?->id ?? $before;
        if (! $user && ! $isLogin) { return; }
        if ($r->isMethod('GET') && ($r->ajax() || $r->expectsJson())) { return; } // background refreshes
        $route = $r->route();
        $subject = $route ? collect($route->parameters())
            ->map(fn ($v) => is_object($v) && method_exists($v, 'getKey') ? $v->getKey() : (is_scalar($v) ? $v : null))
            ->filter(fn ($v) => $v !== null)->map(fn ($v, $k) => "{$k}={$v}")->implode(' ') : '';
        $note = null;
        if ($isLogin) {
            $who = $r->input('user_id', $r->input('user'));
            $note = $user ? 'Logged in' : 'LOGIN FAILED' . (is_scalar($who) && $who !== '' ? ' (login #' . (int) $who . ')' : '');
        } elseif ($r->routeIs('logout')) {
            $note = 'Logged out';
        }
        $path = '/' . ltrim($r->path(), '/') . ($r->isMethod('GET') && $r->getQueryString() ? '?' . $r->getQueryString() : '');
        ActivityLog::create([
            'user_id' => $user ?: null, 'method' => $r->method(), 'path' => mb_substr($path, 0, 255),
            'route' => $route?->getName(), 'subject' => mb_substr($subject, 0, 150) ?: null,
            'status' => $response->getStatusCode(), 'ip' => $r->ip(),
            'agent' => mb_substr((string) $r->userAgent(), 0, 150), 'note' => $note,
        ]);
    }
}
