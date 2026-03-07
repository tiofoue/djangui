<?php

declare(strict_types=1);

namespace Tests\Feature\Members;

use App\Modules\Members\Services\MemberService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Database\Seeds\MembersTestSeeder;

/**
 * Tests d'intégration — MemberService.
 *
 * Couverture :
 *   - getMembers   : success, accès refusé
 *   - getMember    : success, introuvable
 *   - changeRole   : success, non-président, self-modify, assigner president, tontine_group
 *   - removeMember : success (soft-delete), non-président, self-remove, remove president
 *   - invite       : success, non-secrétaire, aucun contact, doublon, tontine_group
 *   - cancelInvitation : success, non-secrétaire, non-pending
 *   - acceptInvitation : success (nouveau), success (réactivation), expiré, mauvais destinataire, déjà membre
 *   - getOverview  : success
 *
 * DB de test : djangui_test (isolée, configurée dans phpunit.xml)
 * Isolation : $refresh = true → migrate:fresh avant la classe ; $seedOnce = true → seed une fois
 */
final class MemberServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    /**
     * Utilise la DB principale (djangui) — pas de DB de test séparée.
     * Le MembersTestSeeder truncate les tables avant chaque test, garantissant
     * un état propre sans avoir à recréer les tables via migrate:fresh.
     */
    protected $migrate   = false;
    protected $refresh   = false;
    protected $seedOnce  = false;
    protected $seed      = MembersTestSeeder::class;

    // -------------------------------------------------------------------------
    // IDs résolus après seed
    // -------------------------------------------------------------------------

    private int $assocId;
    private int $tontineId;
    private int $presidentId;
    private int $secretaryId;
    private int $auditorId;
    private int $memberId;
    private int $outsiderId;
    private int $futureMemberId;

    // -------------------------------------------------------------------------
    // setUp
    // -------------------------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolveIds();
    }

    /**
     * Résout les IDs insérés par le seeder depuis la DB de test.
     */
    private function resolveIds(): void
    {
        $db = $this->getTestDB();

        $assocs = $db->table('associations')->select('id, type')->orderBy('id')->get()->getResultArray();

        foreach ($assocs as $row) {
            if ($row['type'] === 'association') {
                $this->assocId = (int) $row['id'];
            } else {
                $this->tontineId = (int) $row['id'];
            }
        }

        $phones = [
            '+237610000001' => 'presidentId',
            '+237610000002' => 'secretaryId',
            '+237610000003' => 'auditorId',
            '+237610000004' => 'memberId',
            '+237610000005' => 'outsiderId',
            '+237610000006' => 'futureMemberId',
        ];

        $users = $db->table('users')->select('id, phone')->get()->getResultArray();

        foreach ($users as $row) {
            $prop = $phones[$row['phone']] ?? null;
            if ($prop !== null) {
                $this->{$prop} = (int) $row['id'];
            }
        }
    }

    /**
     * Retourne la connexion DB principale.
     */
    private function getTestDB(): \CodeIgniter\Database\BaseConnection
    {
        return \Config\Database::connect();
    }

    // =========================================================================
    // getMembers
    // =========================================================================

    public function testGetMembersReturnsPaginatedList(): void
    {
        $service = new MemberService($this->assocId);
        $result  = $service->getMembers($this->presidentId, 1, 20);

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('meta', $result);
        $this->assertCount(4, $result['data']); // president, secretary, auditor, member
        $this->assertSame(4, $result['meta']['total']);
    }

    public function testGetMembersThrowsWhenRequesterIsNotMember(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Accès refusé/');

        $service = new MemberService($this->assocId);
        $service->getMembers($this->outsiderId, 1, 20);
    }

    // =========================================================================
    // getMember
    // =========================================================================

    public function testGetMemberReturnsTargetData(): void
    {
        $service = new MemberService($this->assocId);
        $result  = $service->getMember($this->presidentId, $this->memberId);

        $this->assertArrayHasKey('user_id', $result);
        $this->assertSame($this->memberId, (int) $result['user_id']);
        $this->assertSame('member', $result['effective_role']);
    }

    public function testGetMemberThrowsWhenTargetNotInAssociation(): void
    {
        $this->expectException(\RuntimeException::class);

        $service = new MemberService($this->assocId);
        $service->getMember($this->presidentId, $this->outsiderId);
    }

    // =========================================================================
    // changeRole
    // =========================================================================

    public function testChangeRoleSucceedsForPresident(): void
    {
        $service = new MemberService($this->assocId);
        $result  = $service->changeRole($this->presidentId, $this->memberId, 'auditor');

        $this->assertSame('auditor', $result['effective_role']);

        // Restaurer pour les tests suivants
        $service->changeRole($this->presidentId, $this->memberId, 'member');
    }

    public function testChangeRoleThrowsWhenRequesterIsNotPresident(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Accès refusé/');

        $service = new MemberService($this->assocId);
        $service->changeRole($this->secretaryId, $this->memberId, 'auditor');
    }

    public function testChangeRoleThrowsWhenModifyingOwnRole(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/propre rôle/');

        $service = new MemberService($this->assocId);
        $service->changeRole($this->presidentId, $this->presidentId, 'member');
    }

    public function testChangeRoleThrowsWhenAssigningPresidentRole(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/président/i');

        $service = new MemberService($this->assocId);
        $service->changeRole($this->presidentId, $this->memberId, 'president');
    }

    public function testChangeRoleThrowsForInvalidRoleInTontineGroup(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/tontine_group/');

        $service = new MemberService($this->tontineId);
        $service->changeRole($this->presidentId, $this->memberId, 'auditor');
    }

    // =========================================================================
    // removeMember
    // =========================================================================

    public function testRemoveMemberSoftDeletesActiveMember(): void
    {
        // Créer un membre temporaire pour le retirer sans affecter le seed
        $db  = $this->getTestDB();
        $now = gmdate('Y-m-d H:i:s');

        $db->table('association_members')->insert([
            'association_id' => $this->assocId,
            'user_id'        => $this->outsiderId,
            'effective_role' => 'member',
            'joined_at'      => $now,
            'is_active'      => 1,
        ]);

        $service = new MemberService($this->assocId);
        $service->removeMember($this->presidentId, $this->outsiderId);

        $row = $db->table('association_members')
            ->where('association_id', $this->assocId)
            ->where('user_id', $this->outsiderId)
            ->get()->getRowArray();

        $this->assertSame(0, (int) $row['is_active']);
        $this->assertNotNull($row['left_at']);

        // Nettoyage
        $db->table('association_members')
            ->where('association_id', $this->assocId)
            ->where('user_id', $this->outsiderId)
            ->delete();
    }

    public function testRemoveMemberThrowsWhenRequesterIsNotPresident(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Accès refusé/');

        $service = new MemberService($this->assocId);
        $service->removeMember($this->secretaryId, $this->memberId);
    }

    public function testRemoveMemberThrowsWhenRemovingOneself(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/vous-même/');

        $service = new MemberService($this->assocId);
        $service->removeMember($this->presidentId, $this->presidentId);
    }

    public function testRemoveMemberThrowsWhenRemovingPresident(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/président/i');

        // Créer un second président temporaire pour tester la règle
        $db  = $this->getTestDB();
        $now = gmdate('Y-m-d H:i:s');

        $db->table('association_members')->insert([
            'association_id' => $this->assocId,
            'user_id'        => $this->outsiderId,
            'effective_role' => 'president',
            'joined_at'      => $now,
            'is_active'      => 1,
        ]);

        try {
            $service = new MemberService($this->assocId);
            $service->removeMember($this->presidentId, $this->outsiderId);
        } finally {
            $db->table('association_members')
                ->where('association_id', $this->assocId)
                ->where('user_id', $this->outsiderId)
                ->delete();
        }
    }

    // =========================================================================
    // invite
    // =========================================================================

    public function testInviteCreatesInvitationWithPhone(): void
    {
        $service = new MemberService($this->assocId);
        $result  = $service->invite($this->secretaryId, [
            'phone' => '+237699999001',
            'role'  => 'member',
        ]);

        $this->assertArrayHasKey('token', $result);
        $this->assertSame('pending', $result['status']);
        $this->assertSame($this->assocId, (int) $result['association_id']);

        // Nettoyage
        $this->getTestDB()->table('invitations')
            ->where('token', $result['token'])->delete();
    }

    public function testInviteThrowsWhenRequesterIsMember(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Accès refusé/');

        $service = new MemberService($this->assocId);
        $service->invite($this->memberId, ['phone' => '+237699999002', 'role' => 'member']);
    }

    public function testInviteThrowsWhenNoContactProvided(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/téléphone ou un email/');

        $service = new MemberService($this->assocId);
        $service->invite($this->secretaryId, ['role' => 'member']);
    }

    public function testInviteThrowsOnDuplicatePendingInvitation(): void
    {
        $service = new MemberService($this->assocId);

        // Première invitation
        $result = $service->invite($this->secretaryId, [
            'phone' => '+237699999003',
            'role'  => 'member',
        ]);

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessageMatches('/invitation en attente/');

            // Doublon : même téléphone
            $service->invite($this->secretaryId, [
                'phone' => '+237699999003',
                'role'  => 'member',
            ]);
        } finally {
            $this->getTestDB()->table('invitations')
                ->where('token', $result['token'])->delete();
        }
    }

    public function testInviteThrowsForInvalidRoleInTontineGroup(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/tontine_group/');

        // Le secrétaire n'est pas dans le tontine_group, utiliser le président
        $service = new MemberService($this->tontineId);
        $service->invite($this->presidentId, [
            'phone' => '+237699999004',
            'role'  => 'auditor', // invalide pour tontine_group
        ]);
    }

    // =========================================================================
    // cancelInvitation
    // =========================================================================

    public function testCancelInvitationSetsCancelledStatus(): void
    {
        $db  = $this->getTestDB();
        $now = gmdate('Y-m-d H:i:s');

        $db->table('invitations')->insert([
            'association_id' => $this->assocId,
            'invited_by'     => $this->secretaryId,
            'phone'          => '+237699998001',
            'token'          => bin2hex(random_bytes(32)),
            'role'           => 'member',
            'status'         => 'pending',
            'expires_at'     => gmdate('Y-m-d H:i:s', strtotime('+7 days')),
            'created_at'     => $now,
        ]);
        $invitId = $db->insertID();

        $service = new MemberService($this->assocId);
        $service->cancelInvitation($this->secretaryId, $invitId);

        $row = $db->table('invitations')->where('id', $invitId)->get()->getRowArray();
        $this->assertSame('cancelled', $row['status']);

        // Nettoyage
        $db->table('invitations')->where('id', $invitId)->delete();
    }

    public function testCancelInvitationThrowsWhenRequesterIsMember(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Accès refusé/');

        $service = new MemberService($this->assocId);
        $service->cancelInvitation($this->memberId, 999);
    }

    public function testCancelInvitationThrowsWhenNotPending(): void
    {
        $db  = $this->getTestDB();
        $now = gmdate('Y-m-d H:i:s');

        $db->table('invitations')->insert([
            'association_id' => $this->assocId,
            'invited_by'     => $this->secretaryId,
            'phone'          => '+237699998002',
            'token'          => bin2hex(random_bytes(32)),
            'role'           => 'member',
            'status'         => 'accepted',
            'expires_at'     => gmdate('Y-m-d H:i:s', strtotime('+7 days')),
            'created_at'     => $now,
        ]);
        $invitId = $db->insertID();

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessageMatches('/en attente/');

            $service = new MemberService($this->assocId);
            $service->cancelInvitation($this->secretaryId, $invitId);
        } finally {
            $db->table('invitations')->where('id', $invitId)->delete();
        }
    }

    // =========================================================================
    // acceptInvitation
    // =========================================================================

    public function testAcceptInvitationCreatesNewMembership(): void
    {
        $db      = $this->getTestDB();
        $now     = gmdate('Y-m-d H:i:s');
        $token   = bin2hex(random_bytes(32));

        $db->table('invitations')->insert([
            'association_id' => $this->assocId,
            'invited_by'     => $this->secretaryId,
            'phone'          => '+237610000006', // futureMember phone
            'token'          => $token,
            'role'           => 'member',
            'status'         => 'pending',
            'expires_at'     => gmdate('Y-m-d H:i:s', strtotime('+7 days')),
            'created_at'     => $now,
        ]);
        $invitId = $db->insertID();

        $invitation = $db->table('invitations')->where('id', $invitId)->get()->getRowArray();

        $service = new MemberService($this->assocId);
        $result  = $service->acceptInvitation($invitation, $this->futureMemberId);

        $this->assertSame($this->futureMemberId, (int) $result['user_id']);
        $this->assertSame('member', $result['effective_role']);

        // Vérifier que l'invitation est marquée accepted
        $inv = $db->table('invitations')->where('id', $invitId)->get()->getRowArray();
        $this->assertSame('accepted', $inv['status']);

        // Nettoyage
        $db->table('association_members')
            ->where('association_id', $this->assocId)
            ->where('user_id', $this->futureMemberId)
            ->delete();
        $db->table('invitations')->where('id', $invitId)->delete();
    }

    public function testAcceptInvitationReactivatesFormerMember(): void
    {
        $db  = $this->getTestDB();
        $now = gmdate('Y-m-d H:i:s');

        // Simuler un ancien membre inactif
        $db->table('association_members')->insert([
            'association_id' => $this->assocId,
            'user_id'        => $this->futureMemberId,
            'effective_role' => 'member',
            'joined_at'      => gmdate('Y-m-d H:i:s', strtotime('-1 year')),
            'left_at'        => $now,
            'is_active'      => 0,
        ]);

        $token = bin2hex(random_bytes(32));
        $db->table('invitations')->insert([
            'association_id' => $this->assocId,
            'invited_by'     => $this->secretaryId,
            'phone'          => '+237610000006',
            'token'          => $token,
            'role'           => 'treasurer',
            'status'         => 'pending',
            'expires_at'     => gmdate('Y-m-d H:i:s', strtotime('+7 days')),
            'created_at'     => $now,
        ]);
        $invitId = $db->insertID();

        $invitation = $db->table('invitations')->where('id', $invitId)->get()->getRowArray();

        $service = new MemberService($this->assocId);
        $result  = $service->acceptInvitation($invitation, $this->futureMemberId);

        $this->assertSame(1, (int) $result['is_active']);
        $this->assertSame('treasurer', $result['effective_role']);

        // Nettoyage
        $db->table('association_members')
            ->where('association_id', $this->assocId)
            ->where('user_id', $this->futureMemberId)
            ->delete();
        $db->table('invitations')->where('id', $invitId)->delete();
    }

    public function testAcceptInvitationThrowsWhenExpired(): void
    {
        $db    = $this->getTestDB();
        $token = bin2hex(random_bytes(32));

        $db->table('invitations')->insert([
            'association_id' => $this->assocId,
            'invited_by'     => $this->secretaryId,
            'phone'          => '+237699997001',
            'token'          => $token,
            'role'           => 'member',
            'status'         => 'pending',
            'expires_at'     => gmdate('Y-m-d H:i:s', strtotime('-1 day')), // expiré
            'created_at'     => gmdate('Y-m-d H:i:s', strtotime('-8 days')),
        ]);
        $invitId = $db->insertID();

        $invitation = $db->table('invitations')->where('id', $invitId)->get()->getRowArray();

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessageMatches('/expir/');

            $service = new MemberService($this->assocId);
            $service->acceptInvitation($invitation, $this->futureMemberId);
        } finally {
            $db->table('invitations')->where('id', $invitId)->delete();
        }
    }

    public function testAcceptInvitationThrowsWhenWrongRecipient(): void
    {
        $db    = $this->getTestDB();
        $token = bin2hex(random_bytes(32));

        $db->table('invitations')->insert([
            'association_id' => $this->assocId,
            'invited_by'     => $this->secretaryId,
            'phone'          => '+237699997002', // numéro qui n'est pas celui de futureMember
            'token'          => $token,
            'role'           => 'member',
            'status'         => 'pending',
            'expires_at'     => gmdate('Y-m-d H:i:s', strtotime('+7 days')),
            'created_at'     => gmdate('Y-m-d H:i:s'),
        ]);
        $invitId = $db->insertID();

        $invitation = $db->table('invitations')->where('id', $invitId)->get()->getRowArray();

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessageMatches('/destiné/');

            $service = new MemberService($this->assocId);
            // futureMember a le phone +237610000006 qui ne correspond pas à +237699997002
            $service->acceptInvitation($invitation, $this->futureMemberId);
        } finally {
            $db->table('invitations')->where('id', $invitId)->delete();
        }
    }

    public function testAcceptInvitationThrowsWhenAlreadyActiveMember(): void
    {
        $db    = $this->getTestDB();
        $token = bin2hex(random_bytes(32));

        $db->table('invitations')->insert([
            'association_id' => $this->assocId,
            'invited_by'     => $this->secretaryId,
            'phone'          => '+237610000004', // memberId déjà actif
            'token'          => $token,
            'role'           => 'member',
            'status'         => 'pending',
            'expires_at'     => gmdate('Y-m-d H:i:s', strtotime('+7 days')),
            'created_at'     => gmdate('Y-m-d H:i:s'),
        ]);
        $invitId = $db->insertID();

        $invitation = $db->table('invitations')->where('id', $invitId)->get()->getRowArray();

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessageMatches('/déjà membre/');

            $service = new MemberService($this->assocId);
            $service->acceptInvitation($invitation, $this->memberId);
        } finally {
            $db->table('invitations')->where('id', $invitId)->delete();
        }
    }

    // =========================================================================
    // getOverview
    // =========================================================================

    public function testGetOverviewReturnsAllAssociationsForUser(): void
    {
        // Le président est dans les deux associations
        $service = new MemberService(0); // cross-associations
        $result  = $service->getOverview($this->presidentId);

        $this->assertArrayHasKey('associations', $result);
        $this->assertArrayHasKey('totals', $result);
        $this->assertCount(2, $result['associations']);

        // Vérifier la structure d'un élément
        $first = $result['associations'][0];
        $this->assertArrayHasKey('association', $first);
        $this->assertArrayHasKey('role', $first);
        $this->assertArrayHasKey('tontines', $first);
        $this->assertArrayHasKey('loans', $first);
        $this->assertArrayHasKey('solidarity', $first);
    }

    public function testGetOverviewReturnsEmptyForUserWithNoAssociation(): void
    {
        $service = new MemberService(0);
        $result  = $service->getOverview($this->outsiderId);

        $this->assertCount(0, $result['associations']);
        $this->assertSame(0, $result['totals']['total_contributed']);
    }
}
