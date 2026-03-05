<?php declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePasswordResetsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            // Clé primaire
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            // Téléphone du demandeur (optionnel si reset par email)
            'phone' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'default' => null],
            // Email du demandeur (optionnel si reset par téléphone)
            'email' => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true, 'default' => null],
            // Jeton de réinitialisation (unique, à usage unique)
            'token' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            // Date d'expiration du jeton
            'expires_at' => ['type' => 'DATETIME', 'null' => false],
            // Date d'utilisation (NULL = pas encore utilisé)
            'used_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'created_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['token']);
        $this->forge->addKey('phone');
        $this->forge->addKey('email');
        $this->forge->addKey('expires_at');

        // Pas de FK sur cette table (indépendante pour faciliter le nettoyage)
        $this->forge->createTable('password_resets', true, [
            'ENGINE'          => 'InnoDB',
            'DEFAULT CHARSET' => 'utf8mb4',
            'COLLATE'         => 'utf8mb4_unicode_ci',
        ]);
    }

    public function down(): void
    {
        $this->forge->dropTable('password_resets', true);
    }
}
