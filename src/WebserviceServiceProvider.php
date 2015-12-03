<?php

namespace Fortean\Webservice;

use Fortean\Webservice\Webservice;
use Fortean\Webservice\CustomXmlHandler;
use Fortean\Webservice\CustomJsonHandler;

use Httpful\Httpful;
use Httpful\Mime as HttpfulMime;

use Illuminate\Support\ServiceProvider;

class WebserviceServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

    /**
     * Bootstrap the service provider
     * 
     * @return void
     */
    public function boot()
    {
        // Publish a demo service description
        $this->publishes([
            __DIR__.'/../config/httpbin.php' => config_path('webservice/httpbin.php'),
        ]);

        // Register our custom parsers with Httpful
        Httpful::register(HttpfulMime::XML, new CustomXmlHandler);
        Httpful::register(HttpfulMime::JSON, new CustomJsonHandler);
    }

    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('fortean.webservice', function ($app) {
            return new Webservice();
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['fortean.webservice'];
    }
}