<?php

namespace App\Providers;

use App\Models\Icd10Code;
use App\Models\Icd9cmCode;
use App\Policies\IcdReferencePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());

        Gate::policy(Icd10Code::class, IcdReferencePolicy::class);
        Gate::policy(Icd9cmCode::class, IcdReferencePolicy::class);

        Vite::prefetch(concurrency: 3);
    }
}
