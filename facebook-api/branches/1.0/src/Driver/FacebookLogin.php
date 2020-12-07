<?php

namespace Pollen\FacebookApi\Driver;

use Pollen\FacebookApi\Contracts\Facebook;
use Pollen\FacebookApi\Contracts\FacebookLogin as FacebookFactoryLoginContract;

abstract class FacebookLogin implements FacebookFactoryLoginContract
{
    /**
     * Instance du gestionnaire.
     * @var Facebook
     */
    protected $manager;

    /**
     * CONSTRUCTEUR.
     *
     * @param Facebook $manager Instance du gestionnaire.
     *
     * @return void
     */
    public function __construct(Facebook $manager)
    {
        $this->manager = $manager;

        add_action('init', function () {
            events()->listen('api.facebook', [$this, 'process']);
        }, 999999);

        $this->boot();
    }

    /**
     * @inheritdoc
     */
    public function boot(): void
    {

    }

    /**
     * @inheritdoc
     */
    public function fb(): Facebook
    {
        return $this->manager;
    }

    /**
     * @inheritdoc
     */
    public function url($action = '', $permissions = ['email'], $redirect_url = ''): string
    {
        $helper = $this->fb()->getRedirectLoginHelper();

        return $helper->getLoginUrl(
            add_query_arg(['tify_api_fb' => $action], $redirect_url ? : home_url('/')),
            (array)$permissions
        );
    }
}