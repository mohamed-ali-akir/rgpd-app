@include('letters.blocks.header')

Objet : {!! $subject !!}

Madame, Monsieur,

Je constate que des données personnelles me concernant sont publiées à l'adresse suivante :
{!! $d->url !!}

Les données concernées sont : {!! $dataList !!}.

@include('letters.blocks.grounds')

@include('letters.blocks.deadline')

@include('letters.blocks.cnil')

@include('letters.blocks.signature')
