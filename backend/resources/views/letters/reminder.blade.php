@include('letters.blocks.header')

Objet : {!! $subject !!}

Madame, Monsieur,

Le {!! $sentAt !!}, je vous ai adressé {!! $requestNoun !!} me concernant, publiées à l'adresse suivante :
{!! $d->url !!}

Le délai de réponse prévu à l'article 12.3 du RGPD a expiré le {!! $dueAt !!}, et je n'ai reçu à ce jour aucune réponse.

Je vous demande donc à nouveau de {!! $reminderAction !!} ({!! $dataList !!}) et de m'en confirmer la réalisation dans les meilleurs délais.

@include('letters.blocks.cnil')

@include('letters.blocks.signature')
