<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use App\Services\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function __construct(private BackupService $backupService)
    {
    }

    public function index(): View
    {
        $backups = $this->backupService->listBackups();
        $dbDriver = config('database.default');
        return view('backups.index', compact('backups', 'dbDriver'));
    }

    public function create(): RedirectResponse
    {
        $filename = $this->backupService->createBackup();
        AuditService::log('backup', 'system', null, $filename);
        return back()->with('success', "تم إنشاء نسخة احتياطية كاملة بنجاح: {$filename}");
    }

    public function download(string $filename): BinaryFileResponse
    {
        $safe = basename($filename);
        $path = $this->backupService->backupDirectory() . DIRECTORY_SEPARATOR . $safe;
        if (!file_exists($path) || !str_ends_with($safe, '.zip')) {
            abort(404);
        }
        return response()->download($path, $safe);
    }

    public function restore(Request $request): RedirectResponse
    {
        if ($request->hasFile('backup_file')) {
            $request->validate([
                'backup_file' => ['required', 'file', 'mimes:zip', 'max:204800'],
            ]);
            $uploaded = $request->file('backup_file');
            $tmpPath = $uploaded->getRealPath();
            $this->backupService->restoreBackup($tmpPath);
            AuditService::log('restore_backup', 'system', null, $uploaded->getClientOriginalName());
            return back()->with('success', 'تمت استعادة النظام بنجاح من الملف المرفوع (تم إنشاء نسخة أمان تلقائية قبل الاستعادة).');
        }

        $safe = basename((string) $request->input('filename', ''));
        $path = $this->backupService->backupDirectory() . DIRECTORY_SEPARATOR . $safe;
        if (!$safe || !file_exists($path) || !str_ends_with($safe, '.zip')) {
            return back()->with('error', 'ملف النسخة الاحتياطية غير صالح.');
        }

        $this->backupService->restoreBackup($path);
        AuditService::log('restore_backup', 'system', null, $safe);
        return back()->with('success', "تمت استعادة النسخة الاحتياطية ({$safe}) بنجاح (تم حفظ نسخة أمان تلقائية قبل الاستعادة).");
    }

    public function destroy(string $filename): RedirectResponse
    {
        $safe = basename($filename);
        $path = $this->backupService->backupDirectory() . DIRECTORY_SEPARATOR . $safe;
        if (file_exists($path) && str_ends_with($safe, '.zip')) {
            @unlink($path);
            AuditService::log('delete', 'backup', null, $safe);
        }
        return back()->with('success', 'تم حذف ملف النسخة الاحتياطية.');
    }
}
