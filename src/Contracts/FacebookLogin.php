<?php

namespace Pollen\FacebookApi\Contracts;

interface FacebookLogin
{
    /**
     * Initialisation du controleur.
     *
     * @return void
     */
    public function boot(): void;

    /**
     * Récupération de l'instance du gestionnaire.
     *
     * @return Facebook
     */
    public function fb(): Facebook;

    /**
     * Traitement.
     *
     * @param string $action Nom de qualification de l'action.
     *
     * @return void
     */
    public function process($action = ''): void;

    /**
     * Url de l'action.
     *
     * @param string $action Nom de qualification de l'action.
     * @param array $permissions Liste des permissions accordées (scope).
     * @param string $redirect_url Url de retour.
     *
     * @return string
     */
    public function url($action = '', $permissions = ['email'], $redirect_url = ''): string;

    /**
     * Bouton de lancement de l'action.
     *
     * @param string $action Nom de qualification de l'action.
     * @param array $attrs Liste des attributs de configuration
     *
     * @return string
     */
    public function trigger($action = '', $attrs = []): string;
}