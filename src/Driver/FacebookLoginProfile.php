<?php

namespace Pollen\FacebookApi\Driver;

use Facebook\Authentication\AccessToken;
use Facebook\Authentication\AccessTokenMetadata;
use WP_Error, WP_User, WP_User_Query;

class FacebookLoginProfile extends FacebookLogin
{
    /**
     * @inheritdoc
     */
    public function boot(): void
    {
        add_action('show_user_profile', function () {
            ?>
            <table class="form-table">
                <tr>
                    <th><?php _e('Affiliation à un compte Facebook', 'tify'); ?></th>
                    <td>
                        <?php $this->trigger(['redirect' => get_edit_profile_url()]); ?>
                    </td>
                </tr>
            </table>
            <?php
        });
    }

    /**
     * Vérification d'association d'un compte utilisateur à Facebook.
     *
     * @param int|WP_User $user
     *
     * @return bool
     */
    public function is($user = null)
    {
        if (!$user) {
            $user = wp_get_current_user();
        }
        if ($user instanceof WP_User) {
            $user_id = $user->ID;
        } else {
            $user_id = (int)$user;
        }
        return !empty(get_user_meta($user_id, '_facebook_user_id', true));
    }

    /**
     * @inheritdoc
     */
    public function process($action = 'profile'): void
    {
        // Bypass.
        if ($action !== 'profile') {
            return;
        }

        // Bypass - L'utilisateur est déjà authentifié.
        if (!is_user_logged_in()) {
            $this->fb()->error(new WP_Error(
                500,
                __('Action impossible, vous devez être connecté pour effectué cette action', 'tify'),
                ['title' => __('Authentification non trouvée', 'tify')]
            ));
            return;
        }

        // Récupération des données utilisateur
        $user_id = get_current_user_id();

        if (!$this->is($user_id)) {
            // Tentative de connection
            $response = $this->fb()->connect(add_query_arg(['tify_api_fb' => $action], get_edit_profile_url()));

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
            } elseif (!$fb_user_id = $tokenMetadata->getUserId()) {
                // Bypass - L'identifiant utilisateur Facebook n'est pas disponible.
                $this->fb()->error(new WP_Error(
                    401,
                    __('Impossible de de définir les données du jeton d\'authentification Facebook.', 'tify'),
                    ['title' => __('Récupération des données du jeton d\'accès en échec', 'tify')]
                ));
                return;
            }

            // Réquête de récupération d'utilisateur correspondant à l'identifiant Facebook
            $user_query = new WP_User_Query([
                'meta_query' => [
                    [
                        'key'   => '_facebook_user_id',
                        'value' => $fb_user_id
                    ]
                ]
            ]);

            // Bypass - Aucun utilisateur correspondant à l'identifiant utilisateur Facebook.
            if ($count = $user_query->get_total()) {
                $this->fb()->error(new WP_Error(
                    401,
                    __('Un utilisateur est déjà enregistré avec ce compte Facebook.', 'tify'),
                    ['title' => __('Utilisateur existant', 'tify')]
                ));
                return;
            }
            update_user_meta($user_id, '_facebook_user_id', $fb_user_id);
        } else {
            delete_user_meta($user_id, '_facebook_user_id');
        }

        // Redirection.
        wp_redirect(get_edit_profile_url());
        exit;
    }

    /**
     * @inheritdoc
     */
    public function url($action = 'profile', $permissions = ['email'], $redirect_url = ''): string
    {
        return parent::url($action, $permissions, $redirect_url ?: get_edit_profile_url());
    }

    /**
     * @inheritdoc
     */
    public function trigger($action = 'profile', $args = []): string
    {
        $args = array_merge([
            'permissions'  => ['email'],
            'content'      => "<span 
                class=\"dashicons dashicons-facebook-alt\" style=\"line-height:28px;\"></span>&nbsp;" .
                (!$this->is()
                    ? __('Associer avec Facebook', 'tify')
                    : __('Dissocier de Facebook', 'tify')
                ),
            'attrs'        => [
                'class' => 'button-primary'
            ],
            'redirect_url' => get_edit_profile_url()
        ], $args);

        return (string) partial('tag', [
            'tag'     => 'a',
            'content' => $args['content'],
            'attrs'   => $args['attrs']
        ]);
    }
}