<?php
/**
 * This file is part of bops
 *
 * @copyright Copyright (C) 2020 Jayson Wang
 * @license   MIT License
 * @link      https://github.com/wjiec/php-bops
 */
namespace Bops;

use Bops\Application\ApplicationInterface;
use Bops\Exception\Framework\Bootstrap\UnknownApplicationException;
use Bops\Navigator\NavigatorInterface;
use Bops\Provider\Config\ServiceProvider as ConfigServiceProvider;
use Bops\Provider\Environment\ServiceProvider as EnvironmentServiceProvider;
use Bops\Provider\ErrorHandler\ServiceProvider as ErrorHandlerServiceProvider;
use Bops\Provider\EventsManager\ServiceProvider as EventsManagerServiceProvider;
use Bops\Provider\Filesystem\ServiceProvider as FilesystemServiceProvider;
use Bops\Provider\ServiceProviderInstaller;
use Dotenv\Dotenv;
use Dotenv\Exception\InvalidPathException;
use League\Flysystem\Filesystem;
use Phalcon\Di;
use Phalcon\Di\FactoryDefault;
use Phalcon\DiInterface;


/**
 * Class Bootstrap
 *
 * @package Bops
 */
class Bootstrap {

    /**
     * Dependency injection manager
     *
     * @var DiInterface
     */
    protected $di;

    /**
     * Bootstrap constructor.
     *
     * @param NavigatorInterface $navigator
     * @throws Exception\Provider\EmptyServiceNameException
     */
    public function __construct(NavigatorInterface $navigator) {
        $this->di = new FactoryDefault();
        Di::setDefault($this->di);

        $this->di->setShared('bootstrap', $this);
        $this->di->setShared('navigator', $navigator);

        $this->setupEnvironment($navigator);
        ServiceProviderInstaller::setup(new ErrorHandlerServiceProvider($this->di));
        ServiceProviderInstaller::setup(new EventsManagerServiceProvider($this->di));
        $this->setupServices($navigator);
    }

    /**
     * Run the application
     *
     * @return string
     * @throws UnknownApplicationException
     */
    public function run(): string {
        if ($application = container('application')) {
            if ($application instanceof ApplicationInterface) {
                /** @noinspection PhpUndefinedMethodInspection */
                return $application->handle()->getContent();
            }
        }
        throw new UnknownApplicationException('The application service does not defined');
    }

    /**
     * Setup the application environment
     *
     * @param NavigatorInterface $navigator
     * @throws Exception\Provider\EmptyServiceNameException
     */
    protected function setupEnvironment(NavigatorInterface $navigator) {
        try {
            Dotenv::createMutable($navigator->rootDir())->load();
            if ($env = env('BOPS_ENVIRONMENT', 'development')) {
                ServiceProviderInstaller::setup(new EnvironmentServiceProvider($this->di));
                container('environment', $env);

                Dotenv::createMutable($navigator->rootDir(), [".env.{$env}"])->load();
            }
        } catch (InvalidPathException $e) {}
    }

    /**
     * Setup the service from users
     *
     * @param NavigatorInterface $navigator
     * @throws Exception\Provider\EmptyServiceNameException
     */
    protected function setupServices(NavigatorInterface $navigator) {
        $this->setupBuiltInServices();
        /* @var $filesystem Filesystem */
        if ($filesystem = container('filesystem', $navigator->configDir())) {
            if ($filesystem->has('providers.php')) {
                /** @noinspection PhpIncludeInspection */
                $providers = include $navigator->configDir('providers.php');
                if (is_array($providers) && !empty($providers)) {
                    $this->setupServiceProviders($providers);
                }
            }

            if ($filesystem->has('services.php')) {
                /** @noinspection PhpIncludeInspection */
                $services = include $navigator->configDir('services.php');
                if (is_array($services) && !empty($providers)) {
                    $this->setRawServices($services);
                }
            }
        }
    }

    /**
     * Setup the built in services
     *
     * @throws Exception\Provider\EmptyServiceNameException
     */
    protected function setupBuiltInServices() {
        ServiceProviderInstaller::setup(new FilesystemServiceProvider($this->di));
        ServiceProviderInstaller::setup(new ConfigServiceProvider($this->di));
    }

    /**
     * Initializing the service providers
     *
     * @param array $providers
     */
    protected function setupServiceProviders(array $providers) {
        foreach ($providers as $provider) {
            ServiceProviderInstaller::setup(new $provider($this->di));
        }
    }

    /**
     * Initializing the raw services
     *
     * @param array $services
     */
    protected function setRawServices(array $services) {
        foreach ($services as $name => $service) {
            $this->di->setShared($name, $service);
        }
    }

}