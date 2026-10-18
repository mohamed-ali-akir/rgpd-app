<?php

namespace App\Console\Commands;

use App\Actions\CreateDraftRequest;
use App\Actions\DraftInput;
use App\Enums\CheckOutcome;
use App\Enums\DataType;
use App\Enums\OrganizationCategory;
use App\Enums\RequestKind;
use App\Enums\RequestStatus;
use App\Enums\ScanTrigger;
use App\Enums\VerificationStatus;
use App\Models\ErasureRequest;
use App\Models\Organization;
use App\Models\User;
use App\Services\Demo\DemoScanSource;
use App\Services\Scanner\FindingRecorder;
use App\Services\Scanner\Scanner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Remet le compte de démo dans un état de départ réaliste : des demandes à chaque étape,
 * des résultats de scan à examiner. Planifiée chaque nuit quand APP_DEMO=true.
 */
#[Signature('demo:reset {--force : Exécuter même hors mode démo (développement)}')]
#[Description('Réinitialise le compte et les données de démonstration')]
class ResetDemoCommand extends Command
{
    /** Organismes fictifs, sur des domaines réservés aux exemples (RFC 2606). */
    private const ORGANIZATIONS = [
        ['name' => 'Annuaire Démo', 'domain' => 'annuaire-demo.example', 'category' => OrganizationCategory::Directory, 'privacy_email' => 'dpo@annuaire-demo.example'],
        ['name' => 'Forum Démo', 'domain' => 'forum-demo.example', 'category' => OrganizationCategory::Forum, 'privacy_email' => 'privacy@forum-demo.example'],
        ['name' => 'Registre Démo', 'domain' => 'registre-demo.example', 'category' => OrganizationCategory::LegalRegistry, 'privacy_email' => null],
        ['name' => 'Journal Démo', 'domain' => 'journal-demo.example', 'category' => OrganizationCategory::Press, 'privacy_email' => 'rgpd@journal-demo.example'],
    ];

    public function handle(CreateDraftRequest $createDraft, DemoScanSource $demoSource, FindingRecorder $recorder): int
    {
        if (! config('app.demo') && ! $this->option('force')) {
            $this->error('Mode démo désactivé (APP_DEMO=false). Utilisez --force en développement.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($createDraft) {
            $user = $this->resetUser();
            $organizations = $this->resetOrganizations($user);
            $this->createRequests($user, $organizations, $createDraft);
        });

        /*
         * Scanner construit ici avec la seule source fictive, et non résolu par le conteneur :
         * avec --force hors mode démo, le conteneur fournirait les sources réelles (API, moteur)
         * et lancerait une vraie recherche au nom du compte fictif. Ici : aucun accès réseau, toujours.
         */
        $scanner = new Scanner([$demoSource], $recorder);
        $user = User::where('email', config('app.demo_email'))->firstOrFail();
        $scanner->run($user->scans()->create(['trigger' => ScanTrigger::Manual]));

        $this->info('Démo réinitialisée : '.config('app.demo_email'));

        return self::SUCCESS;
    }

    private function resetUser(): User
    {
        $user = User::firstOrNew(['email' => config('app.demo_email')]);
        $user->forceFill([
            'name' => 'Camille Démo',
            'password' => Hash::make((string) config('app.demo_password')),
            'email_verified_at' => now(),
        ])->save();

        // Tout le contenu créé par les visiteurs disparaît (les clés étrangères suppriment l'historique).
        $user->erasureRequests()->delete();
        $user->findings()->delete();
        $user->scans()->delete();
        Organization::where('created_by', $user->id)->delete();

        $user->profile()->updateOrCreate([], [
            'first_name' => 'Camille',
            'last_name' => 'Démo',
            'postal_address' => '12 rue des Exemples, 75000 Paris',
            'phone' => '06 00 00 00 00',
            'auto_scan' => false,
        ]);
        // Faux compte Gmail : en démo, MailSender est toujours simulé, rien ne part.
        $user->googleAccount()->updateOrCreate([], [
            'email' => 'camille.demo@gmail.example',
            'refresh_token' => 'demo',
            'scopes' => ['https://www.googleapis.com/auth/gmail.send'],
        ]);

        return $user->refresh();
    }

    /**
     * @return array<string, Organization>
     */
    private function resetOrganizations(User $user): array
    {
        $organizations = [];
        foreach (self::ORGANIZATIONS as $data) {
            $organization = Organization::firstOrNew(['domain' => $data['domain']]);
            $organization->fill($data);
            $organization->verified_at = now();
            $organization->save();
            $organizations[$data['domain']] = $organization;
        }

        return $organizations;
    }

    /**
     * @param  array<string, Organization>  $orgs
     */
    private function createRequests(User $user, array $orgs, CreateDraftRequest $createDraft): void
    {
        $draft = fn (string $domain, string $path, RequestKind $kind = RequestKind::Erasure, array $types = [DataType::Name, DataType::Phone]) => $createDraft->handle(
            $user,
            $orgs[$domain],
            new DraftInput(url: "https://{$domain}{$path}", kind: $kind, dataTypes: $types, context: ['own_data_confirmed' => true]),
        );
        $send = function (ErasureRequest $request, int $daysAgo): void {
            $sentAt = now()->subDays($daysAgo);
            $request->transitionTo(RequestStatus::Sent, ['sent_at' => $sentAt, 'due_at' => $sentAt->copy()->addMonthNoOverflow()], ['channel' => $request->channel->value]);
        };

        // 1. Brouillon à relire et envoyer.
        $draft('journal-demo.example', '/2026/article-camille');

        // 2. Envoyée il y a 10 jours : le délai court.
        $send($draft('forum-demo.example', '/membres/camille', types: [DataType::Name, DataType::Email]), 10);

        // 3. Délai dépassé : relance ou plainte CNIL.
        $late = $draft('annuaire-demo.example', '/ancienne-fiche');
        $send($late, 40);
        $late->transitionTo(RequestStatus::Overdue);

        // 4. Acceptée, effacement vérifié.
        $accepted = $draft('annuaire-demo.example', '/fiche-supprimee');
        $send($accepted, 25);
        $accepted->transitionTo(RequestStatus::Accepted, [], ['note' => 'Fiche supprimée, confirmé par e-mail.']);
        $accepted->checks()->create(['outcome' => CheckOutcome::Gone, 'http_status' => 404]);
        $accepted->forceFill(['verification' => VerificationStatus::Cleared, 'next_check_at' => null, 'check_count' => 1])->save();

        // 5. Demande d'accès (art. 15) envoyée à la main.
        $access = $draft('registre-demo.example', '/entreprise/000000000', RequestKind::Access, []);
        $send($access, 5);
    }
}
