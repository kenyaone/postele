<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Item;
use App\Models\Payment;
use App\Models\PrintJob;
use App\Models\Shift;
use App\Models\Ticket;
use App\Models\TicketItem;
use App\Services\Receipts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PosController extends Controller
{
    public function index(Request $request)
    {
        $user  = $request->user();
        $shift = Shift::currentFor($user->id);

        if ($user->role !== 'manager' && ! $shift) {
            return redirect()->route('pos.shift.openForm');
        }

        $current = Ticket::where('opened_by', $user->id)
            ->whereIn('status', ['open', 'sent'])
            ->latest('opened_at')->first();

        if ($current) {
            return redirect()->route('pos.tickets.show', $current);
        }

        return view('pos.index', [
            'ticket'     => null,
            'categories' => $this->menu(),
            'shift'      => $shift,
        ]);
    }

    public function startTicket(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'manager' && ! Shift::currentFor($user->id)) {
            return redirect()->route('pos.shift.openForm');
        }

        $ticket = Ticket::create([
            'opened_by' => $user->id,
            'status'    => 'open',
            'opened_at' => now(),
        ]);
        return redirect()->route('pos.tickets.show', $ticket);
    }

    public function show(Request $request, Ticket $ticket)
    {
        $this->authorizeTicket($request, $ticket);
        $ticket->load(['items.item', 'opener']);

        return view('pos.index', [
            'ticket'     => $ticket,
            'categories' => $this->menu(),
            'shift'      => Shift::currentFor($request->user()->id),
        ]);
    }

    public function addItem(Request $request, Ticket $ticket)
    {
        $this->authorizeTicket($request, $ticket);
        abort_unless($ticket->status === 'open', 403, 'Ticket is not open.');

        $data = $request->validate(['item_id' => 'required|exists:items,id']);
        $item = Item::findOrFail($data['item_id']);

        $line = $ticket->items()->where('item_id', $item->id)->whereNull('sent_at')->first();
        if ($line) {
            $line->increment('qty');
        } else {
            $ticket->items()->create([
                'item_id'    => $item->id,
                'qty'        => 1,
                'unit_price' => $item->price,
            ]);
        }

        return redirect()->route('pos.tickets.show', $ticket);
    }

    public function updateLine(Request $request, Ticket $ticket, TicketItem $ticketItem)
    {
        $this->authorizeTicket($request, $ticket);
        abort_unless($ticketItem->ticket_id === $ticket->id, 404);
        abort_unless($ticket->status === 'open', 403, 'Ticket is not open.');

        $data = $request->validate(['qty' => 'required|integer|min:0|max:999']);
        $qty = (int) $data['qty'];

        if ($qty === 0) {
            $ticketItem->delete();
        } else {
            $ticketItem->update(['qty' => $qty]);
        }

        return redirect()->route('pos.tickets.show', $ticket);
    }

    public function sendToKitchen(Request $request, Ticket $ticket, Receipts $receipts)
    {
        $this->authorizeTicket($request, $ticket);
        abort_unless($ticket->status === 'open', 403);

        if ($ticket->items()->count() === 0) {
            return back()->withErrors(['send' => 'Ticket is empty.']);
        }

        DB::transaction(function () use ($ticket, $receipts) {
            TicketItem::where('ticket_id', $ticket->id)
                ->whereNull('sent_at')
                ->update(['sent_at' => now()]);
            $ticket->update(['status' => 'sent']);

            $ticket->load('items.item', 'opener');
            PrintJob::create([
                'printer'       => 'kitchen',
                'payload_bytes' => $receipts->buildKot($ticket),
                'status'        => 'pending',
            ]);
        });

        // One counter printer: the POS page opens the print dialog on reload with the kitchen ticket
        // (and, for "Send + print bill", the bill straight after it in the same print job).
        $mode = $request->boolean('print_bill') ? 'both' : 'kot';
        return redirect()->route('pos.tickets.show', $ticket)
            ->with('ok', $mode === 'both' ? 'Sent to kitchen. Printing kitchen ticket and bill.' : 'Sent to kitchen. Printing kitchen ticket.')
            ->with('print_bill', route('pos.tickets.bill', [$ticket, 'mode' => $mode]));
    }

    /** Browser-printed slips. mode: kot | both (kitchen ticket + bill) | bill | receipt (paid tickets). */
    public function printBill(Request $request, Ticket $ticket)
    {
        $this->authorizeTicket($request, $ticket);
        $mode = (string) $request->query('mode', 'bill');
        abort_unless(in_array($mode, ['kot', 'both', 'bill', 'receipt'], true), 404);
        if ($mode === 'receipt') {
            abort_unless($ticket->status === 'paid', 403, 'Ticket is not paid yet.');
        } else {
            abort_unless(in_array($ticket->status, ['open', 'sent'], true), 403, 'Ticket already closed.');
        }

        $ticket->load('items.item', 'opener');
        abort_if($ticket->items->isEmpty(), 422, 'Ticket is empty.');

        return view('pos.bill', [
            'ticket'  => $ticket,
            'mode'    => $mode,
            'payment' => $mode === 'receipt' ? $ticket->payments()->latest('id')->first() : null,
        ]);
    }

    public function closeBill(Request $request, Ticket $ticket, Receipts $receipts)
    {
        $this->authorizeTicket($request, $ticket);
        abort_unless($ticket->status === 'sent', 403, 'Send to kitchen first.');

        $data = $request->validate(['cash_received' => 'required|numeric|min:0']);

        $ticket->load('items.item', 'opener');
        $total = $ticket->total;

        if ($data['cash_received'] < $total) {
            return back()->withErrors(['cash_received' => 'Cash received (KSh ' . number_format($data['cash_received'], 2) . ') is less than total (KSh ' . number_format($total, 2) . ').']);
        }

        DB::transaction(function () use ($ticket, $total, $receipts) {
            $payment = Payment::create([
                'ticket_id'   => $ticket->id,
                'method'      => 'cash',
                'amount'      => $total,
                'received_at' => now(),
                'matched_at'  => now(),
                'matched_by'  => auth()->id(),
            ]);

            $ticket->update(['status' => 'paid', 'closed_at' => now()]);

            PrintJob::create([
                'printer'       => 'cashier',
                'payload_bytes' => $receipts->buildReceipt($ticket->fresh('items.item', 'opener'), $payment),
                'status'        => 'pending',
            ]);
        });

        $change = $data['cash_received'] - $total;
        return redirect()->route('pos.tickets.show', $ticket)->with('change', $change);
    }

    public function voidTicket(Request $request, Ticket $ticket)
    {
        $this->authorizeTicket($request, $ticket);
        abort_unless(in_array($ticket->status, ['open', 'sent'], true), 403);
        $ticket->update(['status' => 'void', 'closed_at' => now()]);
        return redirect()->route('pos.index')->with('ok', 'Ticket #' . $ticket->id . ' voided.');
    }

    private function menu()
    {
        return Category::where('active', true)
            ->with(['items' => fn ($q) => $q->where('active', true)->orderBy('name')])
            ->orderBy('sort_order')->orderBy('name')->get();
    }

    private function authorizeTicket(Request $request, Ticket $ticket): void
    {
        $user = $request->user();
        if ($user->role === 'manager') return;
        abort_unless($ticket->opened_by === $user->id, 403, 'Not your ticket.');
    }
}
