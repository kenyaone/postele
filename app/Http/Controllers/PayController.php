<?php

namespace App\Http\Controllers;

use App\Models\StkRequest;
use App\Models\Ticket;
use App\Services\KopoKopo;
use Illuminate\Http\Request;

/**
 * Customer pays a bill from their own phone: QR on the bill -> this page -> M-Pesa prompt to the
 * customer's number. The prompt carries the bill, so the payment closes that exact bill, even days later.
 * Public (no login): each link only opens one bill, and only with its secret code.
 */
class PayController extends Controller
{
    public static function code(Ticket $t): string
    {
        return $t->id . '-' . substr(hash_hmac('sha256', 'pay|' . $t->id, (string) config('app.key')), 0, 10);
    }

    public static function link(Ticket $t): string
    {
        return rtrim((string) config('app.url'), '/') . '/p/' . self::code($t);
    }

    private function resolve(string $code): Ticket
    {
        abort_unless(preg_match('/^(\d+)-([a-f0-9]{10})$/', $code, $m), 404);
        $t = Ticket::with('items.item')->findOrFail((int) $m[1]);
        abort_unless(hash_equals(self::code($t), $code), 404);
        return $t;
    }

    public function show(string $code)
    {
        $t = $this->resolve($code);
        return view('pay.show', ['t' => $t, 'code' => $code, 'ready' => KopoKopo::ready(), 'last' => StkRequest::where('ticket_id', $t->id)->latest('id')->first()]);
    }

    public function send(Request $request, string $code, KopoKopo $kk)
    {
        $t = $this->resolve($code);
        if (! in_array($t->status, ['open', 'sent'], true) || $t->items->isEmpty()) return redirect()->route('pay.show', $code);
        $phone = KopoKopo::normalizePhone($request->validate(['phone' => 'required|string|max:20'])['phone']);
        if (! $phone) return back()->withInput()->withErrors(['phone' => 'Enter your M-Pesa number, like 0712 345 678.']);
        if (! KopoKopo::ready()) return back()->withErrors(['phone' => 'Paying from your phone is not available right now. Please pay at the counter.']);
        if (StkRequest::where('ticket_id', $t->id)->where('created_at', '>=', now()->subSeconds(45))->exists()) {
            return back()->withErrors(['phone' => 'A prompt was just sent. Check your phone, or try again in a minute.']);
        }
        $amount = (int) ceil($t->total);
        $stk = StkRequest::create(['ticket_id' => $t->id, 'phone' => $phone, 'amount' => $amount, 'status' => 'pending', 'requested_by' => $t->opened_by]);
        try {
            $location = $kk->stkPush($stk);
            $stk->update(['location' => $location, 'kk_id' => basename((string) parse_url($location, PHP_URL_PATH))]);
        } catch (\Throwable $e) {
            report($e);
            $stk->update(['status' => 'failed', 'error' => 'Could not reach Kopo Kopo.']);
            return back()->withErrors(['phone' => 'Could not send the M-Pesa prompt. Try again, or pay at the counter.']);
        }
        return redirect()->route('pay.show', $code)->with('sent', substr($phone, 0, 6) . '***' . substr($phone, -3));
    }

    public function status(string $code)
    {
        $t = $this->resolve($code);
        $s = StkRequest::where('ticket_id', $t->id)->latest('id')->first();
        return response()->json(['paid' => $t->status === 'paid', 'prompt' => $s?->status, 'error' => $s?->status === 'failed' ? ($s->error ?: 'The payment did not go through.') : null]);
    }
}
