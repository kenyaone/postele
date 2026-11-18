<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\KkEvent;
use App\Models\Payment;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Moves an M-Pesa payment that was put on the wrong bill onto the right one.
 * Only reachable from the manage area, which is already limited to managers.
 */
class PaymentMoveController extends Controller
{
    public function move(Request $request, Payment $payment)
    {
        $data = $request->validate([
            'to_bill' => ['required', 'string', 'max:30'],
            'reason'  => ['required', 'string', 'max:150'],
        ], [
            'to_bill.required' => 'Type the bill number the payment should go to.',
            'reason.required'  => 'Say why the payment is being moved.',
        ]);

        $user = $request->user();

        try {
            $message = DB::transaction(function () use ($payment, $data, $user) {
                $p = Payment::lockForUpdate()->findOrFail($payment->id);
                if (! $p->kk_reference) {
                    throw new RuntimeException('Only M-Pesa payments can be moved here.');
                }

                $target = Ticket::findByOrderNo($data['to_bill']);
                if (! $target) {
                    throw new RuntimeException('No bill found with number ' . $data['to_bill'] . '.');
                }
                if ((int) $p->ticket_id === (int) $target->id) {
                    throw new RuntimeException($p->kk_reference . ' is already on bill #' . $target->order_no . '.');
                }

                // Lock both bills in id order so two moves at once cannot deadlock.
                $tickets = Ticket::with(['items', 'payments'])
                    ->whereIn('id', array_filter([$p->ticket_id, $target->id]))
                    ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $from = $p->ticket_id ? $tickets->get($p->ticket_id) : null;
                $to   = $tickets->get($target->id);

                if (! in_array($to->status, ['open', 'sent'], true)) {
                    $state = $to->status === 'void' ? 'cancelled' : $to->status;
                    throw new RuntimeException('Bill #' . $to->order_no . ' is ' . $state . ', so the payment cannot go on it.');
                }
                $amount = round((float) $p->amount, 2);
                if ($amount > $to->balance + 0.004) {
                    throw new RuntimeException('KSh ' . number_format($amount) . ' is more than bill #' . $to->order_no
                        . ' still owes (KSh ' . number_format($to->balance) . ').');
                }

                $p->update(['ticket_id' => $to->id, 'matched_at' => now(), 'matched_by' => $user->id]);

                // Old bill: if it was cleared by this payment, it is owed again.
                if ($from) {
                    $from->load('payments');
                    $fields = ['notes' => trim(($from->notes ? $from->notes . ' ' : '')
                        . 'M-Pesa ' . $p->kk_reference . ' moved to bill #' . $to->order_no . ': ' . $data['reason'] . '.')];
                    if ($from->status === 'paid' && $from->balance > 0.004) {
                        $fields += ['status' => 'sent', 'closed_at' => null];
                    }
                    $from->update($fields);
                }

                // New bill: cleared once nothing is left to pay.
                $to->load('payments');
                $fields = ['notes' => trim(($to->notes ? $to->notes . ' ' : '')
                    . 'M-Pesa ' . $p->kk_reference . ' moved here'
                    . ($from ? ' from bill #' . $from->order_no : '') . ': ' . $data['reason'] . '.')];
                if ($to->balance <= 0.004) {
                    $fields += ['status' => 'paid', 'closed_at' => now()];
                }
                $to->update($fields);

                DB::table('payment_moves')->insert([
                    'payment_id'     => $p->id,
                    'kk_reference'   => $p->kk_reference,
                    'amount'         => $amount,
                    'from_ticket_id' => $from?->id,
                    'to_ticket_id'   => $to->id,
                    'reason'         => $data['reason'],
                    'moved_by'       => $user->id,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);

                return 'Moved ' . $p->kk_reference . ' (KSh ' . number_format($amount) . ')'
                    . ($from ? ' from bill #' . $from->order_no : '') . ' to bill #' . $to->order_no . '.'
                    . ($from && $from->status === 'sent' ? ' Bill #' . $from->order_no . ' is now unpaid.' : '');
            });
        } catch (RuntimeException $e) {
            return back()->withErrors(['move' => $e->getMessage()])->withInput();
        }

        return back()->with('ok', $message);
    }

    /**
     * Spreads one M-Pesa code over one or more bills, in the order typed.
     * Every bill gets what it still owes; whatever is left goes on the last bill.
     * Bills the code was on before are reopened if they are no longer covered.
     */
    public function split(Request $request, string $code)
    {
        $data = $request->validate([
            'bills'  => ['required', 'string', 'max:200'],
            'reason' => ['required', 'string', 'max:150'],
        ], [
            'bills.required'  => 'Type the bill numbers, e.g. 48, 49, 50.',
            'reason.required' => 'Say why the payment is being split.',
        ]);
        $code = strtoupper(trim($code));
        $user = $request->user();

        try {
            $numbers = array_values(array_filter(preg_split('/[\s,;]+/', trim($data['bills']))));
            if (count($numbers) > 15) {
                throw new RuntimeException('At most 15 bills at a time.');
            }
            $targetIds = [];
            foreach ($numbers as $n) {
                $t = Ticket::findByOrderNo($n);
                if (! $t) {
                    throw new RuntimeException('No bill found with number ' . $n . '.');
                }
                if (in_array($t->id, $targetIds, true)) {
                    throw new RuntimeException('Bill #' . $t->order_no . ' is listed twice.');
                }
                $targetIds[] = $t->id;
            }

            $message = DB::transaction(function () use ($code, $targetIds, $data, $user) {
                $old = Payment::where('kk_reference', $code)->lockForUpdate()->get();
                if ($old->isNotEmpty()) {
                    $total    = round((float) $old->sum('amount'), 2);
                    $method   = $old->first()->method;
                    $received = $old->min('received_at');
                } else {
                    $event = KkEvent::where('reference', $code)->whereNotNull('amount')->orderByDesc('received_at')->first();
                    if (! $event) {
                        throw new RuntimeException('No M-Pesa payment with code ' . $code . '.');
                    }
                    $total    = round((float) $event->amount, 2);
                    $method   = 'kk_till_auto';
                    $received = $event->received_at;
                }

                $oldIds  = $old->pluck('ticket_id')->filter()->unique()->values()->all();
                $tickets = Ticket::with(['items', 'payments'])
                    ->whereIn('id', array_unique(array_merge($oldIds, $targetIds)))
                    ->orderBy('id')->lockForUpdate()->get()->keyBy('id');

                // Take the code off the bills it is on now.
                Payment::whereIn('id', $old->pluck('id'))->delete();
                foreach ($oldIds as $id) {
                    $t = $tickets[$id]->load('payments');
                    if ($t->status === 'paid' && $t->balance > 0.004) {
                        $t->update(['status' => 'sent', 'closed_at' => null]);
                    }
                }

                // Put it on the new bills, in order.
                $left  = $total;
                $parts = [];
                $last  = count($targetIds) - 1;
                foreach ($targetIds as $i => $id) {
                    $t = $tickets[$id]->load('payments');
                    if (! in_array($t->status, ['open', 'sent'], true)) {
                        $state = $t->status === 'void' ? 'cancelled' : 'already paid by other money';
                        throw new RuntimeException('Bill #' . $t->order_no . ' is ' . $state . '.');
                    }
                    $amount = $i === $last ? round($left, 2) : round(min($t->balance, $left), 2);
                    if ($amount < 0.005) {
                        throw new RuntimeException('KSh ' . number_format($total) . ' runs out before bill #' . $t->order_no . '.');
                    }
                    $p = Payment::create([
                        'ticket_id' => $t->id, 'method' => $method, 'amount' => $amount, 'kk_reference' => $code,
                        'received_at' => $received, 'matched_at' => now(), 'matched_by' => $user->id,
                    ]);
                    $left = round($left - $amount, 2);
                    $t->load('payments');
                    if ($t->balance <= 0.004) {
                        $t->update(['status' => 'paid', 'closed_at' => now()]);
                    }
                    $parts[] = ['payment' => $p, 'ticket' => $t, 'amount' => $amount];
                }

                $fromList = collect($oldIds)->map(fn ($id) => '#' . $tickets[$id]->order_no)->join(', ');
                $toList   = collect($parts)->map(fn ($x) => '#' . $x['ticket']->order_no . ' ' . number_format($x['amount']))->join(', ');
                $note     = 'M-Pesa ' . $code . ' split' . ($fromList ? ' (was on ' . $fromList . ')' : '') . ' onto ' . $toList . ': ' . $data['reason'] . '.';
                foreach (array_unique(array_merge($oldIds, $targetIds)) as $id) {
                    $t = $tickets[$id];
                    $t->update(['notes' => trim(($t->notes ? $t->notes . ' ' : '') . $note)]);
                }

                foreach ($parts as $x) {
                    DB::table('payment_moves')->insert([
                        'payment_id'     => $x['payment']->id,
                        'kk_reference'   => $code,
                        'amount'         => $x['amount'],
                        'from_ticket_id' => count($oldIds) === 1 ? $oldIds[0] : null,
                        'to_ticket_id'   => $x['ticket']->id,
                        'reason'         => mb_substr('Split' . ($fromList ? ' from ' . $fromList : '') . ': ' . $data['reason'], 0, 150),
                        'moved_by'       => $user->id,
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ]);
                }
                KkEvent::where('reference', $code)->update(['status' => 'matched']);

                $reopened = collect($oldIds)->filter(fn ($id) => ! in_array($id, $targetIds, true) && $tickets[$id]->fresh()->status === 'sent')
                    ->map(fn ($id) => '#' . $tickets[$id]->order_no)->join(', ');
                $lastPart = end($parts);

                return $code . ' (KSh ' . number_format($total) . ') is now on ' . $toList . '.'
                    . ($lastPart['ticket']->balance > 0.004 ? ' Bill #' . $lastPart['ticket']->order_no . ' still owes KSh ' . number_format($lastPart['ticket']->balance) . '.' : '')
                    . ($reopened ? ' Now unpaid: ' . $reopened . '.' : '');
            });
        } catch (RuntimeException $e) {
            return back()->withErrors(['move' => $e->getMessage()])->withInput();
        }

        return back()->with('ok', $message);
    }
}
