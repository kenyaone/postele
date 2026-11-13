<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\KkEvent;
use App\Models\Payment;
use App\Models\StkRequest;
use App\Models\Ticket;
use App\Services\KopoKopo;
use App\Services\PaymentSettler;
use Illuminate\Http\Request;
use RuntimeException;

class MpesaController extends Controller
{
    public function stk(Request $request, Ticket $ticket, KopoKopo $kk)
    {
        $this->authorizeTicket($request, $ticket);
        abort_unless(in_array($ticket->status, ['open', 'sent'], true), 403, 'Ticket is closed.');

        $data  = $request->validate(['phone' => 'required|string|max:20']);
        $phone = KopoKopo::normalizePhone($data['phone']);
        if (! $phone) return back()->withErrors(['phone' => 'Enter a mobile number like 0712 345 678.']);
        if (! KopoKopo::ready()) return back()->withErrors(['phone' => 'M-Pesa prompts are not switched on yet. Match a till payment or enter the code by hand.']);

        $ticket->load('items');
        $amount = (int) ceil($ticket->total);
        $stk = StkRequest::create(['ticket_id' => $ticket->id, 'phone' => $phone, 'amount' => $amount, 'status' => 'pending', 'requested_by' => $request->user()->id]);

        try {
            $location = $kk->stkPush($stk);
            $stk->update(['location' => $location, 'kk_id' => basename((string) parse_url($location, PHP_URL_PATH))]);
        } catch (\Throwable $e) {
            report($e);
            $stk->update(['status' => 'failed', 'error' => 'Could not reach Kopo Kopo.']);
            return back()->withErrors(['phone' => 'Could not send the M-Pesa prompt. Try again, or use another way to pay.']);
        }

        return redirect()->route('pos.tickets.show', $ticket)
            ->with('ok', 'M-Pesa prompt for KSh ' . number_format($amount) . ' sent to ' . $data['phone'] . '. Ask the customer to enter their PIN.');
    }

    public function stkStatus(Request $request, Ticket $ticket)
    {
        $this->authorizeTicket($request, $ticket);
        $s = StkRequest::where('ticket_id', $ticket->id)->latest('id')->first();
        return response()->json(['ticket' => $ticket->fresh()->status, 'stk' => $s?->status, 'error' => $s?->error]);
    }

    public function match(Request $request, Ticket $ticket, PaymentSettler $settler)
    {
        $this->authorizeTicket($request, $ticket);
        $data  = $request->validate(['event_id' => 'required|integer']);
        $event = KkEvent::where('id', $data['event_id'])->where('event_type', 'buygoods_transaction_received')->where('status', 'pending')->first();
        if (! $event) return back()->withErrors(['mpesa' => 'That M-Pesa payment is no longer available. It may already be matched.']);

        $ticket->load('items');
        if ((float) $event->amount + 0.004 < $ticket->total) {
            return back()->withErrors(['mpesa' => 'KSh ' . number_format($event->amount, 2) . ' is less than the bill of KSh ' . number_format($ticket->total, 2) . '.']);
        }
        try {
            $settler->settle($ticket, 'kk_till_auto', (float) $event->amount, $event->reference, $request->user()->id);
        } catch (RuntimeException $e) {
            return back()->withErrors(['mpesa' => $e->getMessage()]);
        }
        $event->update(['status' => 'matched']);
        return redirect()->route('pos.tickets.show', $ticket)->with('ok', "M-Pesa {$event->reference} matched. Bill paid.");
    }

    public function manual(Request $request, Ticket $ticket, PaymentSettler $settler)
    {
        $this->authorizeTicket($request, $ticket);
        $data = $request->validate([
            'code'   => ['required', 'regex:/^[A-Za-z0-9]{8,12}$/'],
        ], ['code.regex' => 'An M-Pesa code is 10 letters and numbers, e.g. QK12ABC3XY.']);
        $code = strtoupper($data['code']);

        $ticket->load('items');

        // A typed code is only accepted once Kopo Kopo has actually received that payment.
        $event = KkEvent::where('event_type', 'buygoods_transaction_received')->where('reference', $code)->latest('id')->first();
        if (! $event) {
            return back()->withInput()->withErrors(['mpesa' => "Kopo Kopo has not received M-Pesa {$code} on Till " . config('pos.till_number') . ' yet. Check the code on the customer\'s SMS, wait a minute and try again. The bill stays unpaid until the money arrives.']);
        }
        if ($event->status !== 'pending') {
            return back()->withInput()->withErrors(['mpesa' => "M-Pesa {$code} has already been used for another bill."]);
        }
        if ((float) $event->amount + 0.004 < $ticket->total) {
            return back()->withInput()->withErrors(['mpesa' => "M-Pesa {$code} was KSh " . number_format((float) $event->amount) . ', less than the bill of KSh ' . number_format($ticket->total) . '.']);
        }
        try {
            $settler->settle($ticket, 'kk_till_auto', (float) $event->amount, $code, $request->user()->id);
        } catch (RuntimeException $e) {
            return back()->withErrors(['mpesa' => $e->getMessage()]);
        }
        $event->update(['status' => 'matched']);
        return redirect()->route('pos.tickets.show', $ticket)->with('ok', "M-Pesa {$code} confirmed by Kopo Kopo. Bill paid.");
    }

    private function authorizeTicket(Request $request, Ticket $ticket): void
    {
        if ($request->user()->role === 'manager') return;
        abort_unless($ticket->opened_by === $request->user()->id, 403, 'Not your ticket.');
    }
}
