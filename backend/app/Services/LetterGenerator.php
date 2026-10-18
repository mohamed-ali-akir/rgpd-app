<?php

namespace App\Services;

use App\Enums\DataType;
use App\Enums\RequestKind;
use App\Services\Letters\Letter;
use App\Services\Letters\LetterData;
use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Construit le texte des lettres à partir des blocs Blade de resources/views/letters.
 * Aucune IA, aucun accès à la base : même entrée = même lettre (testé par snapshots).
 *
 * Les lettres sont du TEXTE BRUT (e-mail, zone de texte) : les vues utilisent {!! !!}
 * car {{ }} transformerait les apostrophes en entités HTML (&#039;).
 */
class LetterGenerator
{
    /**
     * Lettre initiale, selon le droit exercé (effacement, accès, rectification, limitation).
     */
    public function initial(LetterData $data): Letter
    {
        $subject = match ($data->kind) {
            RequestKind::Erasure => $data->hasObjection()
                ? 'Opposition et demande d\'effacement de données personnelles (articles 21 et 17 du RGPD)'
                : 'Demande d\'effacement de données personnelles (article 17 du RGPD)',
            RequestKind::Access => 'Demande d\'accès à mes données personnelles (article 15 du RGPD)',
            RequestKind::Rectification => 'Demande de rectification de données personnelles (article 16 du RGPD)',
            RequestKind::Restriction => 'Demande de limitation du traitement de mes données personnelles (article 18 du RGPD)',
        };

        return new Letter($subject, $this->render('letters.'.$data->kind->value, $data, $subject));
    }

    /**
     * Raccourci conservé pour la lisibilité des tests de la V1.
     */
    public function erasure(LetterData $data): Letter
    {
        return $this->initial($data);
    }

    public function reminder(LetterData $data, DateTimeInterface $sentAt, DateTimeInterface $dueAt): Letter
    {
        $noun = $data->kind->requestNoun(); // « une demande d'effacement de données personnelles »
        $subject = 'Relance : '.mb_substr($noun, mb_strlen('une ')).' restée sans réponse';

        return new Letter($subject, $this->render('letters.reminder', $data, $subject, [
            'sentAt' => $this->formatDate($sentAt),
            'dueAt' => $this->formatDate($dueAt),
            'requestNoun' => $noun,
            'reminderAction' => $data->kind->reminderAction(),
        ]));
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function render(string $view, LetterData $data, string $subject, array $extra = []): string
    {
        $text = view($view, [
            'd' => $data,
            'subject' => $subject,
            'date' => $this->formatDate($data->date),
            'dataList' => $this->dataList($data->dataTypes),
            ...$extra,
        ])->render();

        return $this->normalize($text);
    }

    /**
     * [Name, Email, Photo] → "mes nom et prénom, mon adresse e-mail et ma photographie"
     *
     * @param  list<DataType>  $types
     */
    public function dataList(array $types): string
    {
        if ($types === []) {
            return 'l\'ensemble des données me concernant';
        }

        $labels = array_map(fn (DataType $t) => $t->label(), $types);
        $last = array_pop($labels);

        return $labels === [] ? (string) $last : implode(', ', $labels).' et '.$last;
    }

    private function formatDate(DateTimeInterface $date): string
    {
        // "10 octobre 2026"
        return Carbon::instance($date)->timezone(config('app.display_timezone'))->locale('fr')->isoFormat('D MMMM YYYY');
    }

    /**
     * Retours à la ligne Unix, pas d'espaces en fin de ligne, au plus une ligne vide d'affilée.
     */
    private function normalize(string $text): string
    {
        $text = str_replace("\r\n", "\n", $text);
        $text = preg_replace('/[ \t]+$/m', '', $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text)."\n";
    }
}
