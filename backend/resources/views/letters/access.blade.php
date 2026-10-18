@include('letters.blocks.header')

Objet : {!! $subject !!}

Madame, Monsieur,

En application de l'article 15 du RGPD, je vous demande de me confirmer si vous traitez des données personnelles me concernant et, le cas échéant, de m'en communiquer une copie.
@if ($d->dataTypes === [])
Ma demande porte sur l'ensemble des données me concernant, notamment celles publiées à l'adresse suivante :
@else
Ma demande porte notamment sur {!! $dataList !!}, publiées à l'adresse suivante :
@endif
{!! $d->url !!}

Je vous remercie de m'indiquer également :
- les finalités du traitement ;
- les catégories de données concernées ;
- les destinataires auxquels ces données ont été ou seront communiquées ;
- la durée de conservation envisagée ;
- la source des données, si elles n'ont pas été collectées auprès de moi ;
- l'existence éventuelle d'une prise de décision automatisée.

@include('letters.blocks.deadline')

@include('letters.blocks.cnil')

@include('letters.blocks.signature')
