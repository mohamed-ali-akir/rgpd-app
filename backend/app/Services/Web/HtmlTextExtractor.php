<?php

namespace App\Services\Web;

use DOMDocument;
use DOMXPath;

/**
 * Extrait le titre et le texte visible d'une page HTML (sans scripts ni styles).
 */
class HtmlTextExtractor
{
    /** Limite de texte conservé : suffisant pour la détection, sans exploser la mémoire. */
    private const MAX_CHARS = 300_000;

    /**
     * @return array{title: string, text: string}
     */
    public function extract(string $html): array
    {
        if (trim($html) === '') {
            return ['title' => '', 'text' => ''];
        }

        $dom = new DOMDocument;
        // Le HTML réel est rarement valide : on ignore les avertissements de libxml.
        $previous = libxml_use_internal_errors(true);
        // Le préfixe force l'interprétation en UTF-8 (sinon les accents sont mal décodés).
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($dom);
        foreach (iterator_to_array($xpath->query('//script|//style|//noscript|//template|//svg') ?: []) as $node) {
            $node->parentNode?->removeChild($node);
        }

        // Les blocs voisins (<h1>Martin</h1><p>Camille</p>) ne doivent pas être collés dans le texte :
        // on ajoute une espace après chaque élément de type bloc.
        $blocks = $xpath->query('//p|//div|//li|//td|//th|//tr|//br|//h1|//h2|//h3|//h4|//h5|//h6|//section|//article|//header|//footer|//dd|//dt|//address|//blockquote');
        foreach (iterator_to_array($blocks ?: []) as $node) {
            $node->parentNode?->insertBefore($dom->createTextNode(' '), $node->nextSibling);
        }

        $titleNodes = $xpath->query('//title');
        $title = $titleNodes !== false && $titleNodes->length > 0 ? (string) $titleNodes->item(0)?->textContent : '';
        $bodyNodes = $xpath->query('//body');
        $body = $bodyNodes !== false && $bodyNodes->length > 0 ? $bodyNodes->item(0) : $dom->documentElement;
        $text = $body !== null ? $body->textContent : '';

        return [
            'title' => $this->clean($title),
            'text' => mb_substr($this->clean($text), 0, self::MAX_CHARS),
        ];
    }

    private function clean(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
