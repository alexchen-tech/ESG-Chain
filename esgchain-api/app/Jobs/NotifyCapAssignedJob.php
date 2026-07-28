<?php

namespace App\Jobs;

use App\Mail\CapAssignedMail;
use App\Models\CAP;
use App\Models\User;
use App\Notifications\CapAssignedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * CAP 建立（手動或自動生成）後，通知該供應商底下所有帳號。
 */
class NotifyCapAssignedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(private readonly string $capId) {}

    public function handle(): void
    {
        $cap = CAP::find($this->capId);
        if (!$cap) {
            Log::warning('NotifyCapAssignedJob 找不到對應 CAP', ['cap_id' => $this->capId]);
            return;
        }

        $recipients = User::where('supplier_id', $cap->supplier_id)->get();
        if ($recipients->isEmpty()) {
            Log::warning('NotifyCapAssignedJob 找不到供應商帳號可通知', ['supplier_id' => $cap->supplier_id, 'cap_id' => $cap->id]);
            return;
        }

        foreach ($recipients as $user) {
            $user->notify(new CapAssignedNotification($cap));

            if ($user->email) {
                try {
                    Mail::to($user->email)->send(new CapAssignedMail($cap));
                } catch (\Throwable $e) {
                    // 本機/測試環境常沒有可用的 SMTP relay；email 失敗不影響站內通知已寫入，僅記錄
                    Log::warning('CAP email 通知寄送失敗', ['cap_id' => $cap->id, 'user_id' => $user->id, 'error' => $e->getMessage()]);
                }
            }
        }
    }
}
