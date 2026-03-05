<?php declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUsersTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            // Clé primaire
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            // Identifiant universel unique
            'uuid' => ['type' => 'CHAR', 'constraint' => 36, 'null' => false],
            // Prénom
            'first_name' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            // Nom de famille
            'last_name' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            // Numéro de téléphone (identifiant principal de connexion)
            'phone' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => false],
            // Adresse e-mail (optionnelle)
            'email' => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true, 'default' => null],
            // Mot de passe hashé
            'password' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => false],
            // Chemin vers l'avatar
            'avatar' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'default' => null],
            // Compte actif
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => true, 'null' => false, 'default' => 1],
            // Super-administrateur plateforme
            'is_super_admin' => ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => true, 'null' => false, 'default' => 0],
            // Date de vérification du téléphone
            'phone_verified_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            // Date de vérification de l'e-mail
            'email_verified_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'created_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'updated_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            // Soft delete
            'deleted_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['uuid']);
        $this->forge->addUniqueKey(['phone']);
        $this->forge->addUniqueKey(['email']);
        $this->forge->addKey('deleted_at');

        $this->forge->createTable('users', true, [
            'ENGINE'          => 'InnoDB',
            'DEFAULT CHARSET' => 'utf8mb4',
            'COLLATE'         => 'utf8mb4_unicode_ci',
        ]);
    }

    public function down(): void
    {
        $this->forge->dropTable('users', true);
    }
}
