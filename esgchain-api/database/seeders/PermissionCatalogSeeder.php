<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * 權限目錄 + role_has_permissions seed。
 *
 * 依 `crud-permission-granularity` 稽核，把 `role-permission-management` 階段收斂出的 18 個
 * `module.manage` 統包權限，依 HTTP method 語意（GET→view、POST→create、PUT/PATCH→update、DELETE→delete）
 * 拆到 CRUD 四動作粒度（見 openspec/changes/crud-permission-granularity/design.md Decision 1）。
 * 同一模組同一動作若橫跨多條路由但角色白名單完全相同，仍合併為一個權限字串。
 *
 * 命名格式：`模組.動作` 或 `模組.子模組.動作`。
 *
 * admin 角色不透過本 seeder 的 role_has_permissions 管理：
 * admin 在 EnsurePermission middleware 中一律視為擁有全部權限（見 User::hasPermissionTo 呼叫前的短路判斷）。
 */
class PermissionCatalogSeeder extends Seeder
{
    /**
     * 權限目錄：key = 權限字串，value = 說明（供角色管理 UI 顯示用）
     */
    public const CATALOG = [
        // 使用者/角色管理
        'users.view' => '使用者帳號查看',
        'users.create' => '使用者帳號建立（含啟用/停用、重設密碼）',
        'users.update' => '使用者角色指派',
        'suppliers.manage-users.create' => '供應商入口帳號建立',

        // SAQ / 評核系列 / 問卷專案（同一批路由角色白名單相同，合併保留 saqs.review 前綴）
        'saqs.review.view' => 'SAQ 審核／評核系列／問卷專案查看（覆核紀錄、計分快照、預填提示）',
        'saqs.review.create' => 'SAQ 審核／評核系列／問卷專案建立與動作型端點（開始/完成/退回/標記已審、再審、定案、送出、關閉、封存）',
        'saqs.review.update' => 'SAQ 審核／評核系列／問卷專案更新（權重、計分設定）',
        'saqs.review.delete' => 'SAQ 問卷專案刪除',

        // CAP
        'caps.create' => 'CAP 矯正行動建立（含附件上傳）',
        'caps.update' => 'CAP 矯正行動更新',
        'caps.delete' => 'CAP 矯正行動刪除',

        // 系統設定
        'settings.org-units.create' => '組織單位建立',
        'settings.org-units.update' => '組織單位更新',
        'settings.org-units.delete' => '組織單位刪除',

        'settings.questionnaire-templates.create' => '問卷範本建立（含複製、解除封存、題目新增、匯入題庫）',
        'settings.questionnaire-templates.update' => '問卷範本更新（含題目更新、排序）',
        'settings.questionnaire-templates.delete' => '問卷範本刪除（含題目刪除）',
        'settings.questionnaire-templates.publish' => '問卷範本發布/封存',

        'settings.supplier-groups.create' => '供應商群組建立',
        'settings.supplier-groups.update' => '供應商群組更新',
        'settings.supplier-groups.delete' => '供應商群組刪除',

        'settings.question-bank.create' => '題庫建立（含標籤指派）',
        'settings.question-bank.update' => '題庫更新',
        'settings.question-bank.delete' => '題庫刪除（含標籤移除）',

        'settings.dim-weight-defaults.update' => '維度權重預設值更新',

        'settings.tag-library.create' => '標籤庫建立（含棄用/還原）',
        'settings.tag-library.update' => '標籤庫更新（含 L2 節點權重）',

        'settings.scoring-models.create' => '計分模型建立',
        'settings.scoring-models.update' => '計分模型更新',
        'settings.scoring-models.delete' => '計分模型刪除',

        'settings.carbon-price.update' => '碳價假設更新',

        'settings.country-risk.view' => '國家風險評等查看',
        'settings.country-risk.create' => '國家風險評等建立',
        'settings.country-risk.update' => '國家風險評等更新',

        'market-compliance-rules.create' => '市場合規規則建立',
        'market-compliance-rules.update' => '市場合規規則更新',
        'market-compliance-rules.delete' => '市場合規規則刪除',

        'market-definitions.create' => '目標市場定義建立',
        'market-definitions.update' => '目標市場定義更新',
        'market-definitions.delete' => '目標市場定義刪除',
    ];

    /**
     * 角色 → 權限清單。與拆分前（role-permission-management 完成時）等價：
     * - buyer：caps.manage → caps.create/update/delete
     * - sustain：saqs.review + caps.manage + settings.country-risk.manage → 對應四組拆分後字串
     * - comply：saqs.review + caps.manage → 對應拆分後字串
     * - analyst：saqs.review → 對應拆分後字串
     */
    public const ROLE_PERMISSIONS = [
        'buyer' => [
            'caps.create',
            'caps.update',
            'caps.delete',
        ],
        'sustain' => [
            'saqs.review.view',
            'saqs.review.create',
            'saqs.review.update',
            'saqs.review.delete',
            'caps.create',
            'caps.update',
            'caps.delete',
            'settings.country-risk.view',
            'settings.country-risk.create',
            'settings.country-risk.update',
        ],
        'comply' => [
            'saqs.review.view',
            'saqs.review.create',
            'saqs.review.update',
            'saqs.review.delete',
            'caps.create',
            'caps.update',
            'caps.delete',
        ],
        'analyst' => [
            'saqs.review.view',
            'saqs.review.create',
            'saqs.review.update',
            'saqs.review.delete',
        ],
    ];

    public function run(): void
    {
        foreach (array_keys(self::CATALOG) as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'api',
            ]);
        }

        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'api']);
            $role->syncPermissions($permissions);
        }

        // admin 角色本身不透過此機制管理權限（見類別註解），但仍確保 admin role 記錄存在，
        // 供 PermissionController::rolePermissions 顯示「全部權限（固定）」時查得到角色。
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api']);
    }
}
