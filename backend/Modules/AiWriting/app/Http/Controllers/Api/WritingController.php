<?php

namespace Modules\AiWriting\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Ai\AiGate;
use App\Services\Template\HtmlSanitizer;
use App\Services\Template\LetterHtmlSanitizer;
use App\Settings\CompanySettings;
use App\Support\Translations\LanguageRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Exceptions\AiException;
use Modules\AiWriting\Ai\WritingAgent;
use Modules\AiWriting\Enums\WritingAction;
use Modules\AiWriting\Enums\WritingPurpose;
use Modules\AiWriting\Http\Requests\WriteRequest;

class WritingController extends Controller
{
    private const TIMEOUT_SECONDS = 60;

    public function store(WriteRequest $request, AiGate $gate, LanguageRegistry $languages, CompanySettings $company): JsonResponse
    {
        $user = $request->user();
        $gate->authorize(WritingAgent::FEATURE, $user);

        $data = $request->validated();
        $action = WritingAction::from($data['action']);
        $text = trim((string) ($data['text'] ?? ''));
        $code = $action === WritingAction::Translate ? $data['language'] : app()->getLocale();
        $language = $languages->all()[$code]['name'] ?? $languages->all()[LanguageRegistry::SOURCE]['name'];
        $purpose = WritingPurpose::from($data['purpose']);
        $agent = new WritingAgent($user, $action, $purpose, $company->name ?: (string) config('app.name'), $language);

        $prompt = $action === WritingAction::Draft
            ? "What to write:\n".trim((string) $data['request']).($text !== '' ? "\n\nExisting text to build on:\n".$text : '')
            : "Text:\n".$text.(filled($data['request'] ?? null) ? "\n\nExtra guidance:\n".trim((string) $data['request']) : '');

        try {
            $result = trim($agent->prompt($prompt, timeout: self::TIMEOUT_SECONDS)->text);
        } catch (AiException $error) {
            report($error);
            abort(503, __('core.ai.unavailable'));
        }
        if ($purpose->isHtml()) {
            $result = $this->html($result, $purpose);
        }
        if ($result === '') {
            abort(503, __('core.ai.unavailable'));
        }
        if (array_diff($this->placeholders($text), $this->placeholders($result)) !== []) {
            throw ValidationException::withMessages(['text' => __('aiwriting.placeholder_lost')]);
        }

        return response()->json(['data' => ['text' => $result]]);
    }

    /** The suggestion as HTML that is safe to place in the editor, cleaned the same way the template is when saved. */
    private function html(string $result, WritingPurpose $purpose): string
    {
        $result = trim((string) preg_replace('/^```[a-z]*\s*|\s*```$/i', '', $result));
        $sanitizer = $purpose === WritingPurpose::LetterBody ? app(LetterHtmlSanitizer::class) : app(HtmlSanitizer::class);

        return $sanitizer->sanitizeTemplate($result);
    }

    /**
     * Merge fields such as {{ name }}, compared without their inner spacing.
     *
     * @return list<string>
     */
    private function placeholders(string $text): array
    {
        preg_match_all('/\{\{\s*([^{}]+?)\s*\}\}/', $text, $matches);

        return array_values(array_unique($matches[1]));
    }
}
