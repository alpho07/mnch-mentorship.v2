<?php

namespace App\Providers;

use App\Models\{
    MonthlyReport,
    ApprovedTrainingArea,
    Training
};
use App\Policies\{
    MonthlyReportPolicy,
    ApprovedTrainingAreaPolicy,
    MentorshipTrainingPolicy
};
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider {

    protected $policies = [
        //MonthlyReport::class => MonthlyReportPolicy::class,
       // ApprovedTrainingArea::class => ApprovedTrainingAreaPolicy::class,
       // Training::class => MentorshipTrainingPolicy::class,
            // Add other model policies here...
    ];

    public function boot(): void {
       // $this->registerPolicies();
    }
}
