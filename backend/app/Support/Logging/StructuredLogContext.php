<?php

namespace App\Support\Logging;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StructuredLogContext
{
    /**
     * @var array<string, mixed>
     */
    protected array $context = [];

    public function reset(): void
    {
        $this->context = [];
        Log::flushSharedContext();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function restore(array $context): void
    {
        $this->reset();
        $this->share($context);
    }

    public function ensureCorrelationId(?string $correlationId = null): string
    {
        $id = $correlationId ?: ($this->context['correlation_id'] ?? null) ?: (string) Str::uuid();

        $this->share([
            'correlation_id' => $id,
        ]);

        return $id;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function share(array $context): void
    {
        $normalized = collect($context)
            ->map(fn ($value) => $value instanceof Model ? $value->getKey() : $value)
            ->filter(fn ($value) => $value !== null)
            ->all();

        $this->context = array_merge($this->context, $normalized);
        Log::shareContext($this->context);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->context;
    }

    public function correlationId(): ?string
    {
        return $this->context['correlation_id'] ?? null;
    }
}
