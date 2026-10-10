<?php

namespace Database\Seeders;

use App\Enums\OrganizationCategory as C;
use App\Models\Organization;
use Illuminate\Database\Seeder;

/**
 * Organismes français fréquents.
 *
 * ⚠️ RÈGLE : aucun contact (privacy_email, privacy_form_url, dpo_name) n'est saisi ici
 * sans avoir été VÉRIFIÉ À LA MAIN sur la politique de confidentialité officielle du site.
 * Une fois un contact vérifié : renseigner les champs ET 'verified' => 'AAAA-MM-JJ'.
 * Tant que 'verified' est null, l'app affiche « coordonnées non vérifiées ».
 *
 * Le seeder est rejouable (updateOrCreate sur le domaine) : php artisan db:seed --class=OrganizationSeeder
 */
class OrganizationSeeder extends Seeder
{
    /**
     * @return list<array{name: string, domain: string, category: C, privacy_email?: string|null, privacy_form_url?: string|null, dpo_name?: string|null, verified?: string|null}>
     */
    private function organizations(): array
    {
        return [
            // Annuaires
            ['name' => 'PagesJaunes', 'domain' => 'pagesjaunes.fr', 'category' => C::Directory],
            ['name' => '118 712', 'domain' => '118712.fr', 'category' => C::Directory],

            // Registres légaux (publication obligatoire : refus probable, art. 17.3.b)
            ['name' => 'Pappers', 'domain' => 'pappers.fr', 'category' => C::LegalRegistry],
            ['name' => 'Societe.com', 'domain' => 'societe.com', 'category' => C::LegalRegistry],
            ['name' => 'Infogreffe', 'domain' => 'infogreffe.fr', 'category' => C::LegalRegistry],
            ['name' => 'Verif.com', 'domain' => 'verif.com', 'category' => C::LegalRegistry],
            ['name' => 'Manageo', 'domain' => 'manageo.fr', 'category' => C::LegalRegistry],
            ['name' => 'BODACC', 'domain' => 'bodacc.fr', 'category' => C::LegalRegistry],
            ['name' => 'Annuaire des entreprises', 'domain' => 'annuaire-entreprises.data.gouv.fr', 'category' => C::LegalRegistry],
            ['name' => 'Doctrine', 'domain' => 'doctrine.fr', 'category' => C::LegalRegistry],

            // Réseaux sociaux
            ['name' => 'Facebook', 'domain' => 'facebook.com', 'category' => C::SocialNetwork],
            ['name' => 'Instagram', 'domain' => 'instagram.com', 'category' => C::SocialNetwork],
            ['name' => 'LinkedIn', 'domain' => 'linkedin.com', 'category' => C::SocialNetwork],
            ['name' => 'X (Twitter)', 'domain' => 'x.com', 'category' => C::SocialNetwork],
            ['name' => 'TikTok', 'domain' => 'tiktok.com', 'category' => C::SocialNetwork],
            ['name' => 'YouTube', 'domain' => 'youtube.com', 'category' => C::SocialNetwork],
            ['name' => 'Pinterest', 'domain' => 'pinterest.com', 'category' => C::SocialNetwork],
            ['name' => 'Copains d\'avant', 'domain' => 'copainsdavant.linternaute.com', 'category' => C::SocialNetwork],

            // Forums
            ['name' => 'Reddit', 'domain' => 'reddit.com', 'category' => C::Forum],
            ['name' => 'Jeuxvideo.com', 'domain' => 'jeuxvideo.com', 'category' => C::Forum],
            ['name' => 'Doctissimo', 'domain' => 'doctissimo.fr', 'category' => C::Forum],

            // Presse (liberté d'expression, art. 17.3.a)
            ['name' => 'Le Monde', 'domain' => 'lemonde.fr', 'category' => C::Press],
            ['name' => 'Le Figaro', 'domain' => 'lefigaro.fr', 'category' => C::Press],
            ['name' => 'Le Parisien', 'domain' => 'leparisien.fr', 'category' => C::Press],
            ['name' => 'Ouest-France', 'domain' => 'ouest-france.fr', 'category' => C::Press],
            ['name' => '20 Minutes', 'domain' => '20minutes.fr', 'category' => C::Press],

            // Moteurs de recherche (formulaire de déréférencement : envoi manuel)
            ['name' => 'Google', 'domain' => 'google.com', 'category' => C::SearchEngine],
            ['name' => 'Google', 'domain' => 'google.fr', 'category' => C::SearchEngine],
            ['name' => 'Bing', 'domain' => 'bing.com', 'category' => C::SearchEngine],
            ['name' => 'Qwant', 'domain' => 'qwant.com', 'category' => C::SearchEngine],

            // Autres
            ['name' => 'Leboncoin', 'domain' => 'leboncoin.fr', 'category' => C::Other],
            ['name' => 'Vinted', 'domain' => 'vinted.fr', 'category' => C::Other],
        ];
    }

    public function run(): void
    {
        foreach ($this->organizations() as $org) {
            $organization = Organization::firstOrNew(['domain' => $org['domain']]);
            $organization->fill([
                'name' => $org['name'],
                'category' => $org['category'],
                'privacy_email' => $org['privacy_email'] ?? null,
                'privacy_form_url' => $org['privacy_form_url'] ?? null,
                'dpo_name' => $org['dpo_name'] ?? null,
            ]);
            // verified_at n'est pas "fillable" : seul ce seeder (vérification humaine) le renseigne.
            $organization->verified_at = $org['verified'] ?? null;
            $organization->save();
        }
    }
}
