<?php

namespace Aster255\MakerChecker\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Aster255\MakerChecker\Enums\RequestStatuses;
use Aster255\MakerChecker\Models\MakerCheckerRequest;

class ExpireOverduePendingRequests extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'expire-overdue-requests';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Identify and expire all overdue pending requests.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $expirationInMinutes = config('makerchecker.request_expiration_in_minutes');

        if (!$expirationInMinutes) {
            $this->error('A value needs to be set for the `request_expiration_in_minutes` configuration for this command to be effected');

            return 0;
        }

        MakerCheckerRequest::where('status', RequestStatuses::PENDING)
            ->where('created_at', '<=', Carbon::now()->subMinutes($expirationInMinutes))
            ->update(['status' => RequestStatuses::EXPIRED]);

        $this->info('Pending requests marked as expired successfully.');

        return 0;
    }
}
