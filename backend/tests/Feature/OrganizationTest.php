<?php

use App\Models\Organization;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('recherche un organisme par nom ou domaine', function () {
    organization(['name' => 'PagesJaunes', 'domain' => 'pagesjaunes.fr']);
    organization(['name' => 'Pappers', 'domain' => 'pappers.fr']);

    $this->getJson('/api/organizations?search=jaunes')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.domain', 'pagesjaunes.fr');
});

it('résout une URL vers l\'organisme connu, y compris depuis un sous-domaine', function () {
    $linkedin = organization(['domain' => 'linkedin.com']);

    $this->getJson('/api/organizations/resolve?url='.urlencode('https://fr.linkedin.com/in/camille'))
        ->assertOk()
        ->assertJson(['domain' => 'fr.linkedin.com', 'organization' => ['id' => $linkedin->id]]);
});

it('renvoie null et le domaine extrait pour un organisme inconnu', function () {
    $this->getJson('/api/organizations/resolve?url='.urlencode('https://www.inconnu.fr/page'))
        ->assertOk()
        ->assertExactJson(['domain' => 'inconnu.fr', 'organization' => null]);
});

it('crée un organisme non vérifié en normalisant le domaine', function () {
    $this->postJson('/api/organizations', [
        'name' => 'Nouveau site',
        'domain' => 'https://www.Nouveau-Site.fr/contact',
        'category' => 'forum',
    ])->assertCreated()->assertJson(['domain' => 'nouveau-site.fr', 'verified' => false, 'user_created' => true]);

    expect(Organization::where('domain', 'nouveau-site.fr')->first()->created_by)->toBe(auth()->id());
});

it('refuse un domaine déjà connu', function () {
    organization(['domain' => 'pappers.fr']);

    $this->postJson('/api/organizations', ['name' => 'Doublon', 'domain' => 'www.pappers.fr', 'category' => 'legal_registry'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('domain');
});
