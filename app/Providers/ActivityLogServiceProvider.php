<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class ActivityLogServiceProvider extends ServiceProvider {

    public function boot() {
        // If spatie/laravel-activitylog is not installed, create a simple helper
        if (!function_exists('activity')) {
            // Create a simple activity helper function
            if (!function_exists('activity')) {

                function activity(?string $logName = null) {
                    return new class {

                        public function performedOn($model) {
                            return $this;
                        }

                        public function causedBy($causer) {
                            return $this;
                        }

                        public function log($description) {
                            // Simple logging - you can enhance this
                            \Log::info("Activity: {$description}");
                        }
                    };
                }

            }
        }
    }
}
