<?php
/**
 * This file is part of bops
 *
 * @copyright Copyright (C) 2020 Jayson Wang
 * @license   MIT License
 * @link      https://github.com/wjiec/php-bops
 */
namespace Bops\Provider\Config;

use Bops\Config\Factory;
use Bops\Config\Loader\Adapter\LocalDirectory;
use Bops\Provider\AbstractServiceProvider;
use League\Flysystem\Filesystem;
use League\Flysystem\Plugin\ListFiles;


/**
 * Class ServiceProvider
 *
 * @package Bops\Provider\Config
 */
class ServiceProvider extends AbstractServiceProvider {

    /**
     * Name of the service
     *
     * @return string
     */
    public function name(): string {
        return 'config';
    }

    /**
     * Register the service
     *
     * @return void
     */
    public function register() {
        $this->di->setShared($this->name(), function() {
            /* @var $filesystem Filesystem */
            $filesystem = container('filesystem', container('navigator')->configDir());
            $filesystem->addPlugin(new ListFiles());

            return (new Factory('global', new LocalDirectory($filesystem->getAdapter()->getPathPrefix())))
                ->load(array_map(function(array $file) {
                    return pathinfo($file['path'], PATHINFO_FILENAME);
                }, $filesystem->listFiles()));
        });
    }

}
