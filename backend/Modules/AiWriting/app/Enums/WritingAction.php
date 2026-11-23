<?php

namespace Modules\AiWriting\Enums;

enum WritingAction: string
{
    case Draft = 'draft';
    case Improve = 'improve';
    case Shorten = 'shorten';
    case Formal = 'formal';
    case Translate = 'translate';

    /** Draft starts from a short note of what to write; the others rework existing text. */
    public function needsText(): bool
    {
        return $this !== self::Draft;
    }

    public function task(): string
    {
        return match ($this) {
            self::Draft => 'Write the text described in the request.',
            self::Improve => 'Rewrite the text so it is clearer and reads well. Keep its meaning, facts and length roughly the same.',
            self::Shorten => 'Shorten the text to its essential points. Keep every fact that a reader needs.',
            self::Formal => 'Rewrite the text in a formal, professional tone. Keep its meaning and facts.',
            self::Translate => 'Translate the text. Keep its meaning, tone and layout.',
        };
    }
}
