<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'subject' => 'required|string|max:255',
            'rating' => 'required|integer|between:1,5',
            'message' => 'required|string|max:5000',
        ]);
        Feedback::create($data + ['user_id' => $request->user()?->id]);

        return redirect('/')->with('success', __('msgFeedbackSent'));
    }
}
