<?php declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateRefreshTokensTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            // Clé primaire
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            // Utilisateur propriétaire du refresh token
            'user_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false],
            // Hash du refresh token (stocké hashé pour la sécurité)
            'token_hash' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => false],
            // JWT ID (jti claim) — identifiant unique du JWT associé
            'jti' => ['type' => 'CHAR', 'constraint' => 36, 'null' => false],
            // Date d'expiration du refresh token
            'expires_at' => ['type' => 'DATETIME', 'null' => false],
            // Date de révocation anticipée (NULL = encore valide)
            'revoked_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'created_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['token_hash']);
        $this->forge->addUniqueKey(['jti']);
        // Index composite pour la vérification (user + non révoqué)
        $this->forge->addKey(['user_id', 'revoked_at']);
        $this->forge->addKey('expires_at');

        // FK → users (cascade suppression des tokens si user supprimé)
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('refresh_tokens', true, [
            'ENGINE'          => 'InnoDB',
            'DEFAULT CHARSET' => 'utf8mb4',
            'COLLATE'         => 'utf8mb4_unicode_ci',
        ]);
    }

    public function down(): void
    {
        $this->forge->dropTable('refresh_tokens', true);
    }
}
