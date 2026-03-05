<?php declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAssociationsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            // Clé primaire
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            // Identifiant universel unique
            'uuid' => ['type' => 'CHAR', 'constraint' => 36, 'null' => false],
            // Nom officiel de l'entité
            'name' => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => false],
            // Slug URL unique
            'slug' => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => false],
            // Description libre
            'description' => ['type' => 'TEXT', 'null' => true, 'default' => null],
            // Devise / slogan
            'slogan' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'default' => null],
            // Chemin vers le logo
            'logo' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'default' => null],
            // Téléphone de contact
            'phone' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'default' => null],
            // Adresse physique
            'address' => ['type' => 'TEXT', 'null' => true, 'default' => null],
            // Boîte postale
            'bp' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'default' => null],
            // Numéro contribuable / fiscal
            'tax_number' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'default' => null],
            // Numéro d'autorisation légale
            'auth_number' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'default' => null],
            // Pays (code ISO 2 lettres)
            'country' => ['type' => 'CHAR', 'constraint' => 2, 'null' => false, 'default' => 'CM'],
            // Devise monétaire (code ISO 3 lettres)
            'currency' => ['type' => 'CHAR', 'constraint' => 3, 'null' => false, 'default' => 'XAF'],
            // Type d'entité
            'type' => [
                'type'       => 'ENUM',
                'constraint' => ['tontine_group', 'association', 'federation'],
                'null'       => false,
                'default'    => 'association',
            ],
            // Entité parente (fédération ou association mère)
            'parent_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'default' => null],
            // Statuts en texte intégral
            'statutes_text' => ['type' => 'LONGTEXT', 'null' => true, 'default' => null],
            // Chemin vers le fichier statuts PDF
            'statutes_file' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true, 'default' => null],
            // Statut de validation de l'entité
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['pending_review', 'active', 'rejected', 'suspended'],
                'null'       => false,
                'default'    => 'pending_review',
            ],
            // Motif de rejet éventuel
            'rejection_reason' => ['type' => 'TEXT', 'null' => true, 'default' => null],
            // Administrateur plateforme ayant effectué la révision
            'reviewed_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'default' => null],
            // Date de révision
            'reviewed_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            // Créateur de l'entité (obligatoire)
            'created_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false],
            'created_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'updated_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            // Soft delete
            'deleted_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['uuid']);
        $this->forge->addUniqueKey(['slug']);
        $this->forge->addKey('parent_id');
        $this->forge->addKey('status');
        $this->forge->addKey('type');
        $this->forge->addKey('created_by');
        $this->forge->addKey('reviewed_by');
        $this->forge->addKey('deleted_at');

        // FK vers la table parente (auto-référence)
        $this->forge->addForeignKey('parent_id', 'associations', 'id', 'RESTRICT', 'CASCADE');
        // FK vers le réviseur (super-admin)
        $this->forge->addForeignKey('reviewed_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        // FK vers le créateur
        $this->forge->addForeignKey('created_by', 'users', 'id', 'RESTRICT', 'CASCADE');

        $this->forge->createTable('associations', true, [
            'ENGINE'          => 'InnoDB',
            'DEFAULT CHARSET' => 'utf8mb4',
            'COLLATE'         => 'utf8mb4_unicode_ci',
        ]);
    }

    public function down(): void
    {
        $this->forge->dropTable('associations', true);
    }
}
