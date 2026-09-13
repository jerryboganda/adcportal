<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DicomNode extends Model
{
    protected $table = 'ris_dicom_nodes';

    protected $fillable = [
        'node_name',
        'ae_title',
        'ip_address',
        'port',
        'modality_code',
        'is_worklist_scp',
        'is_storage_scp',
        'status',
        'last_ping_time',
        'last_ping_latency_ms',
        'business_id',
    ];

    protected $casts = [
        'is_worklist_scp' => 'boolean',
        'is_storage_scp' => 'boolean',
    ];

    /**
     * Probe TCP reachability of the DICOM node. This is a plain socket check,
     * NOT a DICOM C-ECHO handshake — callers must label it accordingly.
     */
    public function probe(int $timeoutSeconds = 3): array
    {
        $start = microtime(true);
        $connected = @fsockopen($this->ip_address, (int) $this->port, $errNo, $errStr, $timeoutSeconds);
        $latency = (int) round((microtime(true) - $start) * 1000);

        if (is_resource($connected)) {
            fclose($connected);
            $this->forceFill([
                'status' => 'online',
                'last_ping_time' => now()->format('d M, h:i A'),
                'last_ping_latency_ms' => $latency,
            ])->save();

            return ['status' => 'online', 'latency' => $latency];
        }

        $this->forceFill(['status' => 'unreachable'])->save();

        return ['status' => 'unreachable', 'latency' => null, 'error' => $errStr];
    }
}
