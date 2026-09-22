<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Feedback;
use App\Models\FoundDocument;
use App\Models\LostDeclaration;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminController extends Controller
{
    public function index(Request $request)
    {
        $found = FoundDocument::with('category')
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w->where('owner_name', 'like', "%$s%")->orWhere('doc_number', 'like', "%$s%")))
            ->when($request->category, fn ($q, $c) => $q->where('category_id', $c))
            ->latest()->paginate(20, ['*'], 'found_page')->withQueryString();

        return view('admin.index', [
            'tab' => $request->query('tab', 'documents'),
            'found' => $found,
            'declarations' => LostDeclaration::with('category')->latest()->paginate(20, ['*'], 'decl_page'),
            'users' => User::orderBy('id')->get(),
            'categories' => Category::withCount('foundDocuments')->orderBy('name')->get(),
            'feedback' => Feedback::with('user')->latest()->get(),
        ]);
    }

    public function destroyFound(FoundDocument $document)
    {
        if ($document->image_path) {
            Storage::disk('public')->delete($document->image_path);
        }
        $document->delete();

        return back()->with('success', __('msgDeleted'));
    }

    public function destroyDeclaration(LostDeclaration $declaration)
    {
        $declaration->delete();

        return back()->with('success', __('msgDeleted'));
    }

    public function toggleUser(Request $request, User $user)
    {
        abort_if($user->is($request->user()), 422);
        $user->update(['is_active' => ! $user->is_active]);

        return redirect()->route('admin.index', ['tab' => 'users']);
    }

    public function storeCategory(Request $request)
    {
        Category::create($request->validate(['name' => 'required|string|max:255|unique:categories']));

        return redirect()->route('admin.index', ['tab' => 'categories']);
    }

    public function destroyCategory(Category $category)
    {
        if ($category->foundDocuments()->exists() || $category->lostDeclarations()->exists()) {
            return redirect()->route('admin.index', ['tab' => 'categories'])->withErrors(['name' => __('msgCategoryInUse')]);
        }
        $category->delete();

        return redirect()->route('admin.index', ['tab' => 'categories']);
    }

    public function updateFeedback(Request $request, Feedback $feedback)
    {
        $feedback->update($request->validate([
            'status' => 'required|in:'.implode(',', config('docutrack.feedback_statuses')),
            'admin_notes' => 'nullable|string|max:5000',
        ]));

        return redirect()->route('admin.index', ['tab' => 'feedback'])->with('success', __('msgSaved'));
    }

    public function destroyFeedback(Feedback $feedback)
    {
        $feedback->delete();

        return redirect()->route('admin.index', ['tab' => 'feedback'])->with('success', __('msgDeleted'));
    }
}
