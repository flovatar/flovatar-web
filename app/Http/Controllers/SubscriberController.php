<?php

namespace App\Http\Controllers;

use App\Models\Subscriber;
use App\Jobs\AddMailSubscriber;
use Illuminate\Http\Request;

class SubscriberController extends Controller
{

    public function store(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email:rfc,filter|max:255',
        ]);


        $subscriber = Subscriber::firstOrCreate([
            'email' => $validated['email']
        ]);

        AddMailSubscriber::dispatch($subscriber);

        return redirect('/')->with(['subscribed' => true]);
    }

}
