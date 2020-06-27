<?php

namespace SSMix;

use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Flushable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Path;
use SilverStripe\View\Requirements;
use SilverStripe\View\SSViewer;
use SilverStripe\View\TemplateGlobalProvider;
use SilverStripe\View\ThemeResourceLoader;

class Mix implements Flushable, TemplateGlobalProvider
{
    use Configurable;
    use Injectable;

    private static $mix_manifest = 'dist/mix-manifest.json';
    private static $hot_file     = 'dist/hot';
    private static $manifest     = [];
    private static $hotPath;

    private static $dependencies = [
        'cache' => '%$' . CacheInterface::class . '.Mix',
    ];
    /**
     * @var CacheInterface
     */
    public $cache;

    /**
     * @return self
     */
    public static function inst(): self
    {
        return static::singleton();
    }

    /**
     * @return bool|mixed
     * @throws InvalidArgumentException
     */
    private function getManifest()
    {
        $manifest = !$this->getIsHot() ? $this->cache->get('mix-manifest') : false;
        if (!$manifest) {
            $manifestFile     = Config::inst()->get(__CLASS__, 'mix_manifest');
            $manifestFilePath = ThemeResourceLoader::inst()->findThemedResource($manifestFile, SSViewer::get_themes());
            if ($manifestFilePath && Director::fileExists($manifestFilePath)) {
                $manifestString = file_get_contents(Director::getAbsFile($manifestFilePath));
                $manifest       = json_decode($manifestString, true);
                if (!$this->getIsHot()) {
                    $this->cache->set('mix-manifest', $manifest);
                }
            }
        }

        return $manifest;
    }

    private function getHotPath()
    {
        if (static::$hotPath === null) {
            $hotFile         = Config::inst()->get(__CLASS__, 'hot_file');
            $hotFilePath     = ThemeResourceLoader::inst()->findThemedResource($hotFile, SSViewer::get_themes());
            static::$hotPath = $hotFilePath && Director::fileExists($hotFilePath) ? trim(file_get_contents(Director::getAbsFile($hotFilePath))) : null;
        }
        return static::$hotPath;
    }

    private function getIsHot(): bool
    {
        return $this->getHotPath() !== null;
    }

    /**
     * @param $path
     * @return mixed|null
     * @throws InvalidArgumentException
     */
    private function getPathFromManifest($path)
    {
        $cacheKey = str_replace(['{', '}', '(', ')', '/', '\\', '@', ':'], '.', $path);
        $result   = !$this->getIsHot() ? $this->cache->get($cacheKey) : null;
        if (!$result) {
            $manifest = $this->getManifest();
            $result   = $manifest[$path] ?? null;
            if (!$this->getIsHot()) {
                $this->cache->set($cacheKey, $result);
            }
        }
        return $result;
    }

    /**
     * @param $path
     * @return string|null
     * @throws InvalidArgumentException
     */
    private function resolve($path): ?string
    {
        if (strpos($path, 'http') !== 0) {
            $manifestPath = $this->getPathFromManifest($path);
            if ($manifestPath) {
                if ($this->getIsHot()) {
                    $path = Controller::join_links($this->getHotPath(), $manifestPath);
                } else {
                    $parts = parse_url($manifestPath);
                    $path  = ThemeResourceLoader::inst()->findThemedResource(Path::join('dist', $parts['path']), SSViewer::get_themes());
                    if (isset($parts['query'])) {
                        $path .= '?' . $parts['query'];
                    }
                }
            }
        }

        return $path;
    }

    /**
     * @param $path
     * @param array $options
     * @throws InvalidArgumentException
     */
    public static function mix($path, $options = []): void
    {
        $ext  = pathinfo($path, PATHINFO_EXTENSION);
        $path = static::inst()->resolve($path);
        if ($path) {
            if ($ext === 'css') {
                Requirements::css($path, $options);
            } else if ($ext === 'js') {
                Requirements::javascript($path, $options);
            } else {
                trigger_error("mix({$path}): Unsupported file extension {$ext}. Only js and css supported", E_USER_NOTICE);
            }
        }
    }

    /**
     * @inheritDoc
     */
    public static function flush(): void
    {
        static::inst()->cache->clear();
    }

    /**
     * @inheritDoc
     */
    public static function get_template_global_variables(): array
    {
        return [
            'mix',
        ];
    }
}
