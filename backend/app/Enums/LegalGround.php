<?php

namespace App\Enums;

/**
 * Motifs d'effacement de l'article 17.1 du RGPD.
 */
enum LegalGround: string
{
    case NoLongerNecessary = 'art17_1_a';
    case ConsentWithdrawn = 'art17_1_b';
    case Objection = 'art17_1_c'; // va de pair avec l'opposition au titre de l'art. 21
    case UnlawfulProcessing = 'art17_1_d';
    case LegalObligation = 'art17_1_e';
    case ChildData = 'art17_1_f';

    public function article(): string
    {
        return match ($this) {
            self::NoLongerNecessary => 'art. 17.1.a',
            self::ConsentWithdrawn => 'art. 17.1.b',
            self::Objection => 'art. 17.1.c',
            self::UnlawfulProcessing => 'art. 17.1.d',
            self::LegalObligation => 'art. 17.1.e',
            self::ChildData => 'art. 17.1.f',
        };
    }

    /**
     * Formulation reprise dans la lettre, à la première personne.
     */
    public function letterText(): string
    {
        return match ($this) {
            self::NoLongerNecessary => 'ces données ne sont plus nécessaires au regard des finalités pour lesquelles elles ont été collectées ou traitées',
            self::ConsentWithdrawn => 'je retire le consentement sur lequel est fondé le traitement, et il n\'existe pas d\'autre fondement juridique au traitement',
            self::Objection => 'je m\'oppose au traitement en vertu de l\'article 21 du RGPD, et il n\'existe pas de motif légitime impérieux pour le traitement',
            self::UnlawfulProcessing => 'ces données ont fait l\'objet d\'un traitement illicite',
            self::LegalObligation => 'ces données doivent être effacées pour respecter une obligation légale à laquelle vous êtes soumis',
            self::ChildData => 'ces données ont été collectées dans le cadre de l\'offre de services de la société de l\'information visée à l\'article 8.1 du RGPD, alors que j\'étais mineur',
        };
    }
}
