<?php

namespace App\Services\Settings;

use App\Models\SasbRequiredTopic;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SasbRequiredTopicService
{
    public function getAll(): array
    {
        $rows = DB::table('sasb_required_topics as srt')
            ->leftJoin('question_tags as qt', 'qt.slug', '=', 'srt.tag_slug')
            ->select(
                'srt.id',
                'srt.sasb_industry_code',
                'srt.tag_slug',
                'srt.rationale',
                'qt.label_zh',
                'srt.created_at',
            )
            ->orderBy('srt.sasb_industry_code')
            ->orderBy('srt.tag_slug')
            ->get();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row->sasb_industry_code][] = [
                'id'           => $row->id,
                'tag_slug'     => $row->tag_slug,
                'label_zh'     => $row->label_zh,
                'rationale'    => $row->rationale,
            ];
        }

        return $grouped;
    }

    public function create(array $data): SasbRequiredTopic
    {
        $exists = SasbRequiredTopic::where('sasb_industry_code', $data['sasb_industry_code'])
            ->where('tag_slug', $data['tag_slug'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'tag_slug' => ['該 SASB 代碼已有相同 tag_slug 的必調設定'],
            ]);
        }

        return SasbRequiredTopic::create($data);
    }

    public function delete(string $id): void
    {
        SasbRequiredTopic::findOrFail($id)->delete();
    }

    /**
     * 取得特定 SASB 代碼的所有必調 tag slug 集合
     */
    public function getRequiredSlugsForCode(string $sasbIndustryCode): array
    {
        return SasbRequiredTopic::where('sasb_industry_code', $sasbIndustryCode)
            ->pluck('tag_slug')
            ->toArray();
    }
}
