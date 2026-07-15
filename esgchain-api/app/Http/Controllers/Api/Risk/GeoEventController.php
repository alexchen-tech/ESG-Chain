<?php

namespace App\Http\Controllers\Api\Risk;

use App\Http\Controllers\Controller;
use App\Models\GeoEvent;
use App\Models\GeoEventSupplierReview;
use App\Services\Risk\GeoEventService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GeoEventController extends Controller
{
    public function __construct(private GeoEventService $service) {}

    public function index(Request $request)
    {
        $events = GeoEvent::withCount(['supplierReviews', 'supplierReviews as done_count' => function ($q) {
            $q->where('status', 'done');
        }])
            ->orderByDesc('occurred_at')
            ->paginate(20);

        return response()->json($events);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'                        => 'required|string|max:255',
            'event_type'                  => 'required|string|max:50',
            'affected_scope'              => 'required|array',
            'affected_scope.country_codes' => 'required|array',
            'severity'                    => 'required|in:low,medium,high,critical',
            'occurred_at'                 => 'required|date',
        ]);

        $event = $this->service->create($validated, $request->user()?->id);

        return response()->json($event->load('supplierReviews'), 201);
    }

    public function show(GeoEvent $geoEvent)
    {
        $geoEvent->loadCount(['supplierReviews', 'supplierReviews as done_count' => function ($q) {
            $q->where('status', 'done');
        }]);

        return response()->json($geoEvent);
    }

    public function reviews(GeoEvent $geoEvent)
    {
        $reviews = GeoEventSupplierReview::where('geo_event_id', $geoEvent->id)
            ->with('supplier:id,name,code,country_code')
            ->orderBy('created_at')
            ->paginate(20);

        return response()->json($reviews);
    }

    public function recalculate(GeoEvent $geoEvent)
    {
        $this->service->dispatchRecalculation($geoEvent);

        return response()->json(['message' => '批次重算已排程'], 202);
    }

    public function reviewCallback(Request $request, GeoEvent $geoEvent)
    {
        $validated = $request->validate([
            'results'                  => 'required|array',
            'results.*.supplier_id'    => 'required|string',
            'results.*.dim_e4'         => 'nullable|numeric',
        ]);

        $this->service->handleReviewCallback($geoEvent, $validated['results']);

        return response()->json(['message' => 'ok']);
    }
}
