<?php

declare(strict_types=1);

namespace DataHealth\Mcp\Tests\Fixtures\Findings;

use DataHealth\Attributes\Description;
use DataHealth\Contracts\CanDetect;
use DataHealth\Contracts\CanExplain;
use DataHealth\Contracts\CanResolve;
use DataHealth\Contracts\CanVerify;
use DataHealth\Finding;
use RuntimeException;

#[Description('The model name is broken.')]
class ActionableFinding extends Finding implements CanDetect, CanExplain, CanResolve, CanVerify
{
    public static function detect(): null
    {
        return null;
    }

    public function explain(): array
    {
        if ($this->context['fail'] ?? false) {
            throw new RuntimeException('secret connection credentials');
        }
        $model = $this->model->refresh();

        return [
            'expected' => 'The model name should be correct.',
            'actual' => ['name' => $model->getAttribute('name')],
            'still_applies' => $model->getAttribute('name') !== 'correct',
            'observed_at' => now()->toIso8601String(),
        ];
    }

    #[Description('Check the current name and close this finding if corrected.')]
    public function verify(): bool
    {
        if ($this->context['fail'] ?? false) {
            throw new RuntimeException('secret connection credentials');
        }

        return $this->model->refresh()->getAttribute('name') === 'correct';
    }

    #[Description('Change the name to correct after checking the current state.')]
    public function resolve(): bool
    {
        if ($this->context['fail'] ?? false) {
            throw new RuntimeException('secret connection credentials');
        }

        if ($this->context['cannot_resolve'] ?? false) {
            return false;
        }

        return $this->model->refresh()->update(['name' => 'correct']);
    }
}
