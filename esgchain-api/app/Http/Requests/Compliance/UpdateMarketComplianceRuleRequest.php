<?php

namespace App\Http\Requests\Compliance;

use App\Models\MarketComplianceRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMarketComplianceRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ruleId = $this->route('market_compliance_rule')?->id;

        return [
            'market'         => ['sometimes', 'string', Rule::in(MarketComplianceRule::MARKETS)],
            'doc_type'       => ['sometimes', 'string', 'max:50',
                                  Rule::unique('market_compliance_rules')
                                      ->where(fn ($q) => $q->where('market', $this->input('market', $this->route('market_compliance_rule')?->market)))
                                      ->ignore($ruleId)],
            'program' => ['sometimes', Rule::in(MarketComplianceRule::PROGRAMS)],
            'scope' => ['sometimes', 'in:material,product'],
            'is_mandatory'   => ['boolean'],
            'effective_from' => ['sometimes', 'date'],
            'notes'          => ['nullable', 'string'],
            'is_active'      => ['boolean'],
        ];
    }
}
