<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ParishPublishedReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $private = $request->is('api/v1/admin/*');

        return [
            'id' => $this->id, 'parish_id' => $this->parish_id,
            'title' => $this->title, 'summary' => $this->summary,
            'body' => $this->when($private || $request->attributes->get('report_detail'), $this->body),
            'report_date' => $this->report_date?->format('Y-m-d'),
            'published_at' => $this->published_at?->toIso8601String(),
            'is_published' => $this->when($private, $this->is_published),
            'has_document' => $this->document_path !== null,
            'document_name' => $this->document_name, 'document_size' => $this->document_size,
            'document_url' => $this->document_path && ! $private
                ? rtrim(config('app.url'), '/').'/api/v1/parishes/'.$this->parish_id.'/reports/'.$this->id.'/document' : null,
        ];
    }
}
