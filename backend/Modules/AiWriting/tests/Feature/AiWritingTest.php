<?php

namespace Modules\AiWriting\Tests\Feature;

use App\Enums\AiAbility;
use App\Models\AiRequest;
use App\Models\User;
use App\Settings\AiProviderSettings;
use App\Settings\AiSettings;
use App\Settings\CompanySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Prompts\AgentPrompt;
use Modules\AiWriting\Ai\WritingAgent;
use Modules\AiWriting\Http\Requests\WriteRequest;
use Nwidart\Modules\Facades\Module;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class AiWritingTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->createAdminUser();
        $provider = app(AiProviderSettings::class);
        $provider->provider = 'anthropic';
        $provider->apiKey = 'test-key';
        $provider->abilities = [AiAbility::Text->value => true];
        $provider->save();
        $this->switchOn();
    }

    public function test_a_draft_is_written_from_a_short_request_and_only_counts_are_kept(): void
    {
        $company = app(CompanySettings::class);
        $company->name = 'Northwind Trading';
        $company->save();
        WritingAgent::fake(['  The office is closed on Friday.  '])->preventStrayPrompts();

        $this->actingAs($this->admin)->postJson(route('api.ai-writing.store'), [
            'action' => 'draft', 'purpose' => 'announcement', 'request' => 'office closed friday',
        ])->assertOk()->assertExactJson(['data' => ['text' => 'The office is closed on Friday.']]);

        WritingAgent::assertPrompted(function (AgentPrompt $prompt): bool {
            $instructions = (string) $prompt->agent->instructions();

            return str_contains($prompt->prompt, 'office closed friday')
                && str_contains($instructions, 'Northwind Trading')
                && str_contains($instructions, 'announcement posted to employees')
                && str_contains($instructions, 'English')
                && ! str_contains($instructions.$prompt->prompt, $this->admin->email);
        });
        $request = AiRequest::query()->sole();
        $this->assertSame([$this->admin->id, 'writing'], [$request->user_id, $request->feature]);
    }

    public function test_existing_text_is_reworked_and_translated_into_an_enabled_language(): void
    {
        WritingAgent::fake(['نص مترجم'])->preventStrayPrompts();

        $this->actingAs($this->admin)->postJson(route('api.ai-writing.store'), [
            'action' => 'translate', 'purpose' => 'job_posting', 'text' => 'We are hiring a driver.', 'language' => 'ar',
        ])->assertOk()->assertJsonPath('data.text', 'نص مترجم');

        WritingAgent::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains($prompt->prompt, 'We are hiring a driver.')
            && str_contains((string) $prompt->agent->instructions(), 'Write the result in Arabic.'));
    }

    public function test_each_action_needs_its_own_input(): void
    {
        WritingAgent::fake()->preventStrayPrompts();
        $this->actingAs($this->admin);
        $send = fn (array $payload) => $this->postJson(route('api.ai-writing.store'), ['purpose' => 'general', ...$payload])->assertUnprocessable();

        $send(['action' => 'draft'])->assertJsonValidationErrors('request');
        $send(['action' => 'improve'])->assertJsonValidationErrors('text');
        $send(['action' => 'translate', 'text' => 'Hello'])->assertJsonValidationErrors('language');
        $send(['action' => 'translate', 'text' => 'Hello', 'language' => 'zz'])->assertJsonValidationErrors('language');
        $send(['action' => 'summarise', 'text' => 'Hello'])->assertJsonValidationErrors('action');
        $send(['action' => 'improve', 'text' => str_repeat('a', 10001)])->assertJsonValidationErrors('text');
        $this->postJson(route('api.ai-writing.store'), ['action' => 'improve', 'purpose' => 'salary_letter', 'text' => 'Hello'])
            ->assertUnprocessable()->assertJsonValidationErrors('purpose');
        WritingAgent::assertNeverPrompted();
    }

    public function test_merge_fields_must_survive_a_rewrite(): void
    {
        $this->actingAs($this->admin);
        $payload = ['action' => 'shorten', 'purpose' => 'email_subject', 'text' => 'Welcome {{ employee_name }} to {{company}}'];

        WritingAgent::fake(['Welcome to the team']);
        $this->postJson(route('api.ai-writing.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('text');

        WritingAgent::fake(['Welcome, {{employee_name}} · {{ company }}']);
        $this->postJson(route('api.ai-writing.store'), $payload)->assertOk()->assertJsonPath('data.text', 'Welcome, {{employee_name}} · {{ company }}');
    }

    public function test_an_email_or_letter_body_comes_back_as_clean_html_with_its_merge_fields(): void
    {
        $body = '<p>Dear {{ employee_name }},</p><p>Your leave is approved. <a href="{{ portal_url }}">Open the portal</a></p>';
        WritingAgent::fake([
            "```html\n<p onclick=\"steal()\">Dear {{employee_name}},</p><script>alert(1)</script><p>Your leave has been approved. <a href=\"{{ portal_url }}\">Open the portal</a></p>\n```",
            '<p>Dear colleague,</p><p>Your leave has been approved.</p>',
            '<p class="letter-center">Dear {{ employee_name }}, <a href="{{ portal_url }}">portal</a></p><img src="https://example.test/x.png">',
        ]);
        $send = fn (string $purpose) => $this->actingAs($this->admin)->postJson(route('api.ai-writing.store'), ['action' => 'improve', 'purpose' => $purpose, 'text' => $body]);

        $send('email_body')->assertOk()->assertExactJson(['data' => [
            'text' => '<p>Dear {{ employee_name }},</p><p>Your leave has been approved. <a href="{{ portal_url }}">Open the portal</a></p>',
        ]]);
        WritingAgent::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains((string) $prompt->agent->instructions(), 'The text is HTML')
            && str_contains((string) $prompt->agent->instructions(), 'body of an email'));

        $send('email_body')->assertUnprocessable()->assertJsonValidationErrors('text');

        $letter = $send('letter_body')->assertOk()->json('data.text');
        $this->assertStringContainsString('class="letter-center"', $letter);
        $this->assertStringContainsString('href="{{ portal_url }}"', $letter);
        $this->assertStringNotContainsString('<img', $letter);

        $this->postJson(route('api.ai-writing.store'), ['action' => 'improve', 'purpose' => 'announcement', 'text' => str_repeat('a', WriteRequest::MAX_TEXT + 1)])
            ->assertUnprocessable()->assertJsonValidationErrors('text');
        $this->postJson(route('api.ai-writing.store'), ['action' => 'improve', 'purpose' => 'letter_body', 'text' => str_repeat('a', WriteRequest::MAX_HTML + 1)])
            ->assertUnprocessable()->assertJsonValidationErrors('text');
    }

    public function test_people_without_the_permission_and_signed_out_visitors_are_refused(): void
    {
        WritingAgent::fake()->preventStrayPrompts();
        $payload = ['action' => 'draft', 'purpose' => 'general', 'request' => 'a welcome note'];

        $this->postJson(route('api.ai-writing.store'), $payload)->assertUnauthorized();
        $this->actingAs($this->createEmployeeUser())->postJson(route('api.ai-writing.store'), $payload)->assertForbidden();
        WritingAgent::assertNeverPrompted();
    }

    public function test_the_administrator_switches_and_the_monthly_limit_are_enforced(): void
    {
        WritingAgent::fake(['One', 'Two']);
        $this->actingAs($this->admin);
        $payload = ['action' => 'draft', 'purpose' => 'general', 'request' => 'a welcome note'];

        $this->switchOn(feature: false);
        $this->postJson(route('api.ai-writing.store'), $payload)->assertConflict()->assertJsonPath('message', __('core.ai.refusal.off'));
        $this->switchOn(enabled: false);
        $this->postJson(route('api.ai-writing.store'), $payload)->assertConflict();
        WritingAgent::assertNeverPrompted();

        $this->switchOn(limit: 1);
        $this->postJson(route('api.ai-writing.store'), $payload)->assertOk();
        $this->postJson(route('api.ai-writing.store'), $payload)->assertTooManyRequests()->assertJsonPath('message', __('core.ai.refusal.limit_reached'));
    }

    public function test_a_provider_failure_is_reported_in_plain_words(): void
    {
        WritingAgent::fake(fn () => throw ProviderConnectionException::forProvider('anthropic'));

        $this->actingAs($this->admin)->postJson(route('api.ai-writing.store'), ['action' => 'draft', 'purpose' => 'general', 'request' => 'a welcome note'])
            ->assertServiceUnavailable()->assertJsonPath('message', __('core.ai.unavailable'));
    }

    public function test_the_feature_appears_under_settings_and_only_for_people_who_may_use_it(): void
    {
        $this->isolateModuleStatuses();
        Module::find('SaaS')?->disable();
        config(['organizations.hosted' => false]);
        $listed = collect($this->actingAs($this->admin)->getJson(route('api.settings.ai.show'))->assertOk()->json('data.features'))->firstWhere('key', 'writing');
        $this->assertSame(['key' => 'writing', 'label' => __('aiwriting.feature'), 'enabled' => true, 'missing' => null], Arr::only($listed, ['key', 'label', 'enabled', 'missing']));
        $this->assertSame([[], [], 0, null], [$listed['department_ids'], $listed['user_ids'], $listed['per_person_limit'], $listed['settings_url']]);
        $this->assertSame(['writing'], $this->get(route('dashboard'))->viewData('page')['props']['ai']);

        $this->assertContains('use_ai_writing', config('permission_grants.hr_officer'));
        $this->assertNotContains('use_ai_writing', config('permission_grants.personal'));
        $employee = $this->createEmployeeWithMasterData()['user'];
        $this->assertSame([], $this->actingAs($employee)->get(route('dashboard'))->viewData('page')['props']['ai']);
    }

    private function switchOn(bool $enabled = true, bool $feature = true, int $limit = 0): void
    {
        $settings = app(AiSettings::class);
        $settings->enabled = $enabled;
        $settings->features = ['writing' => $feature];
        $settings->monthlyRequestLimit = $limit;
        $settings->save();
    }
}
