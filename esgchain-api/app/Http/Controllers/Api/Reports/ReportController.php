<?php

namespace App\Http\Controllers\Api\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;

class ReportController extends Controller
{
    private string $aiUrl;

    public function __construct()
    {
        $this->aiUrl = config('services.ai.url', env('AI_SERVICE_URL', 'http://esgchain-ai:8000'));
    }

    /**
     * GET /reports/scope3?year=2024 — Scope 3 排放彙總（代理 FastAPI）
     */
    public function scope3(Request $request): JsonResponse
    {
        $request->validate(['year' => ['required', 'integer', 'min:2000', 'max:2100']]);

        $bearerToken = $request->bearerToken();

        $response = Http::timeout(30)
            ->withToken($bearerToken)
            ->get("{$this->aiUrl}/ai/v1/reports/scope3", ['year' => $request->integer('year')]);

        if (!$response->successful()) {
            return response()->json([
                'success'    => false,
                'error_code' => 'SERVER_ERROR',
                'message'    => '無法取得報告資料',
            ], 500);
        }

        return response()->json($response->json());
    }

    /**
     * GET /reports/scope3/export?year=2024&format=xlsx|pdf
     */
    public function exportScope3(Request $request): mixed
    {
        $request->validate([
            'year'   => ['required', 'integer'],
            'format' => ['required', 'string', 'in:xlsx,pdf'],
        ]);

        $year   = $request->integer('year');
        $format = $request->input('format');

        // 取 Scope 3 資料
        $reportData = $this->fetchScope3Data($request->bearerToken(), $year);

        return match ($format) {
            'xlsx' => $this->generateXlsx($reportData, $year),
            'pdf'  => $this->generatePdf($reportData, $year),
        };
    }

    private function fetchScope3Data(string $token, int $year): array
    {
        $response = Http::timeout(30)
            ->withToken($token)
            ->get("{$this->aiUrl}/ai/v1/reports/scope3", ['year' => $year]);

        return $response->successful() ? ($response->json('data') ?? []) : [];
    }

    private function generateXlsx(array $data, int $year): Response
    {
        $categories = $data['categories'] ?? [];
        $totalCo2e  = $data['total_co2e'] ?? 0;

        // 簡版 CSV 格式（正式版需安裝 maatwebsite/excel）
        $lines   = ["ESG·Chain Scope 3 排放報告 - {$year} 年度\r\n"];
        $lines[] = "總排放量（kgCO2e）,{$totalCo2e}\r\n";
        $lines[] = "\r\n";
        $lines[] = "Category #,類別名稱,排放量（kgCO2e）\r\n";

        foreach ($categories as $cat) {
            $lines[] = "{$cat['category_number']},{$cat['category_name']},{$cat['co2e']}\r\n";
        }

        $csv = implode('', $lines);

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=scope3_{$year}.csv",
        ]);
    }

    private function generatePdf(array $data, int $year): Response
    {
        $categories = $data['categories'] ?? [];
        $totalCo2e  = $data['total_co2e'] ?? 0;

        // 簡版 HTML → PDF（正式版需安裝 barryvdh/laravel-dompdf）
        $rows = '';
        foreach ($categories as $cat) {
            $rows .= "<tr><td>{$cat['category_number']}</td><td>{$cat['category_name']}</td><td>{$cat['co2e']}</td></tr>";
        }

        $html = "<!DOCTYPE html><html><head><meta charset='UTF-8'>
<style>body{font-family:Arial;} table{width:100%;border-collapse:collapse;} th,td{border:1px solid #ccc;padding:6px;}</style>
</head><body>
<h2>ESG·Chain Scope 3 排放報告 — {$year} 年度</h2>
<p>總排放量：<strong>{$totalCo2e} kgCO2e</strong></p>
<table><thead><tr><th>Category #</th><th>類別名稱</th><th>排放量（kgCO2e）</th></tr></thead>
<tbody>{$rows}</tbody></table>
</body></html>";

        return response($html, 200, [
            'Content-Type'        => 'text/html; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=scope3_{$year}.html",
        ]);
    }
}
