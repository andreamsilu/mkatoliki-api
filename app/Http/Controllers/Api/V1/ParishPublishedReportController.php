<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ParishPublishedReportResource;
use App\Models\Parish;
use App\Models\ParishPublishedReport;
use App\Services\AuditService;
use App\Services\DirectoryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class ParishPublishedReportController extends Controller
{
    public function publicIndex(Request $request, string $id, DirectoryService $directory): JsonResponse
    {
        $parish = $directory->query('parishes')->findOrFail($id);
        $request->validate(['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'between:1,50']]);
        $page = ParishPublishedReport::published()->where('parish_id', $parish->id)->latest('published_at')->latest('id')->paginate($request->integer('per_page', 20));

        return ApiResponse::page($page, ParishPublishedReportResource::collection($page->getCollection())->resolve($request));
    }

    private function publicReport(string $id, ParishPublishedReport $report, DirectoryService $directory): void
    {
        $directory->query('parishes')->findOrFail($id);
        abort_unless((string) $report->parish_id === $id && ParishPublishedReport::published()->whereKey($report->id)->exists(), 404);
    }

    public function show(Request $request, string $id, ParishPublishedReport $report, DirectoryService $directory): JsonResponse
    {
        $this->publicReport($id, $report, $directory);
        $request->attributes->set('report_detail', true);

        return ApiResponse::success((new ParishPublishedReportResource($report))->resolve($request));
    }

    public function document(string $id, ParishPublishedReport $report, DirectoryService $directory): BinaryFileResponse
    {
        $this->publicReport($id, $report, $directory);

        return $this->file($report);
    }

    public function preview(ParishPublishedReport $report): BinaryFileResponse
    {
        Gate::authorize('view', $report->parish);

        return $this->file($report);
    }

    private function file(ParishPublishedReport $report): BinaryFileResponse
    {
        abort_unless($report->document_path && Storage::disk('local')->exists($report->document_path), 404);

        return response()->file(Storage::disk('local')->path($report->document_path), [
            'Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="parish-report-'.$report->id.'.pdf"',
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function index(Request $request, Parish $parish): JsonResponse
    {
        Gate::authorize('view', $parish);
        $request->validate(['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'between:1,50']]);
        $page = ParishPublishedReport::where('parish_id', $parish->id)->latest('id')->paginate($request->integer('per_page', 24));

        return ApiResponse::page($page, ParishPublishedReportResource::collection($page->getCollection())->resolve($request));
    }

    public function store(Request $request, Parish $parish, AuditService $audit): JsonResponse
    {
        Gate::authorize('update', $parish);

        return $this->save($request, $parish, new ParishPublishedReport, $audit);
    }

    public function update(Request $request, ParishPublishedReport $report, AuditService $audit): JsonResponse
    {
        Gate::authorize('update', $report->parish);

        return $this->save($request, $report->parish, $report, $audit);
    }

    private function save(Request $request, Parish $parish, ParishPublishedReport $report, AuditService $audit): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'], 'summary' => ['required', 'string', 'max:5000'],
            'body' => ['nullable', 'string', 'max:200000'], 'report_date' => ['nullable', 'date_format:Y-m-d'],
            'published_at' => ['nullable', 'date'], 'is_published' => ['required', 'boolean'],
            'document' => ['nullable', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:10240'],
            'remove_document' => ['sometimes', 'boolean'],
        ]);
        $upload = $request->file('document');
        $newPath = $upload?->store('parish-reports/'.$parish->id, 'local');
        abort_if($upload && ! is_string($newPath), 503);
        $oldPath = null;
        $created = ! $report->exists;
        try {
            $record = DB::transaction(function () use ($request, $report, $parish, $data, $newPath, $upload, $audit, &$oldPath): ParishPublishedReport {
                $record = $report->exists ? ParishPublishedReport::lockForUpdate()->findOrFail($report->id) : $report;
                Gate::authorize('update', $parish);
                $oldPath = $record->document_path;
                $record->fill(Arr::except($data, ['document', 'remove_document']));
                $record->parish_id = $parish->id;
                if ($upload) {
                    $record->document_path = $newPath;
                    $record->document_name = mb_substr(basename($upload->getClientOriginalName()), 0, 255);
                    $record->document_size = $upload->getSize();
                } elseif ($request->boolean('remove_document')) {
                    $record->document_path = null;
                    $record->document_name = null;
                    $record->document_size = null;
                }
                if (! trim($record->body ?? '') && ! $record->document_path) {
                    throw ValidationException::withMessages(['body' => 'Write a report or attach a PDF document.']);
                }
                if ($record->is_published && ! $record->published_at) { $record->published_at = now(); }
                $record->save();
                $audit->record($request->user(), 'parish_report.saved', 'parish_published_reports', $record->id);

                return $record;
            });
        } catch (Throwable $error) {
            if ($newPath) { Storage::disk('local')->delete($newPath); }
            throw $error;
        }
        if ($oldPath && $oldPath !== $record->document_path) { Storage::disk('local')->delete($oldPath); }

        return ApiResponse::success((new ParishPublishedReportResource($record))->resolve($request), $created ? 201 : 200);
    }

    public function destroy(Request $request, ParishPublishedReport $report, AuditService $audit): JsonResponse
    {
        Gate::authorize('update', $report->parish);
        $path = DB::transaction(function () use ($request, $report, $audit): ?string {
            $record = ParishPublishedReport::lockForUpdate()->findOrFail($report->id);
            $path = $record->document_path;
            $record->delete();
            $audit->record($request->user(), 'parish_report.deleted', 'parish_published_reports', $record->id);

            return $path;
        });
        if ($path) { Storage::disk('local')->delete($path); }

        return ApiResponse::success(['deleted' => true]);
    }
}
