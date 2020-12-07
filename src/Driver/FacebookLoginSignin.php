<?php

namespace Pollen\FacebookApi\Driver;

use Facebook\Authentication\AccessToken;
use Facebook\Authentication\AccessTokenMetadata;
use WP_Error, WP_User_Query;

class FacebookLoginSignin extends FacebookLogin
{
    /**
     * @inheritdoc
     */
    public function process($action = 'signin'): void
    {
        // Bypass.
        if ($action !== 'signin') {
            return;
        }

        // Tentative de connection.
        $response = $this->fb()->connect(add_query_arg(['tify_api_fb' => $action], home_url('/')));

        /**
         * @var AccessToken|null $accessToken
         * @var AccessTokenMetadata|null $tokenMetadata
         * @var WP_Error|null $error
         * @var string $action
         * @var string $redirect
         */
        extract($response);

        if ($error instanceof WP_Error) {
            // Bypass - La demande d'authentification Facebook retourne des erreurs.
            $this->fb()->error($error);
            return;
        } elseif (is_user_logged_in()) {
            // Bypass - L'utilisateur est déjà authentifié.
            $this->fb()->error(new WP_Error(
                500,
                __('Action impossible, vous êtes déjà authentifié sur le site', 'tify'),
                ['title' => __('Authentification existante', 'tify')]
            ));
            return;
        } elseif (!$fb_user_id = $tokenMetadata->getUserId()) {
            // Bypass - L'identifiant utilisateur Facebook n'est pas disponible.
            $this->fb()->error(new WP_Error(
                401,
                __('Impossible de de définir les données du jeton d\'authentification Facebook.', 'tify'),
                ['title' => __('Récupération des données du jeton d\'accès en échec', 'tify')]
            ));
            return;
        }

        // Réquête de récupération d'utilisateur correspondant à l'identifiant Facebook.
        $user_query = new WP_User_Query([
            'meta_query' => [
                [
                    'key'   => '_facebook_user_id',
                    'value' => $fb_user_id,
                ],
            ],
        ]);

        if (!$count = $user_query->get_total()) {
            // Bypass - Aucun utilisateur correspondant à l'identifiant utilisateur Facebook.
            $this->fb()->error(new WP_Error(
                401,
                __('Aucun utilisateur ne correspond à votre compte Facebook.', 'tify'),
                ['title' => __('Utilisateur non trouvé', 'tify')]
            ));
            return;
        } elseif ($count > 1) {
            $this->fb()->error(new WP_Error(
                401,
                __('ERREUR SYSTEME : Votre compte Facebook semble être associé à plusieurs compte > Authentification impossible.',
                    'tify'),
                ['title' => __('Nombre d\'utilisateurs trouvés, invalide', 'tify')]
            ));
            return;
        }
        $results = $user_query->get_results();

        // Définition des données utilisateur.
        $user = reset($results);

        // Authentification.
        wp_clear_auth_cookie();
        wp_set_auth_cookie((int)$user->ID);

        // Redirection.
        wp_redirect(home_url('/'));
        exit;
    }

    /**
     * @inheritdoc
     */
    public function trigger($action = 'signin', $args = []): string
    {
        $args = array_merge([
            'permissions'  => ['email'],
            'content'      => __('Connexion avec Facebook', 'tify'),
            'attrs'        => [],
            'redirect_url' => home_url('/'),
        ], $args);

        $url = $this->url($action, $args['permissions'], $args['redirect_url']);

        $args['attrs']['href'] = esc_url($url);
        $args['attrs']['title'] = empty($args['attrs']['title']) ? $args['content'] : $args['attrs']['title'];
        $args['attrs']['class'] = empty($args['attrs']['class'])
            ? 'FacebookTrigger' : 'FacebookTrigger ' . $args['attrs']['class'];

        return (string) partial('tag', [
            'tag'     => 'a',
            'attrs'   => $args['attrs'],
            'content' => $args['content'],
        ]);
    }
}