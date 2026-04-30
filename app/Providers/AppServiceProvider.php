<?php

namespace App\Providers;

use App\OpenApi\ApplyApiBearerSecurity;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Scramble::configure()
            ->withOperationTransformers([
                ApplyApiBearerSecurity::class,
            ])
            ->afterOpenApiGenerated(function (OpenApi $openApi) {
                $openApi->components->addSecurityScheme(
                    'bearerAuth',
                    SecurityScheme::http('bearer', 'JWT')
                        ->as('bearerAuth')
                        ->setDescription('Use a JWT access token with the Bearer scheme.')
                );
            });
    }
}
