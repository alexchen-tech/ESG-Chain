<x-mail::message>
# 新的矯正行動待處理

系統為您的公司建立了一筆新的矯正行動（CAP），請盡快處理。

**標題**：{{ $cap->title }}
**優先級**：{{ $cap->priority }}
@if($cap->due_date)
**到期日**：{{ $cap->due_date->toDateString() }}
@endif

<x-mail::button :url="$url">
查看矯正行動
</x-mail::button>

如有疑問請聯繫您的窗口人員。

{{ config('app.name') }}
</x-mail::message>
