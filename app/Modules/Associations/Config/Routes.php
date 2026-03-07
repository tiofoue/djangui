<?php

declare(strict_types=1);

/** @var \CodeIgniter\Router\RouteCollection $routes */

$routes->group('api', ['namespace' => 'App\Modules\Associations\Controllers'], function ($routes): void {
    // -------------------------------------------------------------------------
    // Routes publiques authentifiées — associations
    // -------------------------------------------------------------------------
    $routes->get('associations', 'AssociationController::getMine', ['filter' => 'auth']);
    $routes->post('associations', 'AssociationController::create', ['filter' => 'auth']);
    $routes->get('associations/(:num)', 'AssociationController::getById/$1', ['filter' => 'auth']);
    $routes->put('associations/(:num)', 'AssociationController::update/$1', ['filter' => 'auth']);
    $routes->delete('associations/(:num)', 'AssociationController::delete/$1', ['filter' => 'auth']);
    $routes->get('associations/(:num)/settings', 'SettingsController::getSettings/$1', ['filter' => 'auth']);
    $routes->put('associations/(:num)/settings', 'SettingsController::updateSettings/$1', ['filter' => 'auth']);
    $routes->get('associations/(:num)/children', 'AssociationController::getChildren/$1', ['filter' => 'auth']);

    // -------------------------------------------------------------------------
    // Routes admin — réservées aux super_admin (contrôle dans le contrôleur)
    // -------------------------------------------------------------------------
    $routes->get('admin/associations', 'AssociationController::adminGetAll', ['filter' => 'auth']);
    $routes->get('admin/associations/pending', 'AssociationController::adminGetPending', ['filter' => 'auth']);
    $routes->put('admin/associations/(:num)/approve', 'AssociationController::adminApprove/$1', ['filter' => 'auth']);
    $routes->put('admin/associations/(:num)/reject', 'AssociationController::adminReject/$1', ['filter' => 'auth']);
    $routes->put('admin/associations/(:num)/suspend', 'AssociationController::adminSuspend/$1', ['filter' => 'auth']);
    $routes->put('admin/associations/(:num)/reinstate', 'AssociationController::adminReinstate/$1', ['filter' => 'auth']);
});
