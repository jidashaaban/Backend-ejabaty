<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CleanupBookings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookings:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove course bookings that were not paid within 24 hours';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // 1. Calculate the cutoff time (24 hours ago)
        $expirationTime = Carbon::now()->subHours(24);

        // 2. Delete rows from user_course where status is still pending and time is up
        $deletedCount = DB::table('user_course')
            ->where('status', 'pending_payment')
            ->where('booked_at', '<', $expirationTime)
            ->delete();

        $this->info("Cleaned up $deletedCount expired bookings.");
    }
    
}
