@include('letters.blocks.header')

Objet : {!! $subject !!}

Madame, Monsieur,

Des données personnelles inexactes me concernant sont publiées à l'adresse suivante :
{!! $d->url !!}

Les données concernées sont : {!! $dataList !!}.

En application de l'article 16 du RGPD, je vous demande de les rectifier dans les meilleurs délais, de la manière suivante :
{!! $d->details !!}

Si ces données ont été communiquées à des tiers, je vous remercie de leur notifier cette rectification, conformément à l'article 19 du RGPD.

@include('letters.blocks.deadline')

@include('letters.blocks.cnil')

@include('letters.blocks.signature')
