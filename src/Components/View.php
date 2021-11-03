<?php

namespace Vio\Matchstick\Components;

use Vio\Matchstick\App;

class View
{

    public static $factory = null;

    public static function bootstrap()
    {
        $container = App::getInstance();

        // we have to bind our app class to the interface
        // as the blade compiler needs the `getNamespace()` method to guess Blade component FQCNs
        $container->instance(\Illuminate\Contracts\Foundation\Application::class, $container);

        // Configuration
        $pathsToTemplates = config('view.paths.templates', []);
        $namespacedPaths = config('view.namespaces', []);
        $pathToCompiledTemplates = config('view.paths.compiled', []);

        // Dependencies
        $filesystem = new \Illuminate\Filesystem\Filesystem;
        $eventDispatcher = new \Illuminate\Events\Dispatcher($container);

        // Create View Factory capable of rendering PHP and Blade templates
        $viewResolver = new \Illuminate\View\Engines\EngineResolver;
        $bladeCompiler = new \Illuminate\View\Compilers\BladeCompiler($filesystem, $pathToCompiledTemplates);

        $viewResolver->register('blade', function () use ($bladeCompiler) {
            return new \Illuminate\View\Engines\CompilerEngine($bladeCompiler);
        });

        $viewFinder = new \Illuminate\View\FileViewFinder($filesystem, $pathsToTemplates);
        $viewFactory = new \Illuminate\View\Factory($viewResolver, $viewFinder, $eventDispatcher);
        $viewFactory->setContainer($container);

        // Add namespaces
        foreach($namespacedPaths as $name => $hints){
            $viewFinder->addNamespace($name, $hints);
        }

        $container->instance(\Illuminate\Contracts\View\Factory::class, $viewFactory);
        $container->alias(
            \Illuminate\Contracts\View\Factory::class,
            (new class extends \Illuminate\Support\Facades\View
            {
                public static function getFacadeAccessor()
                {
                    return parent::getFacadeAccessor();
                }
            })::getFacadeAccessor()
        );

        $container->instance(\Illuminate\View\Compilers\BladeCompiler::class, $bladeCompiler);
        $container->alias(
            \Illuminate\View\Compilers\BladeCompiler::class,
            (new class extends \Illuminate\Support\Facades\Blade
            {
                public static function getFacadeAccessor()
                {
                    return parent::getFacadeAccessor();
                }
            })::getFacadeAccessor()
        );

        static::$factory = $viewFactory;

        if (!function_exists('view')) {
            function view($view = null, $data = [], $mergeData = [])
            {
                $factory = View::$factory;

                if (func_num_args() === 0) {
                    return $factory;
                }

                return $factory->make($view, $data, $mergeData);
            }
        }
    }
}
