<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Ticket;

class Receipts
{
    private const WIDTH = 32;

    public function buildKot(Ticket $ticket): string
    {
        $e = (new EscPos())->init()
            ->align('center')->bold(true)->size(2, 2)
            ->line('*** KITCHEN ***')
            ->size(1, 1)->bold(false)
            ->line('Ticket #' . str_pad((string) $ticket->id, 5, '0', STR_PAD_LEFT))
            ->line($ticket->opened_at?->format('d M Y H:i') ?? '')
            ->line('By: ' . ($ticket->opener->name ?? '?'))
            ->line(str_repeat('-', self::WIDTH))
            ->align('left')->bold(true)->size(1, 2);

        foreach ($ticket->items as $line) {
            $e->line($line->qty . ' x ' . $line->item->name);
        }

        if ($ticket->notes) {
            $e->size(1, 1)->bold(false)
              ->line(str_repeat('-', self::WIDTH))
              ->line('Notes: ' . $ticket->notes);
        }

        return $e->size(1, 1)->bold(false)->feed(4)->cut()->get();
    }

    public function buildReceipt(Ticket $ticket, Payment $payment): string
    {
        $cafe  = config('pos.cafe_name');
        $till  = config('pos.till_number');
        $phone = config('pos.phone');
        $wa    = config('pos.thank_you_wa');

        $e = (new EscPos())->init()
            ->align('center')->bold(true)->size(2, 2)
            ->line($cafe)
            ->size(1, 1)->bold(false)
            ->line("Till $till  \u{00B7}  $phone")
            ->line(str_repeat('-', self::WIDTH))
            ->align('left')
            ->line('Ticket #' . str_pad((string) $ticket->id, 5, '0', STR_PAD_LEFT))
            ->line($ticket->opened_at?->format('d M Y H:i') ?? '')
            ->line('Cashier: ' . ($ticket->opener->name ?? '?'))
            ->line(str_repeat('-', self::WIDTH));

        $total = 0.0;
        foreach ($ticket->items as $line) {
            $sub = (float) $line->unit_price * (int) $line->qty;
            $total += $sub;
            $label = $line->qty . ' x ' . $line->item->name;
            if (strlen($label) > 22) $label = substr($label, 0, 22);
            $amt  = number_format($sub, 2);
            $pad  = self::WIDTH - strlen($label) - strlen($amt);
            $e->line($label . str_repeat(' ', max(1, $pad)) . $amt);
        }

        $e->line(str_repeat('-', self::WIDTH));

        $totalStr = 'KSh ' . number_format($total, 2);
        $pad = self::WIDTH - strlen('TOTAL') - strlen($totalStr);
        $e->bold(true)->size(1, 2)
          ->line('TOTAL' . str_repeat(' ', max(1, $pad)) . $totalStr)
          ->size(1, 1)->bold(false);

        $methodLabel = match ($payment->method) {
            'cash'           => 'Cash',
            'kk_stk'         => 'M-Pesa STK',
            'kk_till_auto',
            'kk_till_manual' => 'M-Pesa Till',
            default          => $payment->method,
        };
        $ref = $payment->kk_reference ? ' (' . $payment->kk_reference . ')' : '';
        $e->line('Paid: ' . $methodLabel . $ref);

        return $e->line(str_repeat('-', self::WIDTH))
            ->align('center')
            ->line('Thank you! Order again:')
            ->line($wa)
            ->feed(4)->cut()->get();
    }
}
