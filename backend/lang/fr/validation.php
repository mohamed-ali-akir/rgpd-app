<?php

/*
 * Messages de validation en français (APP_LOCALE=fr), limités aux règles utilisées par l'API.
 * ":attribute" est remplacé par le nom du champ, traduit dans "attributes" en bas du fichier.
 */
return [
    'accepted' => 'Le champ :attribute doit être accepté.',
    'after' => 'Le champ :attribute doit être une date postérieure au :date.',
    'array' => 'Le champ :attribute doit être une liste.',
    'before_or_equal' => 'Le champ :attribute doit être une date antérieure ou égale à aujourd\'hui.',
    'boolean' => 'Le champ :attribute doit être vrai ou faux.',
    'confirmed' => 'La confirmation du champ :attribute ne correspond pas.',
    'current_password' => 'Le mot de passe est incorrect.',
    'date' => 'Le champ :attribute n\'est pas une date valide.',
    'distinct' => 'Le champ :attribute contient une valeur en double.',
    'email' => 'Le champ :attribute doit être une adresse e-mail valide.',
    'enum' => 'La valeur choisie pour :attribute est invalide.',
    'exists' => 'La valeur choisie pour :attribute est invalide.',
    'in' => 'La valeur choisie pour :attribute est invalide.',
    'integer' => 'Le champ :attribute doit être un nombre entier.',
    'lowercase' => 'Le champ :attribute doit être en minuscules.',
    'max' => [
        'array' => 'Le champ :attribute ne doit pas contenir plus de :max éléments.',
        'string' => 'Le champ :attribute ne doit pas dépasser :max caractères.',
    ],
    'min' => [
        'array' => 'Le champ :attribute doit contenir au moins :min élément(s).',
        'string' => 'Le champ :attribute doit contenir au moins :min caractères.',
    ],
    'regex' => 'Le format du champ :attribute est invalide.',
    'required' => 'Le champ :attribute est obligatoire.',
    'string' => 'Le champ :attribute doit être une chaîne de caractères.',
    'unique' => 'Cette valeur de :attribute est déjà utilisée.',
    'url' => 'Le champ :attribute doit être une adresse web valide.',

    'attributes' => [
        'first_name' => 'prénom',
        'last_name' => 'nom',
        'email' => 'adresse e-mail',
        'password' => 'mot de passe',
        'postal_address' => 'adresse postale',
        'phone' => 'téléphone',
        'name' => 'nom',
        'domain' => 'domaine',
        'category' => 'catégorie',
        'privacy_email' => 'e-mail RGPD',
        'privacy_form_url' => 'formulaire',
        'dpo_name' => 'nom du DPO',
        'url' => 'adresse de la page',
        'organization_id' => 'organisme',
        'data_types' => 'données concernées',
        'legal_grounds' => 'fondements',
        'channel' => 'canal d\'envoi',
        'subject' => 'objet',
        'body' => 'texte',
        'sent_at' => 'date d\'envoi',
        'outcome' => 'réponse',
        'note' => 'note',
        'search' => 'recherche',
        'status' => 'statut',
    ],
];
