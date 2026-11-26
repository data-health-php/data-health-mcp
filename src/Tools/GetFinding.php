<?php

declare(strict_types=1);

namespace DataHealth\Mcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetFinding extends FindingTool
{
    protected string $name = 'get_finding';

    protected string $description = 'Read one visible finding, its description, current explanation, and authorized actions. Does not change finding status or business data.';

    protected function execute(Request $request): ResponseFactory
    {
        return Response::structured($this->presenter->details($this->record($request), $request));
    }
}
