<?php

namespace EasyAws;

use Aws\Credentials\CredentialProvider;
use Aws\S3\S3Client;
use Aws\Sns\SnsClient;
use Aws\Sqs\SqsClient;
use EasyAws\Cache\Adapter;
use EasyAws\Queue\SqsConnector;
use Illuminate\Cache\CacheManager;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot(QueueManager $queueManager)
    {
        $this->publishes([__DIR__ . '/../config/easyaws.php' => config_path('easyaws.php')]);

        $queueManager->extend('sqs', function () {
            return new SqsConnector();
        });
    }

    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/easyaws.php', 'easyaws');

        $this->app->singleton('easyaws.credentials', function ($app) {
            $credentialsCache = [
                'credentials' => new Adapter($app->make(CacheManager::class), config('easyaws.cache_store')),
                'client' => config('easyaws.http_client'), // NOTE: used for unit testing only
            ];
            return CredentialProvider::defaultProvider($credentialsCache);
        });
        $this->app->singleton(S3Client::class, $this->getAwsClientClosure('s3'));
        $this->app->singleton(SnsClient::class, $this->getAwsClientClosure('sns'));
        $this->app->singleton(
            SqsClient::class,
            $this->getAwsClientClosure('sqs', ['http' => ['timeout' => 60, 'connect_timeout' => 60]])
        );
    }

    /**
     * Get a closure for binding into the laravel container that will create the provider AWS client.
     *
     * @param string $client The AWS client to retrieve
     * @return callable|\Closure For binding a concrete class using this factory.
     */
    protected function getAwsClientClosure(string $client, array $config = [])
    {
        return function ($app) use ($client, $config) {
            $config = array_merge(['credentials' => $app->make('easyaws.credentials')], $config);
            return $app->make('aws')->createClient($client, $config);
        };
    }
}
