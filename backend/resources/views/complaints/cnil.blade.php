<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Dossier de plainte CNIL</title>
    <style>
        /* DejaVu Sans est fournie avec dompdf et gère les accents. */
        body { font-family: "DejaVu Sans", sans-serif; font-size: 11px; color: #1f2937; line-height: 1.45; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        h2 { font-size: 13px; margin: 18px 0 6px; padding-bottom: 3px; border-bottom: 1px solid #d1d5db; }
        .muted { color: #6b7280; }
        .notice { border: 1px solid #f59e0b; background: #fffbeb; padding: 8px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; }
        td, th { text-align: left; vertical-align: top; padding: 3px 6px 3px 0; }
        th { width: 32%; font-weight: bold; }
        .timeline td { border-bottom: 1px solid #e5e7eb; }
        pre { white-space: pre-wrap; font-family: "DejaVu Sans", sans-serif; font-size: 10px; background: #f9fafb; padding: 8px; border: 1px solid #e5e7eb; }
    </style>
</head>
<body>
    <h1>Dossier de réclamation auprès de la CNIL</h1>
    <p class="muted">Article 77 du RGPD — dossier préparé le {{ $generatedAt->timezone(config('app.display_timezone'))->locale('fr')->isoFormat('D MMMM YYYY') }}</p>

    <div class="notice">
        Ce document rassemble les éléments de votre demande. Il ne constitue pas une plainte :
        déposez votre réclamation vous-même sur le site de la CNIL (www.cnil.fr) et joignez-y ce dossier
        ainsi que les preuves dont vous disposez (copie de votre demande, réponse éventuelle, captures d'écran).
        Cette application n'est pas un service de conseil juridique.
    </div>

    <h2>1. Plaignant</h2>
    <table>
        <tr><th>Nom</th><td>{{ $profile?->fullName() }}</td></tr>
        <tr><th>Adresse e-mail</th><td>{{ $request->user->email }}</td></tr>
        @if ($profile?->postal_address)
            <tr><th>Adresse postale</th><td>{{ $profile->postal_address }}</td></tr>
        @endif
    </table>

    <h2>2. Organisme mis en cause</h2>
    <table>
        <tr><th>Nom</th><td>{{ $request->organization->name }}</td></tr>
        <tr><th>Site</th><td>{{ $request->organization->domain }}</td></tr>
        @if ($request->organization->privacy_email)
            <tr><th>Contact données personnelles</th><td>{{ $request->organization->privacy_email }}</td></tr>
        @endif
        <tr><th>Page concernée</th><td>{{ $request->url }}</td></tr>
    </table>

    <h2>3. Demande effectuée</h2>
    <table>
        <tr><th>Données concernées</th><td>{{ app(\App\Services\LetterGenerator::class)->dataList($request->data_types->values()->all()) }}</td></tr>
        <tr><th>Fondements invoqués</th><td>
            @if ($request->legal_grounds->contains(\App\Enums\LegalGround::Objection))
                Opposition (art. 21) ;
            @endif
            Effacement : {{ $request->legal_grounds->map(fn ($g) => $g->article())->implode(', ') }}
        </td></tr>
        <tr><th>Envoyée le</th><td>{{ $request->sent_at?->timezone(config('app.display_timezone'))->locale('fr')->isoFormat('D MMMM YYYY') ?? '—' }} ({{ $request->channel->value === 'gmail' ? 'e-mail' : 'envoi par l\'utilisateur' }})</td></tr>
        <tr><th>Échéance de réponse</th><td>{{ $request->due_at?->timezone(config('app.display_timezone'))->locale('fr')->isoFormat('D MMMM YYYY') ?? '—' }} (art. 12.3)</td></tr>
        <tr><th>Situation actuelle</th><td>{{ $request->events->contains('type', \App\Enums\EventType::ResponseRefused) ? 'Demande refusée par l\'organisme' : 'Aucune réponse dans le délai légal' }}</td></tr>
    </table>

    <h2>4. Chronologie</h2>
    <table class="timeline">
        @foreach ($request->events as $event)
            <tr>
                <td style="width: 28%">{{ $event->occurred_at->timezone(config('app.display_timezone'))->locale('fr')->isoFormat('D MMMM YYYY, HH:mm') }}</td>
                <td>
                    {{ $event->type->label() }}
                    @if (! empty($event->payload['note']))
                        <br><span class="muted">Note : {{ $event->payload['note'] }}</span>
                    @endif
                </td>
            </tr>
        @endforeach
    </table>

    <h2>5. Copie de la demande envoyée</h2>
    <p><strong>Objet :</strong> {{ $request->subject }}</p>
    <pre>{{ $request->body }}</pre>
</body>
</html>
