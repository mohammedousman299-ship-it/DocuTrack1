<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\FoundDocument;
use App\Models\LostDeclaration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Matches lost declarations against found documents: same category and
 * similar owner name (case-insensitive LIKE), or identical document number.
 */
class DocumentMatcher
{
    public function search(int $categoryId, string $name, ?string $docNumber = null): Collection
    {
        return FoundDocument::with('category')
            ->where('status', 'available')
            ->where('category_id', $categoryId)
            ->where(fn (Builder $q) => $this->nameOrNumber($q, 'owner_name', $name, $docNumber))
            ->latest()
            ->get();
    }

    public function notifyForFound(FoundDocument $doc): void
    {
        LostDeclaration::where('status', 'open')
            ->where('category_id', $doc->category_id)
            ->get()
            ->filter(fn (LostDeclaration $d) => $this->similar($d->full_name, $doc->owner_name)
                || ($doc->doc_number && $d->doc_number === $doc->doc_number))
            ->each(fn (LostDeclaration $d) => $this->alert($d, $doc));
    }

    public function notifyForDeclaration(LostDeclaration $decl): void
    {
        $this->search($decl->category_id, $decl->full_name, $decl->doc_number)
            ->each(fn (FoundDocument $doc) => $this->alert($decl, $doc));
    }

    private function similar(string $a, string $b): bool
    {
        [$a, $b] = [mb_strtolower(trim($a)), mb_strtolower(trim($b))];

        return str_contains($a, $b) || str_contains($b, $a);
    }

    private function nameOrNumber(Builder $q, string $column, string $name, ?string $number): void
    {
        $q->whereRaw("LOWER($column) LIKE ?", ['%'.mb_strtolower(trim($name)).'%']);
        if ($number) {
            $q->orWhere('doc_number', trim($number));
        }
    }

    private function alert(LostDeclaration $decl, FoundDocument $doc): void
    {
        Alert::firstOrCreate([
            'user_id' => $decl->user_id,
            'found_document_id' => $doc->id,
            'lost_declaration_id' => $decl->id,
        ]);
        $decl->update(['status' => 'matched']);
    }
}
