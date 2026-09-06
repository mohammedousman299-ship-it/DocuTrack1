<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Documents\Upload\UploadTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Délivre une autorisation d'envoi direct.
 *
 * Route protégée par authentification, téléphone vérifié et limitation de
 * débit : une URL d'écriture dans le stockage n'est pas une ressource
 * anonyme.
 */
final class UploadTicketController
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_if($user === null, 404);

        $ticket = UploadTicket::issue($user);

        return response()->json([
            'object_key' => $ticket->objectKey,
            'upload_url' => $ticket->uploadUrl,
            'expires_in' => UploadTicket::LIFETIME_MINUTES * 60,
        ]);
    }
}
