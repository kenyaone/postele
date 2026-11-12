<?php

namespace App\Services;

use App\Models\Printer;
use App\Models\PrintJob;
use App\Models\Ticket;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Decides which printer gets which slip. Network printers get a queued job that the
 * print bridge (on a computer at the cafe) sends over WiFi. With no network printer for
 * a slip, things work as before: the device's own print dialog.
 */
class PrintRouter
{
    private ?Collection $net = null;

    public function __construct(private Receipts $receipts) {}

    private function network(): Collection
    {
        return $this->net ??= Schema::hasTable('printers')
            ? Printer::where('active', true)->where('connection', 'network')->whereNotNull('ip')->get()
            : collect();
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
            PrintJob::create(['printer' => 'kitchen', 'payload_bytes' => $this->receipts->buildKot($ticket), 'status' => 'pending']);
            return;
        }
        foreach ($this->network()->filter(fn ($p) => $p->doesKot()) as $p) {
            $mine = $lines->filter(fn ($l) => $p->handles($l->item->station ?? null))->values();
            if ($mine->isEmpty()) continue;
            $t = clone $ticket;
            $t->setRelation('items', $mine);
            PrintJob::create(['printer' => 'kitchen', 'printer_id' => $p->id, 'payload_bytes' => $this->receipts->buildKot($t), 'status' => 'pending']);
        }
    }

    /** Bills and shift reports; $autoReceipt = the receipt printed by itself after payment. */
    public function cashier(string $payload, bool $autoReceipt = false): void
    {
        if (! $this->hasNetwork('bill')) {
            PrintJob::create(['printer' => 'cashier', 'payload_bytes' => $payload, 'status' => 'pending']);
            return;
        }
        $printers = $this->network()->filter(fn ($p) => $p->doesBill() && (! $autoReceipt || $p->auto_receipt));
        foreach ($printers as $p) {
            PrintJob::create(['printer' => 'cashier', 'printer_id' => $p->id, 'payload_bytes' => $payload, 'status' => 'pending']);
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
            ->line('Address: ' . $p->ip . ':' . $p->port)->line('Time:    ' . now()->format('d M Y H:i:s'))
            ->line(str_repeat('-', 32))->align('center')->line('If you can read this,')->line('the printer is working.')
            ->feed(4)->cut();
        PrintJob::create(['printer' => $p->doesKot() && ! $p->doesBill() ? 'kitchen' : 'cashier', 'printer_id' => $p->id, 'payload_bytes' => $e->get(), 'status' => 'pending']);
    }
}
