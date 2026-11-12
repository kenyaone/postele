<?php

namespace App\Services;

use App\Models\Printer;
use App\Models\PrintJob;
use App\Models\Ticket;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Decides which printer gets which slip. Bridge printers (WiFi/network, or USB on the counter PC)
 * get a queued job that the print bridge prints at the cafe, wherever the waiter is. With no bridge
 * printer for a slip, things work as before: the device's own print dialog.
 */
class PrintRouter
{
    private ?Collection $net = null;
    private static ?bool $linked = null;

    public function __construct(private Receipts $receipts) {}

    private function network(): Collection
    {
        return $this->net ??= Schema::hasTable('printers')
            ? Printer::where('active', true)->whereIn('connection', ['network', 'usb'])->get()
                ->filter(fn ($p) => $p->connection === 'usb' ? (bool) $p->windows_name : (bool) $p->ip)->values()
            : collect();
    }

    private function job(string $printer, ?Printer $p, string $payload, ?int $ticketId, ?string $kind): void
    {
        $row = ['printer' => $printer, 'payload_bytes' => $payload, 'status' => 'pending'];
        if ($p) $row['printer_id'] = $p->id;
        if (self::$linked ??= Schema::hasColumn('print_jobs', 'ticket_id')) $row += ['ticket_id' => $ticketId, 'kind' => $kind];
        PrintJob::create($row);
    }

    public function hasNetwork(string $what): bool
    {
        return $this->network()->contains(fn ($p) => $what === 'kot' ? $p->doesKot() : $p->doesBill());
    }

    /** Kitchen tickets, split by station so each printer only gets its own dishes. */
    public function kot(Ticket $ticket, ?Collection $lines = null): void
    {
        $lines ??= $ticket->items;
        if (! $this->hasNetwork('kot')) {
            $this->job('kitchen', null, $this->receipts->buildKot($ticket), $ticket->id, 'kot');
            return;
        }
        foreach ($this->network()->filter(fn ($p) => $p->doesKot()) as $p) {
            $mine = $lines->filter(fn ($l) => $p->handles($l->item->station ?? null))->values();
            if ($mine->isEmpty()) continue;
            $t = clone $ticket;
            $t->setRelation('items', $mine);
            $this->job('kitchen', $p, $this->receipts->buildKot($t), $ticket->id, 'kot');
        }
    }

    /** Bills, receipts and shift reports; $autoReceipt = the receipt printed by itself after payment. */
    public function cashier(string $payload, bool $autoReceipt = false, ?int $ticketId = null, ?string $kind = null): void
    {
        $kind ??= $autoReceipt ? 'receipt' : 'bill';
        if (! $this->hasNetwork('bill')) {
            $this->job('cashier', null, $payload, $ticketId, $kind);
            return;
        }
        foreach ($this->network()->filter(fn ($p) => $p->doesBill() && (! $autoReceipt || $p->auto_receipt)) as $p) {
            $this->job('cashier', $p, $payload, $ticketId, $kind);
        }
    }

    /** What the device still has to print itself after "Send to kitchen": kot | bill | both | null. */
    public function browserMode(string $mode): ?string
    {
        $kot = ! $this->hasNetwork('kot');
        $bill = $mode === 'both' && ! $this->hasNetwork('bill');
        return $kot && $bill ? 'both' : ($kot ? 'kot' : ($bill ? 'bill' : null));
    }

    public function test(Printer $p): void
    {
        $e = (new EscPos())->init()->align('center')->bold(true)->size(2, 2)->line('TEST PRINT')
            ->size(1, 1)->bold(false)->line(config('pos.cafe_name', 'Tele Cafe'))->line(str_repeat('-', 32))
            ->align('left')->line('Printer: ' . $p->name)->line('Role:    ' . (Printer::ROLES[$p->role] ?? $p->role))
            ->line('Address: ' . $p->address())->line('Time:    ' . now()->format('d M Y H:i:s'))
            ->line(str_repeat('-', 32))->align('center')->line('If you can read this,')->line('the printer is working.')
            ->feed(4)->cut();
        $this->job($p->doesKot() && ! $p->doesBill() ? 'kitchen' : 'cashier', $p, $e->get(), null, 'test');
    }
}
