<?php

namespace Modules\AiWriting\Http\Requests;

use App\Support\Translations\LanguageRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\AiWriting\Enums\WritingAction;
use Modules\AiWriting\Enums\WritingPurpose;

class WriteRequest extends FormRequest
{
    public const MAX_TEXT = 10000;

    public const MAX_HTML = 60000;

    public function authorize(): bool
    {
        return $this->user()?->can('use_ai_writing') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $draft = WritingAction::Draft->value;
        $html = WritingPurpose::tryFrom((string) $this->input('purpose'))?->isHtml() ?? false;

        return [
            'action' => ['required', Rule::enum(WritingAction::class)],
            'purpose' => ['required', Rule::enum(WritingPurpose::class)],
            'text' => ['nullable', 'string', 'max:'.($html ? self::MAX_HTML : self::MAX_TEXT), "required_unless:action,$draft"],
            'request' => ['nullable', 'string', 'max:1000', "required_if:action,$draft"],
            'language' => ['nullable', 'string', Rule::in(app(LanguageRegistry::class)->enabled()), 'required_if:action,'.WritingAction::Translate->value],
        ];
    }
}
