<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ErasureRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * GET /api/requests/{id}/complaint.pdf : dossier à joindre à une plainte CNIL (art. 77).
 * L'app prépare le dossier ; c'est l'utilisateur qui dépose la plainte sur le site de la CNIL.
 */
class ComplaintController extends Controller
{
    public function __invoke(ErasureRequest $erasureRequest): Response
    {
        abort_unless($erasureRequest->hasComplaint(), 409, 'Préparez d\'abord la plainte depuis la page de la demande.');

        $erasureRequest->load('user.profile', 'organization', 'events');

        return Pdf::loadView('complaints.cnil', [
            'request' => $erasureRequest,
            'profile' => $erasureRequest->user->profile,
            'generatedAt' => now(),
        ])->download('dossier-plainte-cnil-demande-'.$erasureRequest->id.'.pdf');
    }
}
