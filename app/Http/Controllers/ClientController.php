<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ClientController extends Controller
{
    public function index()
    {
        $clients = Client::all();
        return Inertia::render('Clients/Index', ['clients' => $clients]);
    }

    public function create()
    {
        return Inertia::render('Clients/Create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'detail' => 'nullable|string',
            'fromSA' => 'boolean',
        ]);
        Client::create($data);
        return redirect()->route('leader.clients.index');
    }

    public function edit(Client $client)
    {
        return Inertia::render('Clients/Edit', ['client' => $client]);
    }

    public function update(Request $request, Client $client)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'detail' => 'nullable|string',
            'fromSA' => 'boolean',
        ]);
        $client->update($data);
        return redirect()->route('leader.clients.index');
    }
}
