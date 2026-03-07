<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration corrective — ajout du champ language sur la table users.
 *
 * Raison : support bilingue FR/EN (langues officielles du Cameroun).
 * La langue pilote les SMS OTP, notifications et PDF.
 */
class AddLanguageToUsers extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('users', [
            'language' => [
                'type'       => 'ENUM',
                'constraint' => ['fr', 'en'],
                'null'       => false,
                'default'    => 'fr',
                'after'      => 'avatar',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('users', 'language');
    }
}
