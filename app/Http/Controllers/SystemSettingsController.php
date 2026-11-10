<?php

namespace App\Http\Controllers;

use App\Models\Backup;
use App\Models\SystemSetting;
use App\Services\DatabaseBackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SystemSettingsController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:120'],
            'locale' => ['required', Rule::in(['ar', 'en'])],
            'company_address' => ['nullable', 'string', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:40'],
            'company_email' => ['nullable', 'email', 'max:255'],
            'company_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $oldLogo = $this->setting('company.logo_path');
        $newLogo = null;

        if ($request->hasFile('company_logo')) {
            $newLogo = $request->file('company_logo')->storeAs(
                'company',
                Str::uuid().'.'.$request->file('company_logo')->extension(),
                'local',
            );

            if ($newLogo === false) {
                throw new RuntimeException('تعذر تخزين شعار الشركة.');
            }
        }

        try {
            DB::transaction(function () use ($validated, $newLogo): void {
                foreach ([
                    'company.name' => [$validated['company_name'], 'string', true],
                    'system.locale' => [$validated['locale'], 'string', false],
                    'company.address' => [$validated['company_address'] ?? '', 'string', false],
                    'company.phone' => [$validated['company_phone'] ?? '', 'string', false],
                    'company.email' => [$validated['company_email'] ?? '', 'string', false],
                ] as $key => [$value, $type, $isPublic]) {
                    SystemSetting::updateOrCreate(
                        ['key' => $key],
                        [
                            'value' => $value,
                            'type' => $type,
                            'group' => 'company',
                            'description' => $key,
                            'is_public' => $isPublic,
                        ],
                    );
                }

                if ($newLogo !== null) {
                    SystemSetting::updateOrCreate(
                        ['key' => 'company.logo_path'],
                        [
                            'value' => $newLogo,
                            'type' => 'image',
                            'group' => 'company',
                            'description' => 'شعار الشركة',
                            'is_public' => true,
                        ],
                    );
                }
            });
        } catch (Throwable $exception) {
            if ($newLogo !== null) {
                Storage::disk('local')->delete($newLogo);
            }

            throw $exception;
        }

        if ($newLogo !== null && filled($oldLogo) && $oldLogo !== $newLogo) {
            Storage::disk('local')->delete($oldLogo);
        }

        $request->session()->put('locale', $validated['locale']);

        return redirect()->route('filament.admin.pages.system-settings')
            ->with('status', 'تم حفظ إعدادات النظام والشركة.');
    }

    public function logo(): StreamedResponse
    {
        $path = $this->setting('company.logo_path');

        abort_unless(filled($path) && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, headers: [
            'Cache-Control' => 'public, max-age=0, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function createBackup(Request $request, DatabaseBackupService $backups): RedirectResponse
    {
        try {
            $backups->create($request->user()->id);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['backup' => 'فشل إنشاء النسخة الاحتياطية. تحقق من تثبيت أدوات MySQL وسجل التطبيق.']);
        }

        return back()->with('status', 'تم إنشاء النسخة الاحتياطية بنجاح.');
    }

    public function uploadBackup(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'backup_file' => ['required', 'file', 'extensions:sql', 'max:524288'],
        ]);

        $file = $validated['backup_file'];
        $path = $file->storeAs('backups/uploads', Str::uuid().'.sql', 'local');
        if ($path === false) {
            throw new RuntimeException('تعذر تخزين ملف النسخة الاحتياطية.');
        }

        $fullPath = Storage::disk('local')->path($path);
        $checksum = hash_file('sha256', $fullPath);

        if ($checksum === false) {
            Storage::disk('local')->delete($path);
            throw new RuntimeException('تعذر التحقق من ملف النسخة المرفوع.');
        }

        Backup::create([
            'name' => $file->getClientOriginalName(),
            'disk' => 'local',
            'path' => $path,
            'status' => 'uploaded',
            'size_bytes' => $file->getSize(),
            'checksum' => $checksum,
            'completed_at' => now(),
            'created_by' => $request->user()->id,
        ]);

        return back()->with('status', 'تم رفع ملف النسخة. أنشئ نسخة أمان قبل استعادته.');
    }

    public function downloadBackup(Backup $backup): StreamedResponse
    {
        abort_unless(Storage::disk($backup->disk)->exists($backup->path), 404);

        return Storage::disk($backup->disk)->download(
            $backup->path,
            Str::slug(pathinfo($backup->name, PATHINFO_FILENAME)).'.sql',
        );
    }

    public function restoreBackup(Request $request, Backup $backup, DatabaseBackupService $backups): RedirectResponse
    {
        $request->validate(['confirm_restore' => ['required', 'accepted']]);

        try {
            $safetyBackup = $backups->restore($backup, $request->user()->id);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['restore' => 'فشلت استعادة النسخة. لم يتم تأكيد نجاح العملية؛ راجع سجل التطبيق.']);
        }

        return back()->with(
            'status',
            "تمت الاستعادة. أُنشئت نسخة أمان قبل الاستعادة (#{$safetyBackup->id}).",
        );
    }

    public function deleteBackup(Backup $backup): RedirectResponse
    {
        $disk = Storage::disk($backup->disk);

        if ($disk->exists($backup->path) && ! $disk->delete($backup->path)) {
            return back()->withErrors(['backup' => 'تعذر حذف ملف النسخة من التخزين.']);
        }

        $backup->delete();

        return back()->with('status', 'تم حذف سجل وملف النسخة الاحتياطية.');
    }

    private function setting(string $key, ?string $default = null): ?string
    {
        return SystemSetting::query()->where('key', $key)->value('value') ?? $default;
    }
}
