<?php
declare(strict_types=1);

namespace TestApp;

use Bake\BakePlugin;
use Cake\Http\BaseApplication;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\RouteBuilder;
use Crustum\Prompts\PromptsPlugin;
use Migrations\MigrationsPlugin;
use Override;
use SignalHandler\SignalHandlerPlugin;

/**
 * Test application for Crustum/Audit plugin tests.
 */
class Application extends BaseApplication
{
    /**
     * @inheritDoc
     */
    #[Override]
    public function bootstrap(): void
    {
        parent::bootstrap();

        if (class_exists(MigrationsPlugin::class)) {
            $this->addPlugin('Migrations');
        }

        if (class_exists(BakePlugin::class)) {
            $this->addPlugin('Bake');
        }

        if (class_exists(PromptsPlugin::class)) {
            $this->addPlugin('Crustum/Prompts');
        }

        if (class_exists(SignalHandlerPlugin::class)) {
            $this->addPlugin('SignalHandler');
        }

        $this->addPlugin('Crustum/Audit', [
            'bootstrap' => true,
            'routes' => true,
        ]);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function routes(RouteBuilder $routes): void
    {
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        return $middlewareQueue;
    }
}
