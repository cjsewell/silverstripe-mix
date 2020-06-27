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
    private static $hotPath      = null;

    private static $dependencies = [
        'cache' => '%$' . CacheInterface::class . '.Mix',
    ];
    /**
     * @var CacheInterface
     */
    public $cache;

    /**
     * @var array
     */
    public $deferredCss = [];
    /**
     * @var array
     */
    public $deferredJs = [];


    /**
     * @return self
     */
    public static function inst(): self
    {
        return static::singleton();
    }

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

    public function getIsHot()
    {
        return $this->getHotPath() !== null;
    }


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

    private function resolve($path)
    {
        if (strpos($path, 'http') !== 0) {
            $manifestPath = $this->getPathFromManifest($path);
            if ($manifestPath) {
                if ($this->getIsHot()) {
                    $path = \SilverStripe\Control\Controller::join_links($this->getHotPath(), $manifestPath);
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

    public function addDeferredCss($path, $options = [])
    {
        $path = static::inst()->resolve($path);

        if ($path) {
            if (!preg_match('{^(//)|(http[s]?:)}', $path) || !Director::is_root_relative_url($path)) {
                $path = @Injector::inst()->get(ResourceURLGenerator::class)->urlForResource($path);
            }
            if (!in_array($path, $this->deferredCss, true)) {
                $preLoadTag  = HTML::createTag('link', [
                    'rel'         => 'preload',
                    'as'          => 'style',
                    'href'        => $path,
                    'integrity'   => $options['integrity'] ?? null,
                    'crossorigin' => $options['crossorigin'] ?? null,
                    'media'       => $options['media'] ?? null,
                    'onload'      => "this.onload=null;this.rel='stylesheet'",
                ]);
                $noScriptTag = '<noscript>' . HTML::createTag('link', ['rel' => 'stylesheet', 'href' => $path,]) . '</noscript>';
                Requirements::insertHeadTags($preLoadTag . PHP_EOL . $noScriptTag . PHP_EOL);
                $this->deferredCss[] = $path;
            }
        }
    }

    public static function deferCss($path, $options = [])
    {

        static::inst()->addDeferredCss($path, $options);
    }

    public static function DeferredCss()
    {
        $result    = new ArrayList();
        $noScripts = [];
        foreach (static::inst()->deferredCss as $href => $options) {
            self::inst()->deferredCss[] = $href;
            $integrity                  = $options['integrity'] ?? null;
            $crossorigin                = $options['crossorigin'] ?? null;
            $media                      = $options['media'] ?? null;
            $preLoadTag                 = HTML::createTag('link', [
                'rel'         => 'preload',
                'as'          => 'style',
                'href'        => $href,
                'integrity'   => $integrity,
                'crossorigin' => $crossorigin,
                'media'       => $media,
                'onload'      => "this.onload=null;this.rel='stylesheet'",
            ]);
            $noScripts[]                = HTML::createTag('link', [
                'rel'  => 'stylesheet',
                'href' => $href,
            ]);
            $result->push(ArrayData::create(['Tag' => DBField::create_field(DBHTMLText::class, $preLoadTag)]));
        }
        $result->push(ArrayData::create(['Tag' => DBField::create_field(DBHTMLText::class, '<noscript>' . implode(PHP_EOL, $noScripts) . '</noscript>')]));

        return $result;
    }

    public static function mix($path, $options = [])
    {
        $ext  = pathinfo($path, PATHINFO_EXTENSION);
        $path = static::inst()->resolve($path);
        if ($path) {
            if ($ext === 'css') {
                Requirements::css($path, $options);
            } elseif ($ext === 'js') {
                Requirements::javascript($path, $options);
            }
        }
    }

    public static function Defer($path, $options = [])
    {
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        if ($ext === 'css') {
            static::deferCss($path, $options);
        } elseif ($ext === 'js') {
            static::mix($path, array_merge(['defer' => true], $options));
        }
    }


    /**
     * @inheritDoc
     */
    public static function flush()
    {
        /** @var static $self */
        static::singleton()->cache->clear();
    }

    /**
     * @inheritDoc
     */
    public static function get_template_global_variables()
    {
        return [
            'mix',
            'DeferredCss',
            'Defer',
        ];
    }
}
