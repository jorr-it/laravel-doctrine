<?php

return array(
    'simple_annotations' => false,

    'metadata' => array(
        base_path('app/models')
    ),

    'proxy' => array(
        'auto_generate' => false,
        'directory'     => null,
        'namespace'     => null
    ),

    // Available: null, apc, xcache, redis, memcache
    'cache_provider' => null,

    'cache' => array(
        'redis' => array(
            'host'     => '127.0.0.1',
            'port'     => 6379,
            'database' => 1
        ),
        'memcache' => array(
            'host' => '127.0.0.1',
            'port' => 11211
        )
    ),

    'repository' => 'Doctrine\ORM\EntityRepository',

    'repositoryFactory' => null,

    'logger' => null
);
