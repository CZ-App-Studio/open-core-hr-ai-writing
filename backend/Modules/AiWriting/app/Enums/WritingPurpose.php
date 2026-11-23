<?php

namespace Modules\AiWriting\Enums;

/**
 * What the text is for, so the result suits where it will be read.
 */
enum WritingPurpose: string
{
    case Announcement = 'announcement';
    case JobPosting = 'job_posting';
    case Goal = 'goal';
    case ReviewComment = 'review_comment';
    case EmailSubject = 'email_subject';
    case EmailBody = 'email_body';
    case LetterBody = 'letter_body';
    case General = 'general';

    public function description(): string
    {
        return match ($this) {
            self::Announcement => 'an announcement posted to employees on the company noticeboard',
            self::JobPosting => 'a job description shown to candidates on the careers site',
            self::Goal => 'the description of a performance goal for an employee',
            self::ReviewComment => 'a comment in an employee performance review',
            self::EmailSubject => 'the subject line of an email, on one line and without a full stop',
            self::EmailBody => 'the body of an email the company sends to an employee or a candidate',
            self::LetterBody => 'the body of a formal human resources letter that is printed as a PDF',
            self::General => 'text in a human resources application',
        };
    }

    /** Whether the text is formatted HTML rather than plain text. */
    public function isHtml(): bool
    {
        return in_array($this, [self::EmailBody, self::LetterBody], true);
    }
}
