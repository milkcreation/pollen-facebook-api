<?php

namespace Pollen\FacebookApi\Driver;

use Facebook\Authentication\AccessToken;
use Facebook\Authentication\AccessTokenMetadata;
use WP_Error, WP_User_Query;

class FacebookLoginSignup extends FacebookLogin
{
    /**
     * @inheritdoc
     */
    public function process($action = 'signup'): void
    {
        // Bypass.
        if ($action !== 'signup') {
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
                    ['title' => __('Authentification existante', 'tify')])
            );
            return;
        } elseif (!$fb_user_id = $tokenMetadata->getUserId()) {
            // Bypass - L'identifiant utilisateur Facebook n'est pas disponible.
            $this->fb()->error(new WP_Error(
                    401,
                    __('Impossible de définir les données du jeton d\'authentification Facebook.', 'tify'),
                    ['title' => __('Récupération des données du jeton d\'accès en échec', 'tify')])
            );
            return;
        }

        // Réquête de récupération d'utilisateur correspondant à l'identifiant Facebook
        $user_query = new WP_User_Query([
            'meta_query' => [
                [
                    'key'   => '_facebook_user_id',
                    'value' => $fb_user_id,
                ],
            ],
        ]);

        // Bypass - Aucun utilisateur correspondant à l'identifiant utilisateur Facebook.
        if ($count = $user_query->get_total()) {
            $this->fb()->error(new WP_Error(
                    401,
                    __('Un utilisateur est déjà enregistré avec ce compte Facebook.', 'tify'),
                    ['title' => __('Utilisateur existant', 'tify')])
            );
            return;
        }

        // Récupération des informations utilisateur.
        $response = $this->fb()->userInfos(['id', 'email', 'name', 'first_name', 'last_name', 'short_name']);
        if (is_wp_error($response['error'])) {
            $this->fb()->error($response['error']);
            return;
        }

        // Cartographie des données utilisateur.
        $userdata = [
            'user_login'   => 'fb-' . $response['infos']['id'],
            'user_pass'    => '',
            'user_email'   => $response['infos']['email'],
            'display_name' => $response['infos']['name'],
            'first_name'   => $response['infos']['first_name'],
            'last_name'    => $response['infos']['last_name'],
            'nickname'     => $response['infos']['short_name'],
            'role'         => 'subscriber',
        ];
        $user_id = wp_insert_user($userdata);

        if (is_wp_error($user_id)) {
            $this->fb()->error($user_id);
            return;
        } elseif (update_user_meta($user_id, '_facebook_user_id', $response['infos']['id'])) {
            // Authentification.
            wp_clear_auth_cookie();
            wp_set_auth_cookie((int)$user_id);
            // Redirection.
            wp_redirect(home_url('/'));
            exit;
        }
    }

    /**
     * @inheritdoc
     */
    public function trigger($action = 'signup', $args = []): string
    {
        $args = array_merge([
            'permissions'  => ['email'],
            'content'      => __('Inscription avec Facebook', 'tify'),
            'attrs'        => [],
            'redirect_url' => home_url('/'),
        ], $args);

        $url = $this->url($action, $args['permissions'], $args['redirect_url']);

        $args['attrs']['href'] = esc_url($url);
        $args['attrs']['title'] = empty($args['attrs']['title'])
            ? $args['content']
            : $args['attrs']['title'];
        $args['attrs']['class'] = empty($args['attrs']['class'])
            ? 'FacebookTrigger'
            : 'FacebookTrigger ' . $args['attrs']['class'];

        return (string) partial('tag', [
            'tag'     => 'a',
            'attrs'   => $args['attrs'],
            'content' => $args['content'],
        ]);
    }
}