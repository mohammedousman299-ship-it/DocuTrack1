<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\LostDeclaration;
use App\Review\DecideDeclarationReview;
use App\Review\ReviewDecision;
use App\Review\ReviewQueue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * File de revue des déclarations à nom incohérent (D-036).
 *
 * Comme le parcours Propriétaire, ce contrôleur ne transmet JAMAIS de modèle
 * Eloquent à la vue : la projection App\Review\DeclarationReviewItem ne porte
 * pas les champs qu'un relecteur n'a pas à voir.
 */
final class DeclarationReviewController
{
    public function __construct(
        private readonly ReviewQueue $queue,
        private readonly DecideDeclarationReview $decide,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        abort_if($user === null, 404);

        $items = $this->queue->pending(app()->getLocale());

        // La consultation de la file est elle-même un accès à des noms
        // complets : elle est tracée, comme la décision. Le journal enregistre
        // le VOLUME consulté, jamais les personnes — sans quoi il deviendrait
        // un second entrepôt des données qu'il surveille.
        DB::table('audit_logs')->insert([
            'actor_user_id' => $user->id,
            'actor_role' => 'admin_sensitive',
            'action' => 'declaration.review_queue_viewed',
            'entity_type' => 'lost_declaration',
            'reason' => 'items='.$items->count(),
            'ip' => $request->ip(),
            'created_at' => now(),
        ]);

        return view('admin.review.index', [
            'items' => $items,
            'pendingCount' => $this->queue->pendingCount(),
            'oldestPendingHours' => $this->queue->oldestPendingHours(),
            'minimumReasonLength' => DecideDeclarationReview::MINIMUM_REASON_LENGTH,
        ]);
    }

    public function update(Request $request, string $declaration): RedirectResponse
    {
        $user = $request->user();

        abort_if($user === null, 404);

        $validated = $request->validate([
            'decision' => ['required', Rule::enum(ReviewDecision::class)],
            'reason' => ['required', 'string', 'min:'.DecideDeclarationReview::MINIMUM_REASON_LENGTH, 'max:1000'],
        ]);

        $model = LostDeclaration::findOrFail($declaration);

        try {
            $this->decide->handle(
                reviewer: $user,
                declaration: $model,
                decision: ReviewDecision::from($validated['decision']),
                reason: $validated['reason'],
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['reason' => $e->getMessage()]);
        }

        return back()->with('status', __('admin.review_recorded'));
    }
}
