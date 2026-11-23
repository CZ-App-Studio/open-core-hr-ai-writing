import { AI_WRITING_HTML_PURPOSES, useAiWritingField } from '@/components/ai-writing-assist';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle
} from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useTranslations } from '@/lib/translations';
import { cn, getErrorMessage } from '@/lib/utils';
import axios from 'axios';
import { Sparkles } from 'lucide-react';
import { useState } from 'react';

const ACTIONS = ['draft', 'improve', 'shorten', 'formal', 'translate'] as const;
type Action = (typeof ACTIONS)[number];

export default function AiWritingSlot() {
  const field = useAiWritingField();
  const { t: translate, languages, language: currentLanguage } = useTranslations();
  const t = (key: string) => translate(`aiwriting.${key}`);
  const [open, setOpen] = useState(false);
  const [action, setAction] = useState<Action>('draft');
  const [request, setRequest] = useState('');
  const [language, setLanguage] = useState('');
  const [suggestion, setSuggestion] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const html = AI_WRITING_HTML_PURPOSES.includes(field.purpose);
  const hasText = (html ? field.value.replace(/<[^>]*>/g, '') : field.value).trim() !== '';

  function show() {
    setAction(hasText ? 'improve' : 'draft');
    setRequest('');
    setLanguage(languages.find(option => option.code !== currentLanguage)?.code ?? '');
    setSuggestion('');
    setError('');
    setOpen(true);
  }

  async function write() {
    if (action === 'draft' && request.trim() === '') return setError(t('needs_request'));
    if (action !== 'draft' && !hasText) return setError(t('needs_text'));
    setBusy(true);
    setError('');
    try {
      const response = await axios.post<{ data: { text: string } }>(route('api.ai-writing.store'), {
        action,
        purpose: field.purpose,
        text: field.value || null,
        request: request.trim() || null,
        language: action === 'translate' ? language : null
      });
      const text = response.data.data.text;
      setSuggestion(field.maxLength ? text.slice(0, field.maxLength) : text);
    } catch (failure) {
      setError(getErrorMessage(failure));
    } finally {
      setBusy(false);
    }
  }

  return (
    <>
      <Button type='button' variant='ghost' size='sm' className='tw:text-primary' onClick={show}>
        <Sparkles className='tw:me-2 tw:h-4 tw:w-4' />
        {t('button')}
      </Button>
      <Dialog open={open} onOpenChange={setOpen} busy={busy} protectChanges={false}>
        <DialogContent className='tw:max-w-2xl'>
          <DialogHeader>
            <DialogTitle>{t('title')}</DialogTitle>
            <DialogDescription>{t('description')}</DialogDescription>
          </DialogHeader>
          <div className='tw:space-y-4'>
            <div className='tw:space-y-2'>
              <p className='tw:text-sm tw:font-medium'>{t('what')}</p>
              <div className='tw:flex tw:flex-wrap tw:gap-2' role='group' aria-label={t('what')}>
                {ACTIONS.filter(option => option !== 'translate' || languages.length > 1).map(option => (
                  <Button
                    key={option}
                    type='button'
                    size='sm'
                    variant={action === option ? 'default' : 'outline'}
                    aria-pressed={action === option}
                    disabled={busy || (option !== 'draft' && !hasText)}
                    onClick={() => {
                      setAction(option);
                      setError('');
                    }}>
                    {t(`action.${option}`)}
                  </Button>
                ))}
              </div>
            </div>
            {action === 'translate' && (
              <FormField label={t('language')} id='ai-writing-language'>
                <Select value={language} onValueChange={setLanguage} disabled={busy}>
                  <SelectTrigger id='ai-writing-language' className='tw:max-w-xs'>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {languages.map(option => (
                      <SelectItem key={option.code} value={option.code}>
                        {option.native_name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </FormField>
            )}
            <FormField
              label={t(action === 'draft' ? 'request_draft' : 'request_other')}
              hint={action === 'draft' ? t('request_draft_hint') : undefined}
              error={error || undefined}
              id='ai-writing-request'>
              <Textarea
                id='ai-writing-request'
                rows={2}
                maxLength={1000}
                value={request}
                disabled={busy}
                onChange={event => setRequest(event.target.value)}
              />
            </FormField>
            {(hasText || suggestion) && (
              <div className={cn('tw:grid tw:gap-4', hasText && suggestion && 'tw:sm:grid-cols-2')}>
                {hasText && (
                  <div className='tw:space-y-1'>
                    <p className='tw:text-sm tw:font-medium'>{t('current')}</p>
                    {html ? (
                      <HtmlPreview title={t('current')} html={field.value} />
                    ) : (
                      <p className='tw:max-h-60 tw:overflow-y-auto tw:whitespace-pre-wrap tw:rounded-lg tw:bg-muted/50 tw:p-3 tw:text-sm tw:text-muted-foreground'>
                        {field.value}
                      </p>
                    )}
                  </div>
                )}
                {suggestion && html && (
                  <div className='tw:space-y-1'>
                    <p className='tw:text-sm tw:font-medium'>{t('suggestion')}</p>
                    <HtmlPreview title={t('suggestion')} html={suggestion} />
                    <p className='tw:text-xs tw:text-muted-foreground'>{t('edit_after')}</p>
                  </div>
                )}
                {suggestion && !html && (
                  <FormField label={t('suggestion')} id='ai-writing-suggestion'>
                    <Textarea
                      id='ai-writing-suggestion'
                      rows={8}
                      maxLength={field.maxLength}
                      value={suggestion}
                      disabled={busy}
                      onChange={event => setSuggestion(event.target.value)}
                    />
                  </FormField>
                )}
              </div>
            )}
            <p className='tw:text-xs tw:text-muted-foreground'>{t('privacy')}</p>
          </div>
          <DialogFooter>
            <Button
              type='button'
              variant={suggestion ? 'outline' : 'default'}
              disabled={busy}
              onClick={() => void write()}>
              {t(busy ? 'writing' : suggestion ? 'again' : 'write')}
            </Button>
            {suggestion && (
              <Button
                type='button'
                disabled={busy}
                onClick={() => {
                  field.onApply(suggestion);
                  setOpen(false);
                }}>
                {t('use')}
              </Button>
            )}
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}

/** Formatted text shown as it will read. The frame runs no scripts and cannot reach the page around it. */
function HtmlPreview({ title, html }: { title: string; html: string }) {
  return (
    <iframe
      title={title}
      sandbox=''
      className='tw:h-60 tw:w-full tw:rounded-lg tw:border tw:bg-white'
      srcDoc={`<!doctype html><body style="font:14px/1.5 system-ui,sans-serif;color:#111;margin:12px">${html}</body>`}
    />
  );
}
