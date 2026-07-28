<?php

namespace App\Http\Controllers\Api\CAP;

use App\Http\Controllers\Controller;
use App\Models\CAP;
use App\Models\CAPAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CAPAttachmentController extends Controller
{
    public function store(Request $request, CAP $cap): JsonResponse
    {
        $this->assertOwnership($request, $cap);

        $validated = $request->validate([
            'file'          => ['required', 'file', 'max:10240'],
            'cap_update_id' => ['nullable', 'uuid', 'exists:cap_updates,id'],
        ]);

        $file     = $request->file('file');
        $fileName = $file->getClientOriginalName();
        $path     = $file->store("cap-evidence/{$cap->id}", 'local');

        $attachment = $cap->attachments()->create([
            'cap_update_id' => $validated['cap_update_id'] ?? null,
            'file_path'     => $path,
            'file_name'     => $fileName,
            'uploaded_by'   => $request->user()->id,
        ]);

        return response()->json(['success' => true, 'data' => $attachment->load('uploadedBy')], 201);
    }

    public function download(Request $request, CAPAttachment $attachment): mixed
    {
        $this->assertOwnership($request, $attachment->cap);

        $path = $attachment->file_path;
        if (!$path || !Storage::disk('local')->exists($path)) {
            return response()->json(['success' => false, 'message' => '文件不存在'], 404);
        }
        $fullPath = Storage::disk('local')->path($path);
        return response()->download($fullPath, $attachment->file_name ?? basename($path));
    }

    public function destroy(Request $request, CAPAttachment $attachment): JsonResponse
    {
        $this->assertOwnership($request, $attachment->cap);

        if ($attachment->file_path) {
            Storage::disk('local')->delete($attachment->file_path);
        }
        $attachment->delete();

        return response()->json(['success' => true, 'message' => '佐證文件已刪除']);
    }

    /**
     * 供應商帳號只能操作自己供應商底下的 CAP 附件；內部員工帳號（無 supplier_id）不受限制。
     */
    private function assertOwnership(Request $request, CAP $cap): void
    {
        $supplierId = $request->user()->supplier_id;
        if ($supplierId) {
            abort_if($cap->supplier_id !== $supplierId, 403, '無權操作此矯正行動的附件');
        }
    }
}
