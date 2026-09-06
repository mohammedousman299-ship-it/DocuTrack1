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

        $searches = $requests->map(function (object $row): array {
            // Une seule correspondance est présentée, même s'il y en a
            // plusieurs : exposer un compte serait un signal d'énumération
            // offert gratuitement (M-07).
            $bestId = DB::table('search_results')
                ->where('search_request_id', $row->id)
                ->orderByDesc('score')
                ->value('found_report_id');

            $report = $bestId === null
                ? null
                : FoundReport::with('documentType')->find($bestId);

            return [
                'id' => $row->id,
                'created_at' => $row->created_at,
                'pending' => $row->status === 'queued',
                'view' => $report === null ? null : FoundReportLevel1View::from($report),
            ];
        })->all();

        return view('search.results', ['searches' => $searches]);
    }
}
