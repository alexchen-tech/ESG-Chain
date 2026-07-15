<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 補齊 ISO26000 範本 bank questions 的 iso26k.* TAG 指派（15 道題）。
 */
return new class extends Migration
{
    // [ bank_question_id => tag_slug ]
    private array $mappings = [
        'c721fad5-ac3a-4488-887c-f7b0a0d2cc07' => 'iso26k.env.resource',
        '7d17e497-6741-47ce-8166-dd386cb688e5' => 'iso26k.labor.conditions',
        '73182038-3771-4ad2-857f-c16362c122d3' => 'iso26k.labor.conditions',
        'af9de116-6b9b-4dfe-937b-4b1f8919df2e' => 'iso26k.env.resource',
        'b223605c-5e6f-4717-8c9e-58cb3ae7dece' => 'iso26k.gov.disclosure',
        '2c8d8bc9-3839-48e6-8bd2-16af0fe4a1b9' => 'iso26k.gov.stakeholder',
        '59fc3424-68a1-4505-9408-a66b4fec6992' => 'iso26k.consumer.data_privacy',
        '78f3a6bd-2402-4dc1-a449-0dd592cb3442' => 'iso26k.env.climate',
        '44655e5a-69a9-43cf-b3dd-38ef83180112' => 'iso26k.env.resource',
        '7f343a67-975c-41f8-a02d-422df0b4c241' => 'iso26k.env.resource',
        'bbabf03c-cc37-4d41-970e-07a5ca105e86' => 'iso26k.labor.social_dialogue',
        '4dcd7499-44cf-4229-8b4c-52ee12153f37' => 'iso26k.env.climate',
        '6c1c9e58-468b-4eed-b2e0-6f175db42ec5' => 'iso26k.labor.conditions',
        '10d078e0-a4fe-4948-86db-c51703ca36d6' => 'iso26k.env.resource',
        'a29f203d-3ef3-4510-bb67-5e6b9fa59434' => 'iso26k.gov.disclosure',
    ];

    public function up(): void
    {
        foreach ($this->mappings as $questionId => $tagSlug) {
            $tagId = DB::table('question_tags')->where('slug', $tagSlug)->value('id');
            if (!$tagId) continue;

            DB::table('question_tag_assignments')->insertOrIgnore([
                'question_id' => $questionId,
                'tag_id'      => $tagId,
            ]);
        }
    }

    public function down(): void
    {
        foreach ($this->mappings as $questionId => $tagSlug) {
            $tagId = DB::table('question_tags')->where('slug', $tagSlug)->value('id');
            if (!$tagId) continue;

            DB::table('question_tag_assignments')
                ->where('question_id', $questionId)
                ->where('tag_id', $tagId)
                ->delete();
        }
    }
};
