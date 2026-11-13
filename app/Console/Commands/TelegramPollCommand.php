<?php

namespace App\Console\Commands;

use App\Services\ForexAutoMonitorService;
use Illuminate\Console\Command;

class TelegramPollCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:poll {--once : Jalankan 1 siklus saja}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Worker background untuk polling tombol Telegram Approve/Reject secara real-time';

    /**
     * Execute the console command.
     */
    public function handle(ForexAutoMonitorService $monitor): int
    {
        $this->info('Telegram Poll Worker aktif...');

        if ($this->option('once')) {
            $processed = $monitor->pollTelegramCallbacks();
            $this->info("Selesai (processed: {$processed})");
            return 0;
        }

        while (true) {
            try {
                $monitor->pollTelegramCallbacks();
            } catch (\Throwable $e) {
                // Jangan crash jika ada timeout jaringan sementara
            }
            sleep(2);
        }

        return 0;
    }
}

