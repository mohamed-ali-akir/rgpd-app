<?php

namespace App\Enums;

enum OrganizationCategory: string
{
    case Directory = 'directory';
    case PeopleSearch = 'people_search';
    case SocialNetwork = 'social_network';
    case Forum = 'forum';
    case Press = 'press';
    case LegalRegistry = 'legal_registry';
    case SearchEngine = 'search_engine';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Directory => 'Annuaire',
            self::PeopleSearch => 'Recherche de personnes',
            self::SocialNetwork => 'Réseau social',
            self::Forum => 'Forum',
            self::Press => 'Presse',
            self::LegalRegistry => 'Registre légal',
            self::SearchEngine => 'Moteur de recherche',
            self::Other => 'Autre',
        };
    }
}
