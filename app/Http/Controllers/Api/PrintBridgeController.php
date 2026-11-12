<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PrintJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrintBridgeController extends Controller
{
    public function pending(): JsonResponse
    {
        $jobs = PrintJob::where('status', 'pending')
            ->where('tries', '<', 3)
            ->orderBy('id')
            ->limit(20)
            ->get(['id', 'printer', 'payload_bytes', 'tries', 'created_at']);

        return response()->json([
            'jobs' => $jobs->map(fn ($j) => [
                'id'          => $j->id,
                'printer'     => $j->printer,
                'payload_b64' => base64_encode($j->payload_bytes),
                'tries'       => $j->tries,
                'created_at'  => $j->created_at->toIso8601String(),
            ])->all(),
        ]);
    }

    public function update(Request $request, PrintJob $printJob): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:sent,failed_attempt',
            'error'  => 'nullable|string|max:500',
        ]);

        if ($data['status'] === 'sent') {
            $printJob->update([
                'status'  => 'sent',
                'sent_at' => now(),
                'error'   => null,
            ]);
        } else {
            $newTries = $printJob->tries + 1;
            $printJob->update([
                'tries'  => $newTries,
                'error'  => $data['error'] ?? 'unknown',
                'status' => $newTries >= 3 ? 'failed' : 'pending',
            ]);
        }

        return response()->json(['ok' => true, 'status' => $printJob->fresh()->status]);
    }
}
