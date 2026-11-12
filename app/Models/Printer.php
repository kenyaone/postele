<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Printer extends Model
{
    public const STATIONS = ['kitchen' => 'Kitchen', 'cashier' => 'Counter / drinks'];
    public const ROLES = ['both' => 'Bills + kitchen (KOT)', 'bill' => 'Bills & receipts only', 'kot' => 'Kitchen tickets (KOT) only'];
    public const CONNECTIONS = ['usb' => 'USB on the counter PC', 'network' => 'WiFi / network cable', 'device' => 'Bluetooth / USB on a phone'];

    protected $fillable = ['name', 'role', 'connection', 'ip', 'windows_name', 'port', 'stations', 'auto_receipt', 'active'];
    protected $casts = ['stations' => 'array', 'auto_receipt' => 'boolean', 'active' => 'boolean', 'port' => 'integer'];

    public function doesKot(): bool { return in_array($this->role, ['kot', 'both'], true); }
    public function doesBill(): bool { return in_array($this->role, ['bill', 'both'], true); }

    /** Printed by the bridge at the cafe (prints by itself, wherever the waiter is). */
    public function viaBridge(): bool { return in_array($this->getAttribute('connection'), ['network', 'usb'], true); }

    public function address(): string
    {
        return $this->getAttribute('connection') === 'usb' ? 'Windows: ' . $this->windows_name : $this->ip . ':' . $this->port;
    }

    /** Does this KOT printer take dishes from that station? No stations ticked = all dishes. */
    public function handles(?string $station): bool
    {
        return empty($this->stations) || in_array($station ?? 'kitchen', $this->stations, true);
    }

    public function jobs() { return $this->hasMany(PrintJob::class); }
}
