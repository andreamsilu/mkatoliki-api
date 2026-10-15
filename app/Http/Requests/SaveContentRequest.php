<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()) { return false; }
        app(\App\Services\ContentPublishing::class)->authorize($this->user(), 'content.manage', $this->route('post'));
        return true;
    }

    public function rules(): array
    {
        return [
            'slug' => ['required', 'string', 'max:180', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('content_posts')->ignore($this->route('post')?->id)],
            'content_type' => ['required', Rule::in(['reflection', 'teaching', 'prayer', 'song', 'history', 'question', 'christian_life', 'parish_story'])],
            'format' => ['required', Rule::in(['article', 'audio', 'short'])],
            'featured' => ['sometimes', 'boolean'],
            'season' => ['nullable', Rule::in(['advent', 'christmas', 'lent', 'easter', 'ordinary'])],
            'category_ids' => ['required', 'array', 'min:1', 'max:8'],
            'category_ids.*' => ['integer', 'distinct', 'exists:content_categories,id'],
            'tag_ids' => ['sometimes', 'array', 'max:20'],
            'tag_ids.*' => ['integer', 'distinct', 'exists:content_tags,id'],
            'cover_media_id' => ['nullable', 'integer', 'exists:content_media,id'],
            'audio_media_id' => ['nullable', 'required_if:format,audio', 'integer', 'exists:content_media,id'],
            'source_type' => ['required', Rule::in(['editorial', 'official'])],
            'source_name' => ['nullable', 'required_if:source_type,official', 'required_unless:rights_status,original,unknown', 'string', 'max:255'],
            'source_url' => ['nullable', 'required_if:source_type,official', 'url:https', 'max:2000'],
            'rights_status' => ['required', Rule::in(['original', 'public_domain', 'licensed', 'permission', 'unknown'])],
            'license_details' => ['nullable', 'required_if:rights_status,licensed,permission', 'string', 'max:5000'],
            'composer' => ['nullable', 'string', 'max:255'],
            'choir' => ['nullable', 'string', 'max:255'],
            'reading_time_minutes' => ['nullable', 'integer', 'between:1,240'],
            'translations' => ['required', 'array', 'min:1', 'max:5'],
            'translations.*' => ['array:locale,title,summary,body,scripture_reference,practical_action,closing_prayer'],
            'translations.*.locale' => ['required', 'distinct', Rule::in(['sw', 'en'])],
            'translations.*.title' => ['required', 'string', 'max:255'],
            'translations.*.summary' => ['required', 'string', 'max:2000'],
            'translations.*.body' => ['required', 'string', 'max:200000'],
            'translations.*.scripture_reference' => ['nullable', 'string', 'max:255'],
            'translations.*.practical_action' => ['nullable', 'string', 'max:5000'],
            'translations.*.closing_prayer' => ['nullable', 'string', 'max:10000'],
            'reflections' => ['sometimes', 'array', 'max:5'],
            'reflections.*' => ['array:locale,reflection_date'],
            'reflections.*.locale' => ['required', 'distinct', Rule::in(['sw', 'en'])],
            'reflections.*.reflection_date' => ['required', 'date_format:Y-m-d'],
        ];
    }
}
