<?php

declare(strict_types=1);

/** @var \CodeIgniter\Router\RouteCollection $routes */

$routes->group('api', function ($routes): void {
    // -------------------------------------------------------------------------
    // Plans — liste publique (pas d'authentification requise)
    // -------------------------------------------------------------------------
    $routes->get('plans', 'App\Modules\Plans\Controllers\SubscriptionController::getPlans');

    // -------------------------------------------------------------------------
    // Souscriptions — authentification requise
    // -------------------------------------------------------------------------
    $routes->get(
        'associations/(:num)/subscription',
        'App\Modules\Plans\Controllers\SubscriptionController::getSubscription/$1',
        ['filter' => 'auth']
    );
    $routes->post(
        'associations/(:num)/subscription',
        'App\Modules\Plans\Controllers\SubscriptionController::subscribe/$1',
        ['filter' => 'auth']
    );
    $routes->delete(
        'associations/(:num)/subscription',
        'App\Modules\Plans\Controllers\SubscriptionController::cancel/$1',
        ['filter' => 'auth']
    );
});
