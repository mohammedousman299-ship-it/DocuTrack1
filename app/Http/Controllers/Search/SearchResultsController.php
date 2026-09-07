<?php

declare(strict_types=1);

namespace App\Http\Controllers\Search;

use App\Disclosure\FoundReportLevel1View;
use App\Models\FoundReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Résultats de recherche, au niveau N1 uniquement.
 *
 * Le contrôleur ne transmet JAMAIS de modèle Eloquent à la vue : il construit
 * des vues de divulgation, qui ne portent pas les champs interdits
 * (docs/DISCLOSURE_LEVELS.md §4.1).
 */
final class SearchResultsController
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        abort_if($user === null, 404);

        $requests = DB::table('search_requests')
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        // Deux requêtes pour l'ensemble de la page, et non deux PAR recherche :
        // la boucle naïve coûtait 22 requêtes pour 6 recherches, mesuré par le
        // test de budget (§9.4). Sur un réseau 3G, cette dérive est le genre de
        // détail qui rend une page inutilisable.
        $bestByRequest = DB::table('search_results')
            ->whereIn('search_request_id', $requests->pluck('id'))
            ->orderBy('search_request_id')
            ->orderByDesc('score')
            ->get()
            ->groupBy('search_request_id')
            ->map(fn ($rows): string => (string) $rows->first()->found_report_id);

        $reports = FoundReport::with('documentType')
            ->whereIn('id', $bestByRequest->values()->all())
            ->get()
            ->keyBy('id');

        $searches = $requests->map(function (object $row) use ($bestByRequest, $reports): array {
            // Une seule correspondance est présentée, même s'il y en a
            // plusieurs : exposer un compte serait un signal d'énumération
            // offert gratuitement (M-07).
            $bestId = $bestByRequest[$row->id] ?? null;
            $report = $bestId === null ? null : $reports->get($bestId);

            return [
                'id' => $row->id,
                'created_at' => $row->created_at,
                'pending' => $row->status === 'queued',
                // Le silence serait plus confortable, mais laisserait la
                // personne devant une recherche qui n'aboutit jamais sans
                // qu'elle sache pourquoi. On le dit (D-036).
                'held' => $row->status === 'held',
                'view' => $report === null ? null : FoundReportLevel1View::from($report),
            ];
        })->all();

        return view('search.results', ['searches' => $searches]);
    }
}
