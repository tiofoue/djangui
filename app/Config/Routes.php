<?php

use CodeIgniter\Router\RouteCollection;

/**
 * Routes globales — Djangui API
 * Chaque module enregistre ses propres routes via son Config/Routes.php
 *
 * @var RouteCollection $routes
 */

// Options par défaut
$routes->setDefaultNamespace('App');
$routes->setDefaultController('Home');
$routes->setDefaultMethod('index');
$routes->setTranslateURIDashes(false);
$routes->set404Override();

// Désactiver le routing auto (sécurité — routes explicites uniquement)
$routes->setAutoRoute(false);

// -----------------------------------------------------------------------
// Chargement des routes de chaque module HMVC
// -----------------------------------------------------------------------
$moduleRoutes = [
    APPPATH . 'Modules/Auth/Config/Routes.php',
    APPPATH . 'Modules/Associations/Config/Routes.php',
    APPPATH . 'Modules/Bureau/Config/Routes.php',
    APPPATH . 'Modules/Members/Config/Routes.php',
    APPPATH . 'Modules/Tontines/Config/Routes.php',
    APPPATH . 'Modules/Loans/Config/Routes.php',
    APPPATH . 'Modules/Solidarity/Config/Routes.php',
    APPPATH . 'Modules/Documents/Config/Routes.php',
    APPPATH . 'Modules/Notifications/Config/Routes.php',
    APPPATH . 'Modules/Reports/Config/Routes.php',
    APPPATH . 'Modules/Plans/Config/Routes.php',
];

foreach ($moduleRoutes as $routeFile) {
    if (file_exists($routeFile)) {
        require $routeFile;
    }
}
