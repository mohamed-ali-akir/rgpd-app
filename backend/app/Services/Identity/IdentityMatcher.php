<?php

namespace App\Services\Identity;

use App\Enums\DataType;
use Illuminate\Support\Str;

/**
 * Cherche l'identité de l'utilisateur dans un texte et estime la confiance.
 *
 * Le nom seul est faible (homonymes) ; l'e-mail est presque certain ; le téléphone est fort.
 * Plusieurs indices se combinent : confiance = 1 - Π(1 - poids). Exemple : nom (0,5) + téléphone (0,8)
 * donne 1 - 0,5 × 0,2 = 0,9. Le résultat reste un indice : l'utilisateur confirme toujours lui-même.
 */
class IdentityMatcher
{
    private const WEIGHTS = [
        'email' => 0.95,
        'phone' => 0.8,
        'name' => 0.5,
    ];

    public function match(Identity $identity, string $text): IdentityMatch
    {
        if (trim($text) === '') {
            return IdentityMatch::none();
        }

        $found = [];
        if ($this->containsName($identity, $text)) {
            $found['name'] = DataType::Name;
        }
        if ($this->containsEmail($identity, $text)) {
            $found['email'] = DataType::Email;
        }
        if ($identity->phone !== null && $this->containsPhone($identity->phone, $text)) {
            $found['phone'] = DataType::Phone;
        }

        if ($found === []) {
            return IdentityMatch::none();
        }

        $miss = 1.0;
        foreach (array_keys($found) as $key) {
            $miss *= 1 - self::WEIGHTS[$key];
        }

        return new IdentityMatch(array_values($found), (int) round((1 - $miss) * 100));
    }

    /**
     * "Camille Martin", "MARTIN Camille", "Camille-Marie Martín" : accents, casse, tirets et ordre ignorés.
     */
    public function containsName(Identity $identity, string $text): bool
    {
        $first = $this->words($identity->firstName);
        $last = $this->words($identity->lastName);
        if ($first === '' || $last === '') {
            return false;
        }

        $haystack = ' '.$this->words($text).' ';

        return str_contains($haystack, " {$first} {$last} ") || str_contains($haystack, " {$last} {$first} ");
    }

    public function containsEmail(Identity $identity, string $text): bool
    {
        $haystack = mb_strtolower($text);
        foreach ($identity->emails as $email) {
            // Délimiteurs : "camille@exemple.fr" ne doit pas correspondre à "xcamille@exemple.fr".
            if (preg_match('/(?<![\w.+-])'.preg_quote($email, '/').'(?![\w-])/u', $haystack) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Formats reconnus : 0601020304, 06 01 02 03 04, 06.01.02.03.04, 06-01-02-03-04, +33 6 01 02 03 04, 0033601020304.
     */
    public function containsPhone(string $phone, string $text): bool
    {
        $digits = $this->nationalDigits($phone);
        if ($digits === null) {
            return false;
        }

        // Les 9 chiffres après le 0, avec un séparateur facultatif entre chaque chiffre.
        $body = implode('[\s.\-]?', str_split(substr($digits, 1)));
        $pattern = '/(?<!\d)(?:\+\s?33\s?(?:\(0\)\s?)?|0033\s?|0)'.$body.'(?!\d)/';

        return preg_match($pattern, $text) === 1;
    }

    /** "+33 6 01 02 03 04" → "0601020304" ; null si ce n'est pas un numéro français à 10 chiffres. */
    private function nationalDigits(string $phone): ?string
    {
        $digits = (string) preg_replace('/\D/', '', $phone);
        if (str_starts_with($digits, '0033')) {
            $digits = '0'.substr($digits, 4);
        } elseif (str_starts_with($digits, '33') && strlen($digits) === 11) {
            $digits = '0'.substr($digits, 2);
        }

        return strlen($digits) === 10 && str_starts_with($digits, '0') ? $digits : null;
    }

    /** "Camille-Marie MARTÍN" → "camille marie martin" */
    private function words(string $text): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($text))));
    }
}
