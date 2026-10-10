{{-- Fondements juridiques : opposition (art. 21) puis effacement (art. 17.1) --}}
@if ($d->hasObjection())
En application de l'article 21 du RGPD, je m'oppose, pour des raisons tenant à ma situation particulière, au traitement de ces données.

@endif
En application de l'article 17 du RGPD, je vous demande d'effacer ces données dans les meilleurs délais, pour le(s) motif(s) suivant(s) :
@foreach ($d->legalGrounds as $ground)
- {!! $ground->letterText() !!} ({!! $ground->article() !!}){!! $loop->last ? '.' : ' ;' !!}
@endforeach

Si ces données ont été communiquées à des tiers, je vous remercie de leur notifier cet effacement, conformément à l'article 19 du RGPD.
