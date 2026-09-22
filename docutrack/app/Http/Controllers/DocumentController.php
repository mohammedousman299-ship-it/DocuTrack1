<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\FoundDocument;
use App\Models\LostDeclaration;
use App\Models\Payment;
use App\Services\DocumentMatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
    public function __construct(private DocumentMatcher $matcher) {}

    public function createFound()
    {
        return view('documents.report', ['categories' => Category::orderBy('name')->get()]);
    }

    public function storeFound(Request $request)
    {
        $data = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'owner_name' => 'required|string|max:255',
            'doc_number' => 'nullable|string|max:100',
            'location' => 'required|string|max:255',
            'deposit_point' => 'required|string|max:255',
            'context' => 'nullable|string|max:2000',
            'image' => ['nullable', 'string', 'regex:/^data:image\/(png|jpeg);base64,/'],
        ]);

        if ($image = $data['image'] ?? null) {
            $ext = str_starts_with($image, 'data:image/png') ? 'png' : 'jpg';
            $path = 'found/'.Str::uuid().'.'.$ext;
            Storage::disk('public')->put($path, base64_decode(substr($image, strpos($image, ',') + 1)));
            $data['image_path'] = $path;
        }
        unset($data['image']);

        $doc = $request->user()->hasMany(FoundDocument::class)->create($data);
        $this->matcher->notifyForFound($doc);

        return redirect()->route('finder')->with('success', __('msgReportSaved'));
    }

    public function search(Request $request)
    {
        $categories = Category::orderBy('name')->get();
        if (! $request->filled('owner_name')) {
            return view('documents.search', compact('categories'));
        }

        $data = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'owner_name' => 'required|string|max:255',
            'doc_number' => 'nullable|string|max:100',
        ]);
        $matches = $this->matcher->search($data['category_id'], $data['owner_name'], $data['doc_number'] ?? null);

        return view($matches->isEmpty() ? 'documents.no-match' : 'documents.matches', compact('matches'));
    }

    public function createDeclaration()
    {
        return view('documents.declare', ['categories' => Category::orderBy('name')->get()]);
    }

    public function storeDeclaration(Request $request)
    {
        $data = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'full_name' => 'required|string|max:255',
            'doc_number' => 'nullable|string|max:100',
            'last_location' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
        ]);

        $decl = $request->user()->hasMany(LostDeclaration::class)->create($data);
        $this->matcher->notifyForDeclaration($decl);

        return redirect()->route('notifications')->with('success', __('msgDeclared'));
    }

    public function payForm(Request $request, FoundDocument $document)
    {
        if ($request->user()->hasPaidFor($document)) {
            return redirect()->route('documents.show', $document);
        }

        return view('documents.pay', compact('document'));
    }

    public function pay(Request $request, FoundDocument $document)
    {
        $data = $request->validate([
            'method' => 'required|in:'.implode(',', config('docutrack.payment_methods')),
            'account_number' => 'required|string|max:50',
        ]);

        // Simulated gateway: every payment is accepted immediately.
        Payment::firstOrCreate(
            ['user_id' => $request->user()->id, 'found_document_id' => $document->id],
            $data + ['amount' => config('docutrack.fee'), 'reference' => 'DT-'.Str::upper(Str::random(10)), 'status' => 'paid'],
        );

        return redirect()->route('documents.show', $document);
    }

    public function show(Request $request, FoundDocument $document)
    {
        abort_unless($request->user()->hasPaidFor($document) || $request->user()->isAdmin(), 403);

        return view('documents.show', ['document' => $document->load('category', 'user')]);
    }

    public function notifications(Request $request)
    {
        $alerts = $request->user()->alerts()->with('foundDocument.category')->latest()->get();
        $request->user()->alerts()->whereNull('read_at')->update(['read_at' => now()]);

        return view('notifications', compact('alerts'));
    }
}
