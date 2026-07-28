<?php

namespace App\Http\Requests\Compliance;

use App\Models\MarketComplianceRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMarketComplianceRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'market'         => ['required', 'string', Rule::in(MarketComplianceRule::MARKETS)],
            'doc_type'       => ['required', 'string', 'max:50',
                                  Rule::unique('market_compliance_rules')->where(fn ($q) =>
                                      $q->where('market', $this->input('market'))
                                  )],
            'program' => ['sometimes', Rule::in(MarketComplianceRule::PROGRAMS)],
            'scope' => ['sometimes', 'in:material,product'],
            'is_mandatory'   => ['boolean'],
            'effective_from' => ['required', 'date'],
            'notes'          => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'doc_type.unique' => '此市場已有相同文件類型的規則。',
        ];
    }
}
