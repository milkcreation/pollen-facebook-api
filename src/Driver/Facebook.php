<?php

namespace Pollen\FacebookApi\Driver;

use Facebook\Facebook as FacebookSdk;
use Facebook\Authentication\AccessToken;
use Facebook\Authentication\AccessTokenMetadata;
use Facebook\Exceptions\FacebookSDKException;
use Illuminate\Support\Arr;
use Psr\Container\ContainerInterface;
use Pollen\FacebookApi\Contracts\Facebook as FacebookContract;
use WP_Error;

/**
 * Class Facebook
 * @package tiFy\Api\Facebook
 *
 * @see https://github.com/facebook/php-graph-sdk
 * @see https://developers.facebook.com/docs/php/howto/example_facebook_login
 */
class Facebook extends FacebookSdk implements FacebookContract
{
    /**
     * Instance de la classe.
     * @var self
     */
    private static $instance;

    /**
     * Classe de rappel du jeton d'accès d'un utilisateur connecté.
     * @var null|AccessToken
     */
    private $accessToken;

    /**
     * Liste des attributs de configuration.
     * @var array
     */
    protected $attributes = [];

    /**
     * Instance du conteneur d'injection de dépendances.
     * @var ContainerInterface
     */
    protected $container;

    /**
     * CONSTRUCTEUR.
     *
     * @param array $attrs {
     *      Liste des attributs de configuration du SDK Facebook
     *
     *      @type string $app_id (requis)
     *      @type string $app_secret
     *      @type string $default_graph_version
     *      @type bool $enable_beta_mode
     *      @type $http_client_handler
     *      @type $persistent_data_handler
     *      @type $pseudo_random_string_generator
     *      @type $url_detection_handler
     * }
     * @param ContainerInterface $container Instance du conteneur d'injection de dépendances.
     *
     * @return void
     *
     * @throws FacebookSDKException
     * }
     *
     */
    public function __construct(array $attrs, ContainerInterface $container)
    {
        $this->container = $container;

        // Traitement des attributs de configuration du SDK PHP Facebook permis.
        $this->attributes = array_intersect_key($attrs, array_flip([
            'app_id',
            'app_secret',
            'default_graph_version',
            'enable_beta_mode',
            'http_client_handler',
            'persistent_data_handler',
            'pseudo_random_string_generator',
            'url_detection_handler',
        ]));

        // Instanciation du SDK PHP Facebook
        parent::__construct($this->attributes);

        add_action('init', function (){
            foreach(['profile', 'signin', 'signup'] as $control) {
                $this->container->get("api.facebook.login.{$control}");
            }
        });

        add_action('wp_loaded', function () {
            if ($action = request()->get('tify_api_fb', '')) {
                return events()->trigger('api.facebook', [$action]);
            }
            return null;
        });
    }

    /**
     * @inheritdoc
     */
    public static function create(array $args, ContainerInterface $container): FacebookContract
    {
        return self::$instance = self::$instance instanceof static ? self::$instance : new static($args, $container);
    }

    /**
     * @inheritdoc
     */
    public function config($key, $default = null)
    {
        return Arr::get($this->attributes, $key, $default);
    }

    /**
     * @inheritdoc
     */
    public function connect($redirect_url = '')
    {
        /**
         * Classe de rappel du jeton d'authentification
         * @var null|AccessToken $accessToken
         */
        $accessToken = null;

        /**
         * Classe de rappel de traitement des métadonnées du jeton d'authentification
         * @var null|AccessTokenMetadata $tokenMetadata
         */
        $tokenMetadata = null;

        /**
         * Classe de rappel des erreurs de traitement
         * @var null|\WP_Error $error
         */
        $error = null;

        // Classe de rappel de redirection
        $helper = $this->getRedirectLoginHelper();
        if (isset($_GET['state'])) {
            $helper->getPersistentDataHandler()->set('state', $_GET['state']);
        }
        // Récupération du jeton d'accès
        try {
            $accessToken = $helper->getAccessToken($redirect_url);
        } catch (FacebookSDKException $e) {
            $error = new WP_Error(
                401,
                'Facebook SDK returned an error: ' . $e->getMessage(),
                ['title' => __('Le kit de développement Facebook renvoi une erreur', 'tify')]
            );
            return compact('accessToken', 'tokenMetadata', 'error');
        }

        // Bypass - La récupération du jeton d'accès tombe en échec
        if (!isset($accessToken)) {
            if ($helper->getError()) {
                $error = new WP_Error(
                    401,
                    "Error: " . $helper->getError() . "\n" .
                    "Error Code: " . $helper->getErrorCode() . "\n" .
                    "Error Reason: " . $helper->getErrorReason() . "\n" .
                    "Error Description: " . $helper->getErrorDescription() . "\n"
                );

                return compact('accessToken', 'tokenMetadata', 'error');
            } else {
                $error = new WP_Error(400, 'Bad request');

                return compact('accessToken', 'tokenMetadata', 'error');
            }
        }

        // Classe de rappel de traitement des jetons d'accès
        $oAuth2Client = $this->getOAuth2Client();

        // Classe de rappel de traitement des métadonnées de jetons
        $tokenMetadata = $oAuth2Client->debugToken($accessToken);

        // Contrôle de la correspondance entre l'app_id de l'api Facebook et celle du jeton
        try {
            $tokenMetadata->validateAppId($this->getAppId());
        } catch (FacebookSDKException $e) {
            $error = new WP_Error(
                $e->getCode(),
                $e->getMessage(),
                ['title' => __('Correspondance du jeton d\'accès en échec', 'tify')]
            );
            return compact('accessToken', 'tokenMetadata', 'error');
        }

        // Contrôle de la validité du jeton
        try {
            $tokenMetadata->validateExpiration();
        } catch (FacebookSDKException $e) {
            $error = new WP_Error(
                $e->getCode(),
                $e->getMessage(),
                ['title' => __('Expiration du jeton d\'accès', 'tify')]
            );
            return compact('accessToken', 'tokenMetadata', 'error');
        }

        // Tentative d'échange du jeton d'accès courte durée pour un jeton d'accès longue durée
        if (!$accessToken->isLongLived()) {
            try {
                $accessToken = $oAuth2Client->getLongLivedAccessToken($accessToken);
            } catch (FacebookSDKException $e) {
                $error = new WP_Error(
                    $e->getCode(),
                    "Error getting long-lived access token: " . $e->getMessage(),
                    ['title' => __('Récupération du jeton d\'accès longue durée en échec', 'tify')]
                );
                return compact('accessToken', 'tokenMetadata', 'error');
            }
        }

        // Bypass - La classe de rappel du jeton d'authentification n'est pas conforme
        if (!$accessToken instanceof AccessToken) {
            $error = new WP_Error(
                401,
                __('Impossible de définir le jeton d\'authentification Facebook.', 'tify'),
                ['title' => __('Récupération du jeton d\'accès en échec', 'tify')]
            );
            return compact('accessToken', 'tokenMetadata', 'error');
        }

        // Bypass - La classe de rappel de traitement des métadonnées du jeton d'authentification
        if (!$tokenMetadata instanceof AccessTokenMetadata) {
            $error = new WP_Error(
                401,
                __('Impossible de définir les données du jeton d\'authentification Facebook.', 'tify'),
                ['title' => __('Récupération des données du jeton d\'accès en échec', 'tify')]
            );
            return compact('accessToken', 'tokenMetadata', 'error');
        }

        // Mise en cache de la classe de rappel du jeton d'accès
        $this->accessToken = $accessToken;

        // Définition du jeton dans les variables de session
        $_SESSION['fb_access_token'] = (string)$this->accessToken;

        // Transmission de la réponse
        return compact('accessToken', 'tokenMetadata', 'error');
    }

    /**
     * @inheritdoc
     */
    public function clear(): void
    {
        if (!$id = request()->get('tify_api_fb_clear', false)) {
            return;
        }
        $_SESSION['fb_access_token'] = '';
    }

    /**
     * @inheritdoc
     */
    public function error(WP_Error $e): void
    {
        $data = $e->get_error_data();

        wp_die(
            $e->get_error_message(),
            (!empty($data['title']) ? $data['title'] : __('Processus en erreur', 'tify')),
            $e->get_error_code()
        );
        exit;
    }

    /**
     * @inheritdoc
     */
    public function getAppId(): string
    {
        return $this->config('app_id', '');
    }

    /**
     * @inheritdoc
     */
    public function userInfos(array $fields = ['id'])
    {
        if (!$this->accessToken instanceof AccessToken) {
            $error = new WP_Error(
                401,
                __('Impossible de définir le jeton d\'authentification Facebook.', 'tify'),
                ['title' => __('Récupération du jeton d\'accès en échec', 'tify')]
            );
            return compact('infos', 'error');
        }
        try {
            $response = $this->get('/me?fields=' . join(',', $fields), (string)$this->accessToken);
            $infos = $response->getGraphUser();
        } catch (FacebookSDKException $e) {
            $error = new WP_Error(
                $e->getCode(),
                'Erreur: ' . $e->getMessage(),
                ['title' => __('Erreur Facebook', 'tify')]
            );
        }
        return compact('infos', 'error');
    }
}