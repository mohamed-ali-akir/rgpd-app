@include('letters.blocks.header')

Objet : {!! $subject !!}

Madame, Monsieur,

Des données personnelles me concernant sont publiées à l'adresse suivante :
{!! $d->url !!}

Les données concernées sont : {!! $dataList !!}.

En application de l'article 18 du RGPD, je vous demande de limiter le traitement de ces données pour le motif suivant :
{!! $d->details !!}

Pendant cette limitation, ces données ne peuvent être que conservées, sauf exceptions prévues par l'article 18.2 du RGPD. Si elles ont été communiquées à des tiers, je vous remercie de leur notifier cette limitation, conformément à l'article 19 du RGPD.

@include('letters.blocks.deadline')

@include('letters.blocks.cnil')

@include('letters.blocks.signature')
