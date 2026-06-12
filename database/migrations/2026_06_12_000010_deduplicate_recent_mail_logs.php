<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $seen = [];
        $deleteIds = [];

        DB::table('mail_logs')
            ->select([
                'id',
                'mailable',
                'subject',
                'to_email',
                'to_name',
                'status',
                'error_message',
                'sent_at',
            ])
            ->whereIn('status', ['sent', 'blocked'])
            ->where('created_at', '>=', now()->subDays(2))
            ->orderBy('id')
            ->chunkById(500, function ($logs) use (&$seen, &$deleteIds) {
                foreach ($logs as $log) {
                    $key = implode('|', [
                        $log->mailable ?? '',
                        $log->subject ?? '',
                        $log->to_email ?? '',
                        $log->to_name ?? '',
                        $log->status ?? '',
                        $log->error_message ?? '',
                        (string) $log->sent_at,
                    ]);

                    if (isset($seen[$key])) {
                        $deleteIds[] = $log->id;
                        continue;
                    }

                    $seen[$key] = true;
                }
            });

        foreach (array_chunk($deleteIds, 500) as $chunk) {
            DB::table('mail_logs')->whereIn('id', $chunk)->delete();
        }
    }

    public function down(): void
    {
        // Los registros duplicados son bitacora redundante; no se restauran en rollback.
    }
};
