<?php

namespace Yijie\Routing\Console;

use Illuminate\Console\Command;
use Yijie\Dev\Doc\Generators\ControllerGenerator;
use Yijie\Dev\Doc\Generators\ActionGenerator;
use Yijie\Routing\Database\Models\Route;
use Illuminate\Support\Str;

class RouteApiMakeCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'yijie.make:route.api {version=v1}';

    /**
     * @var string
     */
    protected $description = '根据控制器定义生成路由';

    /**
     * -----------------------------------------------------------
     * 获取stub
     * @param array $arguments
     * @return string
     * -----------------------------------------------------------
     */
    protected function getStub(array $arguments)
    {
        $stub = file_get_contents(__DIR__ . '/stubs/route_api.stub');
        $search = [];
        $replace = [];
        foreach ($arguments as $key => $argument) {
            $search[] = "{{{$key}}}";
            $replace[] = "{$argument}";
        }
        return str_replace($search, $replace, $stub);
    }

    /**
     * -----------------------------------------------------------
     * 获取控制器所在路径
     * @return array
     * -----------------------------------------------------------
     */
    protected function getControllersPaths()
    {
        return [app_path('Http/Controllers')];
    }

    /**
     * -----------------------------------------------------------
     * 获取输出路径
     * -----------------------------------------------------------
     */
    protected function getOutputPath($version)
    {
        return base_path('routes/api') . DIRECTORY_SEPARATOR . $version . '.php';
    }

    /**
     * -----------------------------------------------------------
     * 路径转换namespace
     * @param $path
     * @return string
     * -----------------------------------------------------------
     */
    protected function getNamespace($path)
    {
        return ucfirst(substr(str_replace([base_path(), '/'], ['', '\\'], $path), 1));
    }

    /**
     * -----------------------------------------------------------
     * 获取file app
     * @return \Illuminate\Filesystem\Filesystem
     * -----------------------------------------------------------
     */
    protected function getFileApp()
    {
        return app('files');
    }

    /**
     * -----------------------------------------------------------
     * Determine if the class already exists.
     * @param string $path
     * @return bool
     * -----------------------------------------------------------
     */
    protected function alreadyExists($path)
    {
        return $this->getFileApp()->exists($path);
    }

    /**
     * -----------------------------------------------------------
     * Build the directory for the class if necessary.
     * @param $path
     * @return mixed
     * -----------------------------------------------------------
     */
    protected function makeDirectory($path)
    {
        if (!$this->getFileApp()->isDirectory(dirname($path))) {
            $this->getFileApp()->makeDirectory(dirname($path), 0777, true, true);
        }

        return $path;
    }

    /**
     * -----------------------------------------------------------
     * 生成路由
     * -----------------------------------------------------------
     */
    public function handle()
    {
        $version = $this->argument('version');
        $controllers = $this->getControllers($version);
        $outputPath = $this->getOutputPath($version);
        $outputContent = '<?php' . "\n" . '$api = app(\'Dingo\Api\Routing\Router\');';
        if (!$this->alreadyExists($outputPath)) {
            $this->makeDirectory($outputPath);
            $this->info($outputPath . " created successfully.");
        }
        $attributes = $saveAttributes = [];
        foreach ($controllers as $className => $doc) {
            $attribute = [];
            $outputContent .= "\n" . '// ' . $className;
            $prefix = $doc->findTag('prefix');
            $prefix = $prefix ? $prefix : $className;
            $attribute['controller_name'] = $doc->getTitle();
            $attribute['controller'] = $className = str_replace('/', '\\', $className);
            $doc->eachMyMethod(function (ActionGenerator $actionDoc) use ($className, $prefix, &$outputContent, &$attributes, &$attribute,&$saveAttributes) {
                $this->info($className . " putting ......");
                $title = $actionDoc->getTitle();
                $ext = '';
                $findTagsWhere = ['middleware', 'method'];
                $tags = $actionDoc->findWhereTags($findTagsWhere);
                $actionMethod = current($tags['method']);
                if (!$actionMethod) return false;
                $middleware = $tags['middleware'];
                if ($middleware) {
                    $ext .= '->middleware(' . json_encode($middleware) . ')';
                }
                $arguments['prefix'] = str_replace(['_controller', '/_'], ['', '/'], strtolower(Str::snake($prefix)));
                $arguments['class'] = $className;
                $arguments['method'] = $actionDoc->getName();
                $arguments['ext'] = $ext;
                $arguments['title'] = $title;
                $arguments['actionMethod'] = $actionMethod;
                $outputContent .= "\n" . $this->getStub($arguments);
                $attribute['url'] = $arguments['actionMethod'] . "/{$arguments['prefix']}/{$arguments['method']}";
                $attribute['method_name'] = $arguments['title'];
                $attribute['description'] = $actionDoc->getDesc();
                $attribute['method'] = $arguments['method'];
                if (isset($attribute['url'])) {
                    $attributes[$attribute['url']] = $attribute;
                    if (in_array('check.permission', $middleware)) {
                        $saveAttributes[$attribute['url']] = $attribute;
                    }
                }
            });
        }
        $model = app(Route::class);
        $this->getFileApp()->put($outputPath, $outputContent);

        $model->all()->each(function ($item) use (&$saveAttributes) {
            $url = $item->url;
            if (isset($saveAttributes[$url])) {
                foreach ($saveAttributes[$url] as $key => $value) {
                    if (empty($item->{$key}) || $item->{$key} != $value) {
                        $item->fill($saveAttributes[$url])->save();
                        break;
                    }
                }
                unset($saveAttributes[$url]);
            } else {
                $item->delete();
            }
        });
        $saveAttributes = array_values($saveAttributes);
        if ($saveAttributes) $model->createBatch($model->getInsertAttributes($saveAttributes));
        $this->call('api:cache');
        $this->info("all controllers put successfully.");
    }

    /**
     * -----------------------------------------------------------
     * 获取Controllers
     * @param $version
     * @return array
     * -----------------------------------------------------------
     */
    public function getControllers($version)
    {
        $controllers = [];
        $paths = $this->getControllersPaths();
        $this->eachPaths($paths, $version, $controllers);
        return $controllers;
    }

    protected function eachPaths($paths, $version, &$controllers, $rootPath = '')
    {
        $app = $this->getFileApp();
        foreach ($paths as $item) {
            if (($dirs = $app->directories($item))) {
                $this->eachPaths($dirs, $version, $controllers, $item);
            }
            $dir = '';
            if ($rootPath) {
                $_item = str_replace('\\', '/', $item);
                $_rootPath = str_replace('\\', '/', $rootPath);
                $dir = str_replace($_rootPath . '/', '', $_item);
                $dir .= '/';
            }
            $namespace = $this->getNamespace($item);
            foreach ($app->files($item) as $file) {
                $className = current(explode('.', $file->getBasename()));
                $class = $namespace . '\\' . $className;
                $doc = new ControllerGenerator($class);
                $controllerVersion = $doc->findTag('version');
                if (!$controllerVersion || $controllerVersion != $version) {
                    continue;
                }
                $controllers[$dir . $className] = $doc;
            }
        }
    }
}
