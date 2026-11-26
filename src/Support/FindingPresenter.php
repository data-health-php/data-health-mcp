<?php

declare(strict_types=1);

namespace DataHealth\Mcp\Support;

use DataHealth\Contracts\CanDetect;
use DataHealth\Contracts\CanExplain;
use DataHealth\Contracts\CanResolve;
use DataHealth\Contracts\CanVerify;
use DataHealth\DataHealthManager;
use DataHealth\Enums\RecordStatus;
use DataHealth\Finding;
use DataHealth\FindingRegistry;
use DataHealth\Mcp\Contracts\FindingAccess;
use DataHealth\Models\FindingRecord;
use Laravel\Mcp\Request;
use Throwable;

class FindingPresenter
{
    public function __construct(private FindingRegistry $registry, private FindingAccess $access, private DataHealthManager $manager) {}

    /**
     * @param  class-string<Finding>  $class
     * @return array<string, mixed>
     */
    public function type(string $key, string $class): array
    {
        return [
            'key' => $key,
            'description' => $class::getDescription(),
            'capabilities' => [
                'detect' => is_a($class, CanDetect::class, true),
                'explain' => is_a($class, CanExplain::class, true),
                'verify' => is_a($class, CanVerify::class, true),
                'resolve' => is_a($class, CanResolve::class, true),
            ],
            'action_descriptions' => [
                'detect' => $class::getMethodDescription('detect'),
                'verify' => $class::getMethodDescription('verify'),
                'resolve' => $class::getMethodDescription('resolve'),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function summary(FindingRecord $record): array
    {
        return [
            'id' => $record->id,
            'key' => $record->key,
            'model_type' => $record->model_type,
            'model_id' => $record->model_id,
            'status' => $record->status->value,
            'urgency' => $record->urgency->value,
            'worklist' => $record->worklist,
            'last_detected_at' => $record->last_detected_at->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public function details(FindingRecord $record, Request $request): array
    {
        $class = $this->registry->all()->get($record->key);
        $explanation = null;
        $explanationError = null;

        if ($class !== null && is_a($class, CanExplain::class, true)) {
            try {
                $explanation = $this->manager->explain($record);
                // Check serialization before producing a response, so malformed facts also degrade gracefully.
                json_encode($explanation, JSON_THROW_ON_ERROR);
            } catch (Throwable $exception) {
                report($exception);
                $explanation = null;
                $explanationError = 'The current explanation is unavailable.';
            }
        }

        $actions = [];
        foreach (['verify' => CanVerify::class, 'resolve' => CanResolve::class] as $ability => $contract) {
            $actions[$ability] = [
                'available' => $class !== null && is_a($class, $contract, true)
                    && $record->status === RecordStatus::Active
                    && $this->access->allows($request, $ability, $record),
                'description' => $class !== null ? $class::getMethodDescription($ability) : null,
            ];
        }

        return [
            ...$this->summary($record),
            'registered' => $class !== null,
            'description' => $class !== null ? $class::getDescription() : null,
            'explanation' => $explanation,
            'explanation_error' => $explanationError,
            'actions' => $actions,
            ...(config('data-health-mcp.include_context') ? ['context' => $record->context] : []),
        ];
    }
}
