<?php

namespace App\Notifications;

use App\Models\CAP;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * 站內未讀提示（database channel）。email 通知另外由 NotifyCapAssignedJob 用
 * CapAssignedMail 寄送並包 try/catch——本機/測試環境常沒有可用的 SMTP relay，
 * 若與站內通知放同一個 via() 清單，mail channel 丟例外會連帶讓 database
 * channel 的寫入也一起失敗（並在佇列重試時重複建立通知列）。
 */
class CapAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly CAP $cap) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'cap_id'   => $this->cap->id,
            'title'    => $this->cap->title,
            'priority' => $this->cap->priority,
            'due_date' => $this->cap->due_date?->toDateString(),
            'message'  => '您有一筆新的矯正行動待處理',
        ];
    }
}
