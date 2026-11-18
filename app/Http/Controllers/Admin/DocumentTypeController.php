<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentType;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DocumentTypeController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLog,
    ) {
    }

    public function index(): View
    {
        return view('admin.document-types.index', [
            'documentTypes' => DocumentType::query()->withCount('documents')->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:40', 'alpha_dash', 'unique:document_types,code'],
            'name_th' => ['required', 'string', 'max:150'],
            'name_en' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $type = DocumentType::query()->create([
            'code' => strtoupper((string) $validated['code']),
            'name_th' => (string) $validated['name_th'],
            'name_en' => $validated['name_en'] ?? null,
            'description' => $validated['description'] ?? null,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => true,
        ]);

        $this->auditLog->record(
            action: 'change_setting',
            user: $request->user(),
            outcome: 'success',
            afterData: ['entity' => 'document_type', 'operation' => 'create', 'code' => $type->code],
            request: $request,
        );

        return redirect()->route('admin.document-types.index')->with('status', 'เพิ่มประเภทเอกสารเรียบร้อยแล้ว');
    }

    public function update(Request $request, DocumentType $documentType): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:40', 'alpha_dash', Rule::unique('document_types', 'code')->ignore($documentType->id)],
            'name_th' => ['required', 'string', 'max:150'],
            'name_en' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $documentType->update([
            'code' => strtoupper((string) $validated['code']),
            'name_th' => (string) $validated['name_th'],
            'name_en' => $validated['name_en'] ?? null,
            'description' => $validated['description'] ?? null,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->auditLog->record(
            action: 'change_setting',
            user: $request->user(),
            outcome: 'success',
            afterData: ['entity' => 'document_type', 'operation' => 'update', 'code' => $documentType->code, 'is_active' => $documentType->is_active],
            request: $request,
        );

        return redirect()->route('admin.document-types.index')->with('status', 'อัปเดตประเภทเอกสารเรียบร้อยแล้ว');
    }
}
