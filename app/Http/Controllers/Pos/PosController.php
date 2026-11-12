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
            'categories' => $this->menu(session('pos_channel', 'house')),
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
            ...(self::hasChannels() ? ['channel' => session('pos_channel') === 'glovo' ? 'glovo' : 'house'] : []),
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
            'categories' => $this->menu($ticket->channel ?? 'house'),
            'shift'      => Shift::currentFor($request->user()->id),
        ]);
    }

    public function addItem(Request $request, Ticket $ticket)
    {
        $this->authorizeTicket($request, $ticket);
        abort_unless($ticket->status === 'open', 403, 'Ticket is not open.');

        $data = $request->validate(['item_id' => 'required|exists:items,id']);
        $item = Item::findOrFail($data['item_id']);
        if (self::hasChannels() && ($item->category->channel ?? 'house') !== ($ticket->channel ?? 'house')) {
            return $request->ajax() ? response()->json(['error' => 'That dish is on the other menu.'], 422) : back()->withErrors(['item' => 'That dish is on the other menu (Dine-in / Glovo).']);
        }

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

        return $request->ajax() ? response()->noContent() : redirect()->route('pos.tickets.show', $ticket);
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

        return $request->ajax() ? response()->noContent() : redirect()->route('pos.tickets.show', $ticket);
    }

    public function sendToKitchen(Request $request, Ticket $ticket, Receipts $receipts)
    {
        $this->authorizeTicket($request, $ticket);
        abort_unless($ticket->status === 'open', 403);

        if ($ticket->items()->count() === 0) {
            return back()->withErrors(['send' => 'Ticket is empty.']);
        }

        DB::transaction(function () use ($ticket, $receipts) {
            $newLineIds = TicketItem::where('ticket_id', $ticket->id)->whereNull('sent_at')->pluck('id');
            TicketItem::where('ticket_id', $ticket->id)
                ->whereNull('sent_at')
                ->update(['sent_at' => now()]);
            $ticket->update(['status' => 'sent']);

            $ticket->load('items.item', 'opener');
            app(\App\Services\PrintRouter::class)->kot($ticket, $newLineIds->isNotEmpty() ? $ticket->items->whereIn('id', $newLineIds)->values() : null);
        });

        // One counter printer: the POS page opens the print dialog on reload with the kitchen ticket
        // (and, for "Send + print bill", the bill straight after it in the same print job).
        $mode = $request->boolean('print_bill') ? 'both' : 'kot';
        if ($mode === 'both' && app(\App\Services\PrintRouter::class)->hasNetwork('bill')) app(\App\Services\PrintRouter::class)->cashier($receipts->buildBill($ticket->fresh(['items.item', 'opener'])));
        return redirect()->route('pos.tickets.show', $ticket)
            ->with('ok', $mode === 'both' ? 'Sent to kitchen. Printing kitchen ticket and bill.' : 'Sent to kitchen. Printing kitchen ticket.')
            ->with('print_bill', ($bm = app(\App\Services\PrintRouter::class)->browserMode($mode)) ? route('pos.tickets.bill', [$ticket, 'mode' => $bm]) : null);
    }

    /** Browser-printed slips. mode: kot | both (kitchen ticket + bill) | bill | receipt (paid tickets). */
    public function printBill(Request $request, Ticket $ticket)
    {
        $this->authorizeTicket($request, $ticket);
        $mode = (string) $request->query('mode', 'bill');
        abort_unless(in_array($mode, ['kot', 'both', 'bill', 'receipt', 'cancel'], true), 404);
        if ($mode === 'cancel') {
            abort_unless($ticket->status === 'void', 403, 'Order is not cancelled.');
        } elseif ($mode === 'receipt') {
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

            app(\App\Services\PrintRouter::class)->cashier($receipts->buildReceipt($ticket->fresh('items.item', 'opener'), $payment), true);
        });

        $change = $data['cash_received'] - $total;
        return redirect()->route('pos.tickets.show', $ticket)->with('change', $change);
    }

    /** Cancel an order. Before the kitchen: the waiter's own call. After: reason + manager approval, and a CANCELLED slip prints. */
    public function voidTicket(Request $request, Ticket $ticket)
    {
        $this->authorizeTicket($request, $ticket);
        abort_unless(in_array($ticket->status, ['open', 'sent'], true), 403, 'This order can no longer be cancelled.');
        $user = $request->user();
        $sent = $ticket->status === 'sent';

        $data = $request->validate([
            'reason'      => [$sent ? 'required' : 'nullable', 'string', 'max:100'],
            'reason_note' => ['nullable', 'string', 'max:150'],
            'manager_pin' => ['nullable', 'string', 'max:20'],
        ], ['reason.required' => 'Choose why the order is being cancelled.']);

        $approver = null;
        if ($sent) {
            if ($user->role === 'manager') {
                $approver = $user;
            } else {
                $pin = (string) ($data['manager_pin'] ?? '');
                if ($pin !== '') {
                    $approver = \App\Models\User::where('role', 'manager')->where('active', true)->whereNotNull('pin_hash')->get()
                        ->first(fn ($m) => \Illuminate\Support\Facades\Hash::check($pin, $m->pin_hash));
                }
                if (! $approver) {
                    return back()->withErrors(['manager_pin' => 'A manager PIN is needed to cancel an order the kitchen already has.'])
                        ->withInput($request->except('manager_pin'))->with('void_open', true);
                }
            }
        }

        $reason = trim(($data['reason'] ?? '') . (! empty($data['reason_note']) ? ': ' . $data['reason_note'] : ''));
        $fields = ['status' => 'void', 'closed_at' => now()];
        if (\Illuminate\Support\Facades\Schema::hasColumn('tickets', 'void_reason')) {
            $fields += [
                'void_reason'      => $reason !== '' ? $reason : 'Cancelled before it went to the kitchen',
                'voided_by'        => $user->id,
                'void_approved_by' => $approver?->id,
            ];
        }
        $ticket->update($fields);

        $done = redirect()->route('pos.index')->with('ok', 'Order #' . str_pad($ticket->id, 5, '0', STR_PAD_LEFT) . ' cancelled.');
        if ($sent) {
            $done->with('print_bill', route('pos.tickets.bill', [$ticket, 'mode' => 'cancel']));
        }
        return $done;
    }

    /** Dine-in / Glovo switch: picks the menu for the next order, and switches an open order that has no dishes yet. */
    public function setChannel(Request $request)
    {
        $data = $request->validate(['channel' => ['required', 'in:house,glovo'], 'ticket_id' => ['nullable', 'integer']]);
        $request->session()->put('pos_channel', $data['channel']);
        if (! empty($data['ticket_id']) && self::hasChannels() && ($t = Ticket::find($data['ticket_id']))) {
            $this->authorizeTicket($request, $t);
            if ($t->status === 'open' && $t->items()->count() === 0) {
                $t->update(['channel' => $data['channel']]);
            } elseif (($t->channel ?? 'house') !== $data['channel']) {
                return back()->withErrors(['channel' => 'This order already has dishes. Finish or cancel it to switch menus.']);
            }
        }
        return back();
    }

    private static function hasChannels(): bool
    {
        static $has = null;
        return $has ??= \Illuminate\Support\Facades\Schema::hasColumn('tickets', 'channel');
    }

    private function menu(string $channel = 'house')
    {
        return Category::where('active', true)
            ->when(self::hasChannels(), fn ($q) => $q->where('channel', $channel))
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
