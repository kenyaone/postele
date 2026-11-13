<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\CreditSale;
use App\Models\Customer;
use App\Models\Ticket;
use App\Services\PaymentSettler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Close a bill on credit: a named customer (new or existing), or Glovo's account for Glovo orders. */
class CreditController extends Controller
{
    public function store(Request $request, Ticket $ticket, PaymentSettler $settler)
    {
        $user = $request->user();
        abort_unless($user->role === 'manager' || $ticket->opened_by === $user->id, 403, 'Not your ticket.');
        abort_unless($ticket->status === 'sent', 403, 'Send the order to the kitchen first.');
        $ticket->load('items');

        if (($ticket->channel ?? 'house') === 'glovo') {
            $customer = Customer::where('is_platform', true)->where('name', 'Glovo')->firstOrFail();
            $due = null;
        } else {
            $data = $request->validate([
                'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
                'name'        => ['required_without:customer_id', 'nullable', 'string', 'max:120'],
                'phone'       => ['required_without:customer_id', 'nullable', 'string', 'max:20', 'regex:/^\+?[0-9 ]{9,15}$/'],
                'due_on'      => ['nullable', 'date', 'after_or_equal:today'],
            ], [
                'name.required_without'  => 'Pick a customer or type the new customer\'s name.',
                'phone.required_without' => 'A new credit customer needs a phone number, so they can be reached to pay.',
                'phone.regex'            => 'Type a real phone number, e.g. 0712 345 678.',
            ]);
            $vague = 'Credit is only for a named customer who will pay later (a person or an office). Walk-in and table customers pay now by cash or M-Pesa.';
            if (! empty($data['customer_id'])) {
                $customer = Customer::findOrFail($data['customer_id']);
                if (Customer::isPlaceholderName($customer->name)) return back()->withInput()->withErrors(['credit' => $vague]);
            } else {
                $name = trim(preg_replace('/\s+/', ' ', $data['name']));
                if (Customer::isPlaceholderName($name)) return back()->withInput()->withErrors(['name' => $vague]);
                $customer = Customer::where('is_platform', false)->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first()
                    ?? Customer::create(['name' => $name, 'phone' => ($data['phone'] ?? '') !== '' ? $data['phone'] : null]);
            }
            $due = $data['due_on'] ?? null;
        }

        $total = round((float) $ticket->total, 2);
        DB::transaction(function () use ($ticket, $settler, $customer, $total, $due, $user) {
            $settler->settle($ticket, 'credit', $total, null, $user->id);
            CreditSale::create(['ticket_id' => $ticket->id, 'customer_id' => $customer->id, 'amount' => $total, 'due_on' => $due, 'recorded_by' => $user->id]);
        });

        return redirect()->route('pos.tickets.show', $ticket)
            ->with('ok', 'KSh ' . number_format($total) . ' put on ' . $customer->name . '\'s account.');
    }
}
