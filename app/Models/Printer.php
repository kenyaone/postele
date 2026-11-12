<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Printer extends Model
{
    public const STATIONS = ['kitchen' => 'Kitchen', 'cashier' => 'Counter / drinks'];
    public const ROLES = ['both' => 'Bills + kitchen (KOT)', 'bill' => 'Bills & receipts only', 'kot' => 'Kitchen tickets (KOT) only'];

    protected $fillable = ['name', 'role', 'connection', 'ip', 'port', 'stations', 'auto_receipt', 'active'];
    protected $casts = ['stations' => 'array', 'auto_receipt' => 'boolean', 'active' => 'boolean', 'port' => 'integer'];

    public function doesKot(): bool { return in_array($this->role, ['kot', 'both'], true); }
    public function doesBill(): bool { return in_array($this->role, ['bill', 'both'], true); }

    /** Does this KOT printer take dishes from that station? No stations ticked = all dishes. */
    public function handles(?string $station): bool
    {
        return empty($this->stations) || in_array($station ?? 'kitchen', $this->stations, true);
    }

    public function jobs() { return $this->hasMany(PrintJob::class); }
}
