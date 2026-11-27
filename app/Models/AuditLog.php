<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'username',
        'full_name',
        'action',
        'entity_type',
        'entity_id',
        'entity_label',
        'details_json',
        'ip_address',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function details(): array
    {
        return json_decode($this->details_json ?? '{}', true) ?: [];
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            'create' => 'إنشاء',
            'update' => 'تعديل',
            'delete' => 'حذف (سلة المحذوفات)',
            'restore' => 'استعادة',
            'force_delete' => 'حذف نهائي',
            'approve' => 'اعتماد',
            'reject' => 'رفض',
            'batch_approve' => 'اعتماد جماعي',
            'batch_reject' => 'رفض جماعي',
            'submit' => 'إرسال للمراجعة',
            'login' => 'تسجيل دخول',
            'logout' => 'تسجيل خروج',
            'password_change' => 'تغيير كلمة المرور',
            'clone_year' => 'نسخ خطة سنة سابقة',
            'period_toggle' => 'فتح/إغلاق فترة الإدخال',
            'backup' => 'نسخ احتياطي',
            'restore_backup' => 'استعادة نسخة احتياطية',
            'export' => 'تصدير تقرير',
            'restore_version' => 'استعادة إصدار سابق',
            'renumber' => 'إعادة ترقيم تلقائي',
            default => $this->action,
        };
    }
}
