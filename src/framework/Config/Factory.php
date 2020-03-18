<?php
/**
 * This file is part of bops
 *
 * @copyright Copyright (C) 2020 Jayson Wang
 * @license   MIT License
 * @link      https://github.com/wjiec/php-bops
 */
namespace Bops\Config;

use Bops\Config\Loader\LoaderInterface;
use League\Flysystem\Filesystem;
use Phalcon\Config;


/**
 * Class Factory
 *
 * @package Bops\Config
 */
class Factory {

    /**
     * The name of the service
     *
     * @var string
     */
    protected $name;

    /**
     * The configure directory
     *
     * @var LoaderInterface
     */
    protected $loader;

    /**
     * The prefix string of cache filename
     *
     * @var string
     */
    protected static $prefix = '__';

    /**
     * The suffix string of cache filename
     *
     * @var string
     */
    protected static $suffix = '__';

    /**
     * Factory constructor.
     *
     * @param string $name The name of the factory and using cache filename
     * @param LoaderInterface $loader
     */
    public function __construct(string $name, LoaderInterface $loader) {
        $this->name = join('', [self::$prefix, $name, self::$suffix]);
        $this->loader = $loader;
    }

    /**
     * Load all modules and returns a config
     *
     * @param array $configs
     * @return Config
     */
    public function load(array $configs = []): Config {
        $config = new Config();
        $basedir = container('navigator')->configCacheDir();

        /* @var $filesystem Filesystem */
        $filesystem = container('filesystem', container('navigator')->configCacheDir());
        if ($filesystem->has("{$this->name}.php") && !container('environment')->contains('development')) {
            return static::merge($config, $basedir . "/{$this->name}.php");
        }

        foreach ($configs as $cfg) {
            static::merge($config, $this->loader->pathOf("{$cfg}.php"), ($cfg === 'config' ? null : $cfg));
        }

        static::dump($filesystem, "{$this->name}.php", $config->toArray());
        return $config;
    }

    /**
     * Merge other configure to main object
     *
     * @param Config $config
     * @param string $path
     * @param string|null $mount
     * @return Config
     */
    private static function merge(Config $config, string $path, ?string $mount = null): Config {
        /** @noinspection PhpIncludeInspection */
        $value = include $path;
        if (is_array($value)) {
            $value = new Config($value);
        }

        if ($value instanceof Config) {
            if (!$mount) {
                return $config->merge($value);
            }
            $config[$mount] = (new Config())->merge($value);
        }
        return $config;
    }

    /**
     * Dump the configure to cache file
     *
     * @param Filesystem $filesystem
     * @param string $file
     * @param array $data
     */
    private static function dump(Filesystem $filesystem, string $file, array $data): void {
        $contents = '<?php' . PHP_EOL
            . '/* !! PLEASE DO NOT EDIT THIS FILE DIRECTLY !! */' . PHP_EOL
            . 'return ' . var_export($data, true) . ';' . PHP_EOL;
        $filesystem->put($file, $contents, $data);
    }


}
