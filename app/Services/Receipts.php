<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Shift;
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

    public function buildBill(Ticket $ticket): string
    {
        return $this->buildInvoice($ticket, null);
    }

    public function buildReceipt(Ticket $ticket, Payment $payment): string
    {
        return $this->buildInvoice($ticket, $payment);
    }

    private function buildInvoice(Ticket $ticket, ?Payment $payment): string
    {
        $cafe  = config('pos.cafe_name');
        $till  = config('pos.till_number');
        $phone = config('pos.phone');
        $wa    = config('pos.thank_you_wa');
        $isBill = $payment === null;

        $e = (new EscPos())->init()
            ->align('center')->bold(true)->size(2, 2)
            ->line($cafe)
            ->size(1, 1)->bold(false)
            ->line("Till $till  \u{00B7}  $phone")
            ->line(str_repeat('-', self::WIDTH));

        if ($isBill) {
            $e->bold(true)->line("BILL \u{2014} PLEASE PAY")->bold(false);
            $e->line(str_repeat('-', self::WIDTH));
        }

        $e->align('left')
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

        if (! $isBill) {
            $methodLabel = match ($payment->method) {
                'cash'           => 'Cash',
                'kk_stk'         => 'M-Pesa STK',
                'kk_till_auto',
                'kk_till_manual' => 'M-Pesa Till',
                default          => $payment->method,
            };
            $ref = $payment->kk_reference ? ' (' . $payment->kk_reference . ')' : '';
            $e->line('Paid: ' . $methodLabel . $ref);
        }

        $e->line(str_repeat('-', self::WIDTH))->align('center');

        if ($isBill) {
            $e->line('Pay at the counter.')
              ->line('M-Pesa Till ' . $till)
              ->line('(Not a receipt)');
        } else {
            $e->line('Thank you! Order again:')
              ->line($wa);
        }

        return $e->feed(4)->cut()->get();
    }

    public function buildShiftReport(Shift $shift, array $d, bool $isZ): string
    {
        $cafe = config('pos.cafe_name');

        $e = (new EscPos())->init()
            ->align('center')->bold(true)->size(2, 2)
            ->line($isZ ? 'Z REPORT' : 'X REPORT')
            ->size(1, 1)->bold(false)
            ->line($cafe)
            ->line(str_repeat('-', self::WIDTH))
            ->align('left')
            ->line('Cashier: ' . ($shift->user->name ?? '?'))
            ->line('Opened:  ' . $shift->opened_at->format('d M Y H:i'))
            ->line($shift->closed_at
                ? 'Closed:  ' . $shift->closed_at->format('d M Y H:i')
                : '(shift ongoing)')
            ->line(str_repeat('-', self::WIDTH))
            ->line('TICKETS: ' . $d['ticketsPaid'] . ' paid, ' . $d['ticketsVoid'] . ' void')
            ->line(str_repeat('-', self::WIDTH))
            ->bold(true)->line('BY CATEGORY')->bold(false);

        foreach ($d['byCategory'] as $cat => $total) {
            $e->line($this->padLine(substr($cat, 0, 22), number_format($total, 2)));
        }

        $e->line(str_repeat('-', self::WIDTH))
          ->bold(true)->size(1, 2)
          ->line($this->padLine('TOTAL', number_format($d['totalSales'], 2)))
          ->size(1, 1)->bold(false)
          ->line(str_repeat('-', self::WIDTH))
          ->bold(true)->line('BY METHOD')->bold(false);

        $methodLabels = [
            'cash'           => 'Cash',
            'kk_stk'         => 'M-Pesa STK',
            'kk_till_auto'   => 'M-Pesa Till auto',
            'kk_till_manual' => 'M-Pesa Till man',
        ];
        foreach ($methodLabels as $k => $label) {
            $amt = (float) ($d['byMethod'][$k] ?? 0);
            $e->line($this->padLine($label, number_format($amt, 2)));
        }

        $e->line(str_repeat('-', self::WIDTH))
          ->bold(true)->line('CASH RECONCILE')->bold(false)
          ->line($this->padLine('Opening', number_format((float) $shift->cash_opening, 2)))
          ->line($this->padLine('+ Cash sales', number_format($d['cashSales'], 2)))
          ->line($this->padLine('= Expected', number_format($d['cashExpected'], 2)));

        if ($d['cashDeclared'] !== null) {
            $sign = $d['variance'] < 0 ? '-' : '+';
            $e->line($this->padLine('Declared', number_format($d['cashDeclared'], 2)))
              ->bold(true)
              ->line($this->padLine('Variance', $sign . number_format(abs($d['variance']), 2)))
              ->bold(false);
        }

        if ($d['kkUnmatched'] > 0) {
            $e->line(str_repeat('-', self::WIDTH))
              ->bold(true)->line('KK UNMATCHED: ' . $d['kkUnmatched'])->bold(false);
        }

        return $e->feed(4)->cut()->get();
    }

    private function padLine(string $label, string $val): string
    {
        $pad = self::WIDTH - strlen($label) - strlen($val);
        return $label . str_repeat(' ', max(1, $pad)) . $val;
    }
}
