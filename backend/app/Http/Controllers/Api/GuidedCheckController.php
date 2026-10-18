<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrganizationCategory;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\Identity\Identity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Vérifications guidées : ce que l'automate ne peut pas lire (réseaux sociaux, annuaires qui refusent
 * les robots), l'utilisateur le vérifie lui-même en un clic, via une recherche DuckDuckGo
 * "Prénom Nom" site:domaine. Aucun accès à ces sites depuis notre serveur.
 */
class GuidedCheckController extends Controller
{
    private const CATEGORIES = [
        OrganizationCategory::PeopleSearch,
        OrganizationCategory::Directory,
        OrganizationCategory::SocialNetwork,
        OrganizationCategory::LegalRegistry,
        OrganizationCategory::Forum,
    ];

    public function __invoke(Request $request): JsonResponse
    {
        $name = Identity::forUser($request->user())->fullName();

        $checks = Organization::query()
            ->whereIn('category', self::CATEGORIES)
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->map(fn (Organization $organization) => [
                'organization' => [
                    'id' => $organization->id,
                    'name' => $organization->name,
                    'domain' => $organization->domain,
                    'category' => $organization->category,
                    'category_label' => $organization->category->label(),
                ],
                'search_url' => 'https://duckduckgo.com/?q='.rawurlencode('"'.$name.'" site:'.$organization->domain),
            ]);

        return response()->json($checks->values());
    }
}
