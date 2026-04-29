<?php

namespace App\Providers;

use App\Listeners\LogMailSent;
use App\Mail\Transport\GraphTransport;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\AzureBlobStorage\AzureBlobStorageAdapter;
use League\Flysystem\Filesystem;
use MicrosoftAzure\Storage\Blob\BlobRestProxy;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ── Listener global: loguear todos los mails enviados ─────
        Event::listen(MessageSent::class, LogMailSent::class);

        // ── Transporte Microsoft Graph (Mail.Send via Azure AD) ───
        Mail::extend('graph', function () {
            return new GraphTransport(
                tenantId:     config('services.msgraph.tenant_id'),
                clientId:     config('services.msgraph.client_id'),
                clientSecret: config('services.msgraph.client_secret'),
                fromEmail:    config('mail.from.address'),
            );
        });

        Storage::extend('azure', function ($app, $config) {
            $connectionString = sprintf(
                'DefaultEndpointsProtocol=https;AccountName=%s;AccountKey=%s;EndpointSuffix=core.windows.net',
                $config['name'],
                $config['key']
            );

            $client = BlobRestProxy::createBlobService($connectionString);
            $adapter = new AzureBlobStorageAdapter($client, $config['container'], $config['prefix'] ?? '');
            $diskConfig = $config;

            return new class(new Filesystem($adapter), $adapter, $diskConfig) extends FilesystemAdapter {
                public function url($path): string
                {
                    if (isset($this->config['prefix'])) {
                        $path = $this->concatPathToUrl($this->config['prefix'], $path);
                    }

                    if (isset($this->config['url'])) {
                        return $this->concatPathToUrl($this->config['url'], $path);
                    }

                    return parent::url($path);
                }
            };
        });
    }
}
