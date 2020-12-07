<?php declare(strict_types=1);

namespace Pollen\FacebookApi;

use Pollen\FacebookApi\Contracts\FacebookApi as FacebookApiContract;
use tiFy\Container\ServiceProvider as BaseServiceProvider;

class FacebookApiServiceProvider extends BaseServiceProvider
{
    /**
     * Liste des noms de qualification des services fournis.
     * {@internal Permet le chargement différé des services qualifié.}
     * @var string[]
     */
    protected $provides = [
        FacebookApiContract::class
    ];

    /**
     * @inheritDoc
     */
    public function boot()
    {
        events()->listen('wp.booted', function () {
            $this->getContainer()->get(FacebookApiContract::class)->boot();
        });
    }

    /**
     * @inheritDoc
     */
    public function register(): void
    {
        $this->getContainer()->share(FacebookApiContract::class, function (): FacebookApiContract {
            return new FacebookApi(config('embed', []), $this->getContainer());
        });
    }
}
