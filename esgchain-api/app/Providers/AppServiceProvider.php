<?php

namespace App\Providers;

use App\Models\BomLineSupplier;
use App\Models\MaterialItemEmission;
use App\Models\PcfSnapshot;
use App\Models\RiskAssessment;
use App\Models\Supplier;
use App\Models\SupplierComplianceDoc;
use App\Models\TradeGood;
use App\Observers\BomLineSupplierObserver;
use App\Observers\MaterialItemEmissionObserver;
use App\Observers\PcfSnapshotPathRiskObserver;
use App\Observers\RiskAssessmentObserver;
use App\Observers\SupplierComplianceDocPathRiskObserver;
use App\Observers\SupplierObserver;
use App\Observers\TradeGoodObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        TradeGood::observe(TradeGoodObserver::class);
        BomLineSupplier::observe(BomLineSupplierObserver::class);
        MaterialItemEmission::observe(MaterialItemEmissionObserver::class);
        RiskAssessment::observe(RiskAssessmentObserver::class);
        Supplier::observe(SupplierObserver::class);
        PcfSnapshot::observe(PcfSnapshotPathRiskObserver::class);
        SupplierComplianceDoc::observe(SupplierComplianceDocPathRiskObserver::class);
    }
}
