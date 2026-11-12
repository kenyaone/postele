<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Printer;
use App\Models\PrintJob;
use App\Services\PrintRouter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class PrintersController extends Controller
{
    public function index()
    {
        abort_unless(\Illuminate\Support\Facades\Schema::hasTable('printers'), 503, 'Printers are being set up. Try again in a few minutes.');
        $printers = Printer::withCount(['jobs as printed_today' => fn ($q) => $q->where('status', 'sent')->where('created_at', '>=', today())])
            ->orderByDesc('active')->orderBy('name')->get();
        $active = $printers->where('active', true);
        $kot = $active->filter(fn ($p) => $p->doesKot());
        $bill = $active->filter(fn ($p) => $p->doesBill());
        $stations = Item::where('active', 1)->distinct()->pluck('station')->filter()->values();
        $uncovered = $kot->isEmpty() ? collect() : $stations->reject(fn ($s) => $kot->contains(fn ($p) => $p->handles($s)))->values();
        $network = $active->where('connection', 'network');
        $seen = Cache::get('print_bridge_seen');
        $seenAt = $seen ? Carbon::createFromTimestamp($seen)->setTimezone(config('app.timezone')) : null;
        $bridgeOk = $seenAt && $seenAt->gt(now()->subMinutes(2));
        $waiting = PrintJob::whereNotNull('printer_id')->where('status', 'pending')->where('created_at', '>=', now()->subMinutes(15))->count();
        $failed = PrintJob::whereNotNull('printer_id')->where('status', 'failed')->where('created_at', '>=', today())->count();

        return view('manage.printers', compact('printers', 'kot', 'bill', 'stations', 'uncovered', 'network', 'seenAt', 'bridgeOk', 'waiting', 'failed'));
    }

    public function store(Request $request)
    {
        $p = Printer::create($this->fields($request));
        return redirect()->route('manage.printers')->with('ok', "Printer \"{$p->name}\" added." . ($p->connection === 'network' ? ' Tap Test print to check it.' : ''));
    }

    public function update(Request $request, Printer $printer)
    {
        $printer->update($this->fields($request));
        return redirect()->route('manage.printers')->with('ok', "Printer \"{$printer->name}\" saved.");
    }

    public function destroy(Printer $printer)
    {
        $printer->delete();
        return redirect()->route('manage.printers')->with('ok', "Printer \"{$printer->name}\" removed.");
    }

    public function test(Printer $printer, PrintRouter $router)
    {
        if ($printer->connection !== 'network') {
            return back()->with('ok', 'This printer prints through the device print dialog: use the test page button.');
        }
        $router->test($printer);
        return back()->with('ok', "Test print queued for \"{$printer->name}\". It prints within a few seconds while the print bridge is running.");
    }

    public function testPage()
    {
        return view('manage.printer-test');
    }

    /** The bridge program with this cafe's address and key filled in (managers only). */
    public function bridge(string $kind)
    {
        $file = $kind === 'windows' ? 'tele-print-bridge.cmd' : 'tele-print-bridge.py';
        $body = str_replace(
            ['__API__', '__TOKEN__'],
            [url('/api/print'), (string) config('pos.bridge_token')],
            file_get_contents(resource_path('bridge/' . $file))
        );
        if ($kind === 'windows') $body = str_replace("\n", "\r\n", str_replace("\r\n", "\n", $body));
        return response($body, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . $file . '"',
            'Cache-Control' => 'no-store',
        ]);
    }

    private function fields(Request $request): array
    {
        $d = $request->validate([
            'name' => 'required|string|max:60',
            'role' => 'required|in:both,bill,kot',
            'connection' => 'required|in:network,device',
            'ip' => 'nullable|required_if:connection,network|ip',
            'port' => 'nullable|integer|between:1,65535',
            'stations' => 'nullable|array',
            'stations.*' => 'in:' . implode(',', array_keys(Printer::STATIONS)),
        ], ['ip.required_if' => 'Type the printer\'s IP address (printed on its self-test page).']);
        $network = $d['connection'] === 'network';
        $kot = in_array($d['role'], ['kot', 'both'], true);
        return [
            'name' => $d['name'],
            'role' => $d['role'],
            'connection' => $d['connection'],
            'ip' => $network ? $d['ip'] : null,
            'port' => $network ? ($d['port'] ?? 9100) : 9100,
            'stations' => $kot && ! empty($d['stations']) && count($d['stations']) < count(Printer::STATIONS) ? array_values($d['stations']) : null,
            'auto_receipt' => $network && $d['role'] !== 'kot' && $request->boolean('auto_receipt'),
            'active' => $request->boolean('active'),
        ];
    }
}
