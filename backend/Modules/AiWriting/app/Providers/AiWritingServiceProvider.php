<?php

namespace Modules\AiWriting\Providers;

use App\Enums\AiAbility;
use App\Support\AiFeatures;
use Illuminate\Support\ServiceProvider;
use Modules\AiWriting\Ai\WritingAgent;

class AiWritingServiceProvider extends ServiceProvider
{
    protected string $name = 'AiWriting';

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }

    public function boot(): void
    {
        app(AiFeatures::class)->register(WritingAgent::FEATURE, 'aiwriting.feature', 'use_ai_writing', [AiAbility::Text], $this->name);
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [];
    }
}
