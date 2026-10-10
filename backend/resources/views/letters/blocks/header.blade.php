{{-- En-tête : expéditeur, destinataire, date --}}
{!! $d->fullName() !!}
@if ($d->postalAddress)
{!! $d->postalAddress !!}
@endif
{!! $d->email !!}
@if ($d->phone)
{!! $d->phone !!}
@endif

À l'attention du délégué à la protection des données
@if ($d->dpoName)
{!! $d->dpoName !!}
@endif
{!! $d->organizationName !!}

Le {!! $date !!}
