<?php

namespace App\Services\Suppliers;

use App\Models\CAP;
use App\Models\GeoEvent;
use App\Models\RiskAssessment;
use App\Models\SAQ;

class SupplierTimelineService
{
    public function getTimeline(string $supplierId): array
    {
        return [
            'events'      => $this->getEvents($supplierId),
            'pending_saq' => $this->getPendingSaq($supplierId),
        ];
    }

    private function getEvents(string $supplierId): array
    {
        $saqs = SAQ::where('supplier_id', $supplierId)
            ->whereNotNull('score')
            ->with('project:id,name')
            ->get(['id', 'project_id', 'score', 'grade', 'status', 'submitted_at', 'created_at',
                   'dim_e1', 'dim_e2', 'dim_e3', 'dim_e4', 'dim_e5', 'dim_e6'])
            ->keyBy('id');

        $ras = RiskAssessment::where('supplier_id', $supplierId)
            ->orderBy('assessed_at', 'desc')
            ->get();

        $raIds = $ras->pluck('id');
        $capsByRa = CAP::where('source_type', 'risk_assessment')
            ->whereIn('source_id', $raIds)
            ->get(['id', 'source_id', 'status'])
            ->groupBy('source_id');

        // 預載地緣事件名稱
        $geoEventIds = $ras->where('source_type', 'geo_event')->pluck('source_id')->filter()->unique();
        $geoEvents = $geoEventIds->isNotEmpty()
            ? GeoEvent::whereIn('id', $geoEventIds)->pluck('name', 'id')
            : collect();

        $saqIdToRaId = $ras->whereNotNull('source_saq_id')
            ->pluck('id', 'source_saq_id');

        $events = [];

        foreach ($ras as $ra) {
            $sourceType = $ra->source_type ?? 'saq';
            $linkedSaq  = ($sourceType === 'saq' && $ra->source_saq_id)
                ? ($saqs->get($ra->source_saq_id) ?? null) : null;
            $caps = $capsByRa->get($ra->id, collect());

            $event = [
                'type'        => 'risk_assessment',
                'date'        => $ra->assessed_at?->toIso8601String(),
                'year'        => $ra->assessed_at?->year,
                'source_type' => $sourceType,
                'risk'        => [
                    'id'            => $ra->id,
                    'source_saq_id' => $ra->source_saq_id,
                    'six_dims'      => [
                        'E1' => $ra->dim_e1,
                        'E2' => $ra->dim_e2,
                        'E3' => $ra->dim_e3,
                        'E4' => $ra->dim_e4,
                        'E5' => $ra->dim_e5,
                        'E6' => $ra->dim_e6,
                    ],
                    'is_auto'     => $ra->source_saq_id !== null || $sourceType === 'geo_event',
                    'assessed_by' => $ra->assessed_by,
                    'notes'       => $ra->notes,
                ],
                'linked_saq' => $linkedSaq ? [
                    'id'          => $linkedSaq->id,
                    'score'       => $linkedSaq->score,
                    'grade'       => $linkedSaq->grade,
                    'submitted_at' => $linkedSaq->submitted_at?->toIso8601String(),
                ] : null,
                'caps' => $caps->map(fn($c) => [
                    'id'             => $c->id,
                    'status'         => $c->status,
                    'findings_count' => $c->findings()->count(),
                ])->values()->all(),
            ];

            if ($sourceType === 'geo_event') {
                $event['geo_event_name'] = $geoEvents->get($ra->source_id);
                $event['geo_event_id']   = $ra->source_id;
            }

            $events[] = $event;
        }

        foreach ($saqs as $saq) {
            $linkedRaId = $saqIdToRaId->get($saq->id);
            $events[] = [
                'type'   => 'saq_scored',
                'date'   => $saq->created_at?->toIso8601String(),
                'year'   => $saq->created_at?->year,
                'saq'    => [
                    'id'           => $saq->id,
                    'project_name' => $saq->project?->name,
                    'score'        => $saq->score,
                    'grade'        => $saq->grade,
                    'submitted_at' => $saq->submitted_at?->toIso8601String(),
                    'status'       => $saq->status,
                    'six_dims'     => [
                        'E1' => $saq->dim_e1,
                        'E2' => $saq->dim_e2,
                        'E3' => $saq->dim_e3,
                        'E4' => $saq->dim_e4,
                        'E5' => $saq->dim_e5,
                        'E6' => $saq->dim_e6,
                    ],
                ],
                'linked_ra' => $linkedRaId,
            ];
        }

        usort($events, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));

        return $events;
    }

    private function getPendingSaq(string $supplierId): ?array
    {
        $latest = SAQ::where('supplier_id', $supplierId)
            ->whereNull('score')
            ->whereIn('status', ['submitted', 'under_review'])
            ->orderBy('created_at', 'desc')
            ->first(['id', 'status', 'submitted_at', 'created_at']);

        if (!$latest) {
            return null;
        }

        return [
            'id'           => $latest->id,
            'status'       => $latest->status,
            'submitted_at' => $latest->submitted_at?->toIso8601String(),
        ];
    }
}
