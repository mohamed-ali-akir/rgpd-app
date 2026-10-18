<?php

namespace App\Enums;

enum FindingSource: string
{
    case CompanyRegistry = 'company_registry'; // API Recherche d'entreprises
    case WebSearch = 'web_search';             // moteur de recherche + lecture de la page

    public function label(): string
    {
        return match ($this) {
            self::CompanyRegistry => 'Registre des entreprises',
            self::WebSearch => 'Recherche web',
        };
    }
}
