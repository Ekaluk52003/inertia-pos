<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\QrCode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillOrderQrCodeIds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:backfill-qr-code-ids {--dry-run : Only show what would be changed}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill orders.qr_code_id by matching orders to QR codes by restaurant, table and created_at windows.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $this->info('Starting backfill of orders.qr_code_id'.($dry ? ' (dry-run)' : ''));

        $totalMatched = 0;
        $qrGrouped = QrCode::orderBy('restaurant_id')->orderBy('table_number')->orderBy('created_at')->get()
            ->groupBy(function ($q) {
                return $q->restaurant_id.'|'.$q->table_number;
            });

        foreach ($qrGrouped as $key => $qrList) {
            /** @var \Illuminate\Support\Collection $qrList */
            $qrList = $qrList->values();

            for ($i = 0; $i < $qrList->count(); $i++) {
                $qr = $qrList[$i];
                $start = $qr->created_at;
                $end = $i + 1 < $qrList->count() ? $qrList[$i + 1]->created_at : null;

                $query = Order::where('restaurant_id', $qr->restaurant_id)
                    ->whereNull('qr_code_id')
                    ->where('table_number', $qr->table_number)
                    ->where('created_at', '>=', $start);

                if ($end) {
                    $query->where('created_at', '<', $end);
                }

                $count = $query->count();
                if ($count === 0) {
                    continue;
                }

                $this->line("Found {$count} legacy orders matching QR id={$qr->id} (table={$qr->table_number}, restaurant={$qr->restaurant_id}) created_at >= {$start}" . ($end ? " and < {$end}" : ''));

                if (! $dry) {
                    DB::transaction(function () use ($query, $qr, &$totalMatched) {
                        $updated = $query->update(['qr_code_id' => $qr->id]);
                        $totalMatched += $updated;
                    });
                    $this->info("Assigned {$count} orders to qr_code_id={$qr->id}");
                }
            }
        }

        $this->info('Backfill complete'.($dry ? ' (dry-run, no changes made)' : ". totalAssigned={$totalMatched}"));

        return 0;
    }
}
