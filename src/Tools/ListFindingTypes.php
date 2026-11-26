<?php

declare(strict_types=1);

namespace DataHealth\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListFindingTypes extends FindingTool
{
    protected string $name = 'list_finding_types';

    protected string $description = 'List registered finding types, descriptions, and supported capabilities. Does not run detection.';

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    protected function execute(Request $request): ResponseFactory
    {
        $this->access->authorize($request, 'viewAny');
        $types = [];
        foreach ($this->registry->all() as $key => $class) {
            $types[] = $this->presenter->type($key, $class);
        }

        return Response::structured(['data' => $types]);
    }
}
