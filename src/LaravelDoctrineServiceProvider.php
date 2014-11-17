<?php namespace Mitch\LaravelDoctrine;

use App;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\ORM\Tools\Setup;
use Doctrine\Common\EventManager;
use Illuminate\Auth\AuthManager;
use Illuminate\Support\ServiceProvider;
use Mitch\LaravelDoctrine\Cache;
use Mitch\LaravelDoctrine\Configuration\DriverMapper;
use Mitch\LaravelDoctrine\Configuration\SqlMapper;
use Mitch\LaravelDoctrine\Configuration\SqliteMapper;
use Mitch\LaravelDoctrine\EventListeners\SoftDeletableListener;
use Mitch\LaravelDoctrine\Filters\TrashedFilter;

class LaravelDoctrineServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     * @var bool
     */
    protected $defer = false;

    public function boot()
    {
        $this->package('mitchellvanw/laravel-doctrine', 'doctrine', __DIR__ . '/..');
        $this->extendAuthManager();
    }

    /**
     * Register the service provider.
     * @return void
     */
    public function register()
    {
        $this->registerConfigurationMapper();
        $this->registerCacheManager();
        $this->registerEntityManager();
        $this->registerClassMetadataFactory();

        $this->commands(array(
            'Mitch\LaravelDoctrine\Console\GenerateProxiesCommand',
            'Mitch\LaravelDoctrine\Console\SchemaCreateCommand',
            'Mitch\LaravelDoctrine\Console\SchemaUpdateCommand',
            'Mitch\LaravelDoctrine\Console\SchemaDropCommand'
        ));
    }

    /**
     * The driver mapper's instance needs to be accessible from anywhere in the application,
     * for registering new mapping configurations or other storage libraries.
     */
    private function registerConfigurationMapper() 
    {
        $this->app->bind('Mitch\LaravelDoctrine\Configuration\DriverMapper', function () {
            $mapper = new DriverMapper;
            $mapper->registerMapper(new SqlMapper);
            $mapper->registerMapper(new SqliteMapper);
            return $mapper;
        });
    }

    public function registerCacheManager()
    {
        $this->app->bind('Mitch\LaravelDoctrine\CacheManager', function ($app) {
            $manager = new CacheManager($app['config']['doctrine::doctrine.cache']);
            $manager->add(new Cache\ApcProvider);
            $manager->add(new Cache\MemcacheProvider);
            $manager->add(new Cache\RedisProvider);
            $manager->add(new Cache\XcacheProvider);
            $manager->add(new Cache\NullProvider);
            return $manager;
        });
    }

    private function registerEntityManager()
    {
        $that = $this;
		
		$this->app->singleton('Doctrine\ORM\EntityManager', function ($app) use ($that) {
            $config = $app['config']['doctrine::doctrine'];
            $metadata = Setup::createAnnotationMetadataConfiguration(
                $config['metadata'],
                $app['config']['app.debug'],
                $config['proxy']['directory'],
                $app['Mitch\LaravelDoctrine\CacheManager']->getCache($config['cache_provider']),
                $config['simple_annotations']
            );
            $metadata->addFilter('trashed', 'Mitch\LaravelDoctrine\Filters\TrashedFilter');
            $metadata->setAutoGenerateProxyClasses($config['proxy']['auto_generate']);
            $metadata->setDefaultRepositoryClassName($config['repository']);
            $metadata->setSQLLogger($config['logger']);

            if (isset($config['proxy']['namespace']))
                $metadata->setProxyNamespace($config['proxy']['namespace']);

            $eventManager = new EventManager;
            $eventManager->addEventListener(Events::onFlush, new SoftDeletableListener);
            $entityManager = EntityManager::create($that->mapLaravelToDoctrineConfig($app['config']), $metadata, $eventManager);
            $entityManager->getFilters()->enable('trashed');
            return $entityManager;
        });
        $this->app->singleton('Doctrine\ORM\EntityManagerInterface', 'Doctrine\ORM\EntityManager');
    }

    private function registerClassMetadataFactory()
    {
        $this->app->singleton('Doctrine\ORM\Mapping\ClassMetadataFactory', function ($app) {
            return $app['Doctrine\ORM\EntityManager']->getMetadataFactory();
        });
    }

    private function extendAuthManager()
    {
        $this->app['Illuminate\Auth\AuthManager']->extend('doctrine', function ($app) {
            return new DoctrineUserProvider(
                $app['Illuminate\Hashing\HasherInterface'],
                $app['Doctrine\ORM\EntityManager'],
                $app['config']['auth.model']
            );
        });
    }

    /**
     * Get the services provided by the provider.
     * @return array
     */
    public function provides()
    {
        return array(
            'Mitch\LaravelDoctrine\CacheManager',
            'Doctrine\ORM\EntityManagerInterface',
            'Doctrine\ORM\EntityManager',
            'Doctrine\ORM\Mapping\ClassMetadataFactory',
            'Mitch\LaravelDoctrine\Configuration\DriverMapper',
            'Illuminate\Auth\AuthManager',
        );
    }

    /**
     * Map Laravel's to Doctrine's database configuration requirements.
     * @param $config
     * @throws \Exception
     * @return array
     */
    public function mapLaravelToDoctrineConfig($config)
    {
        $default = $config['database.default'];
        $connection = $config["database.connections.{$default}"];
        return App::make('Mitch\LaravelDoctrine\Configuration\DriverMapper')->map($connection);
    }
}
