<?php

declare(strict_types=1);

return [
    // HTTP servers are registered explicitly by the host application.
    'local_server' => 'data-health',

    // Context identifies occurrences and may contain private application data.
    // Only expose it when every context field is appropriate for the client.
    'include_context' => false,
];
