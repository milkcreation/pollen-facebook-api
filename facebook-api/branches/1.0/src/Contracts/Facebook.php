<?php

namespace Pollen\FacebookApi\Contracts;

use Psr\Container\ContainerInterface;
use Facebook\Exceptions\FacebookSDKException;
use Facebook\Facebook as FacebookSdk;
use WP_Error;

/**
 * Interface Facebook
 * @package tiFy\Api\Facebook
 *
 * @mixin FacebookSdk
 */
interface Facebook
{
    /**
     * Instanciation de la classe.
     *
     * @param array $attrs Liste des attributs de configuration.
     * @param ContainerInterface $container Conteneur d'injection de dépendance.
     *
     * @return static
     *
     * @throws FacebookSDKException
     */
    public static function create(array $args, ContainerInterface $container): Facebook;

    /**
     * Récupération d'un attribut de configuration.
     *
     * @param string $key Clé de qualification de l'attribut.
     * @param mixed $default Valeur de retoru par défaut.
     *
     * @return mixed
     */
    public function config(string $key, $default = null);

    /**
     * @inheritdoc
     */
    public function connect($redirect_url = '');

    /**
     * Déconnection de Facebook.
     *
     * @return void
     */
    public function clear(): void;

    /**
     * Affichage des message d'erreurs.
     *
     * @param WP_Error $e
     *
     * @return void
     */
    public function error(WP_Error $e): void;

    /**
     * Récupération de l'App ID.
     *
     * @return string
     */
    public function getAppId(): string;

    /**
     * Récupération d'informations utilisateur.
     * @see https://developers.facebook.com/docs/graph-api/reference/user/
     * @see https://developers.facebook.com/docs/php/howto/example_retrieve_user_profile
     *
     * @param array $fields Tableau indexés des champs à récupérer.
     *
     * @return array
     */
    public function userInfos(array $fields = ['id']);
}