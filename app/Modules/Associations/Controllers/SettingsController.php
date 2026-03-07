<?php

declare(strict_types=1);

namespace App\Modules\Associations\Controllers;

use App\Common\BaseController;
use App\Modules\Associations\Services\AssociationService;
use App\Services\AuthContext;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Contrôleur des settings d'association.
 *
 * Délègue toute la logique métier à AssociationService.
 *
 * Routes :
 *   GET /api/associations/{id}/settings → getSettings()
 *   PUT /api/associations/{id}/settings → updateSettings()
 */
class SettingsController extends BaseController
{
    private AssociationService $service;

    public function __construct()
    {
        $this->service = new AssociationService();
    }

    // =========================================================================
    // GET /api/associations/{id}/settings
    // =========================================================================

    /**
     * Retourne tous les settings d'une association.
     *
     * @param int $id Identifiant de l'association
     *
     * @return ResponseInterface
     */
    public function getSettings(int $id): ResponseInterface
    {
        /** @var object $jwt */
        $jwt    = AuthContext::get();
        $userId = (int) ($jwt->sub ?? 0);

        try {
            $result = $this->service->getSettings($id, $userId);

            return $this->respond($result);
        } catch (\RuntimeException $e) {
            $message = $e->getMessage();

            if (str_contains($message, 'introuvable')) {
                return $this->respondNotFound($message);
            }

            if (str_contains($message, 'Accès refusé')) {
                return $this->respondForbidden($message);
            }

            return $this->respondError($message, 422);
        }
    }

    // =========================================================================
    // PUT /api/associations/{id}/settings
    // =========================================================================

    /**
     * Met à jour ou crée des settings pour une association.
     *
     * Body JSON :
     * {
     *   "settings": [
     *     { "label": "Frais d'adhésion", "value": "5000", "is_custom": true },
     *     { "key": "max_members", "label": "Nombre maximum de membres", "value": "50", "is_custom": false }
     *   ]
     * }
     *
     * @param int $id Identifiant de l'association
     *
     * @return ResponseInterface
     */
    public function updateSettings(int $id): ResponseInterface
    {
        $body     = $this->request->getJSON(true) ?? [];
        $settings = $body['settings'] ?? null;

        // Validation : settings doit être un tableau non vide
        if (!is_array($settings) || empty($settings)) {
            return $this->respondValidationError([
                'settings' => 'Le champ settings est obligatoire et doit être un tableau non vide.',
            ]);
        }

        // Validation de chaque entrée
        $errors = [];

        foreach ($settings as $index => $entry) {
            if (!is_array($entry)) {
                $errors["settings.{$index}"] = 'Chaque entrée doit être un objet.';
                continue;
            }

            $label = (string) ($entry['label'] ?? '');

            if ($label === '') {
                $errors["settings.{$index}.label"] = 'Le label est obligatoire.';
            } elseif (mb_strlen($label) > 255) {
                $errors["settings.{$index}.label"] = 'Le label ne peut pas dépasser 255 caractères.';
            }
        }

        if (!empty($errors)) {
            return $this->respondValidationError($errors);
        }

        /** @var object $jwt */
        $jwt    = AuthContext::get();
        $userId = (int) ($jwt->sub ?? 0);

        try {
            $result = $this->service->updateSettings($id, $settings, $userId);

            return $this->respond($result, 200, 'Settings mis à jour.');
        } catch (\RuntimeException $e) {
            $message = $e->getMessage();

            if (str_contains($message, 'introuvable')) {
                return $this->respondNotFound($message);
            }

            if (str_contains($message, 'Accès refusé')) {
                return $this->respondForbidden($message);
            }

            return $this->respondError($message, 422);
        }
    }
}
