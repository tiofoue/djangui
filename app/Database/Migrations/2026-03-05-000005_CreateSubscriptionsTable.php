<?php declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSubscriptionsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            // Clé primaire
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            // Association abonnée (une seule souscription active à la fois)
            'association_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false],
            // Plan souscrit
            'plan_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false],
            // État de la souscription
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['trial', 'active', 'expired', 'cancelled'],
                'null'       => false,
                'default'    => 'trial',
            ],
            // Fin de la période d'essai
            'trial_ends_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            // Début de la période de facturation courante
            'current_period_start' => ['type' => 'DATETIME', 'null' => false],
            // Fin de la période de facturation courante
            'current_period_end' => ['type' => 'DATETIME', 'null' => false],
            // Méthode de paiement (ex: mtn_money, orange_money)
            'payment_method' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true, 'default' => null],
            // Date d'annulation
            'cancelled_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'created_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'updated_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);

        $this->forge->addKey('id', true);
        // Une association ne peut avoir qu'une seule souscription
        $this->forge->addUniqueKey(['association_id']);
        $this->forge->addKey('plan_id');
        $this->forge->addKey('status');

        // FK → associations (cascade suppression)
        $this->forge->addForeignKey('association_id', 'associations', 'id', 'CASCADE', 'CASCADE');
        // FK → plans (protéger le plan contre la suppression)
        $this->forge->addForeignKey('plan_id', 'plans', 'id', 'RESTRICT', 'CASCADE');

        $this->forge->createTable('subscriptions', true, [
            'ENGINE'          => 'InnoDB',
            'DEFAULT CHARSET' => 'utf8mb4',
            'COLLATE'         => 'utf8mb4_unicode_ci',
        ]);
    }

    public function down(): void
    {
        $this->forge->dropTable('subscriptions', true);
    }
}
