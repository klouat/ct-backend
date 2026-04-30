<?php

namespace App\OpenApi;

use Dedoc\Scramble\Contracts\OperationTransformer;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\SecurityRequirement;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\Str;

class ApplyApiBearerSecurity implements OperationTransformer
{
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $middleware = $routeInfo->route->gatherMiddleware();

        $hasAuthMiddleware = collect($middleware)->contains(
            fn ($name) => is_string($name) && ($name === 'auth' || Str::startsWith($name, 'auth:'))
        );

        if (! $hasAuthMiddleware) {
            $operation->security = [];

            return;
        }

        if ($operation->security === []) {
            return;
        }

        $operation->addSecurity(new SecurityRequirement([
            'bearerAuth' => [],
        ]));
    }
}
