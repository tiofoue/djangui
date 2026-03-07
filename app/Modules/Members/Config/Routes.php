<?php

declare(strict_types=1);

/** @var \CodeIgniter\Router\RouteCollection $routes */

$routes->group('api', ['namespace' => 'App\Modules\Members\Controllers'], function ($routes): void {
    // -------------------------------------------------------------------------
    // Membres d'une association
    // -------------------------------------------------------------------------
    $routes->get(
        'associations/(:num)/members',
        'MemberController::list/$1',
        ['filter' => 'auth']
    );
    $routes->get(
        'associations/(:num)/members/(:num)',
        'MemberController::detail/$1/$2',
        ['filter' => 'auth']
    );
    $routes->put(
        'associations/(:num)/members/(:num)/role',
        'MemberController::changeRole/$1/$2',
        ['filter' => 'auth']
    );
    $routes->delete(
        'associations/(:num)/members/(:num)',
        'MemberController::remove/$1/$2',
        ['filter' => 'auth']
    );

    // -------------------------------------------------------------------------
    // Invitations — scoped par association (auth requis)
    // -------------------------------------------------------------------------
    $routes->post(
        'associations/(:num)/invitations',
        'InvitationController::create/$1',
        ['filter' => 'auth']
    );
    $routes->get(
        'associations/(:num)/invitations',
        'InvitationController::list/$1',
        ['filter' => 'auth']
    );
    $routes->delete(
        'associations/(:num)/invitations/(:num)',
        'InvitationController::cancel/$1/$2',
        ['filter' => 'auth']
    );

    // -------------------------------------------------------------------------
    // Acceptation d'invitation — auth requise (utilisateur doit être connecté)
    // -------------------------------------------------------------------------
    $routes->addPlaceholder('invittoken', '[a-f0-9]{64}');
    $routes->post(
        'invitations/(:invittoken)/accept',
        'InvitationController::accept/$1',
        ['filter' => 'auth']
    );

    // -------------------------------------------------------------------------
    // Tableau de bord personnel cross-associations
    // -------------------------------------------------------------------------
    $routes->get(
        'me/overview',
        'MeController::overview',
        ['filter' => 'auth']
    );
});
