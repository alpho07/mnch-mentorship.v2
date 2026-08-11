<?php

namespace App\Jobs;

use App\Models\DatabaseRestore;
use App\Services\DatabaseBackupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RestoreDatabaseJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1000;

    public function __construct(
        public int $restoreId,
    ) {}

    public function handle(DatabaseBackupService $service): void
    {
        $service->runRestore(DatabaseRestore::findOrFail($this->restoreId));
    }
}
