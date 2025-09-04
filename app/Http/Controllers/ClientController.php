<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class ClientController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        if ($user && $user->user_role === 'superadmin') {
            $clients = Client::all();
        } else {
            $companyId = $user->company_id ?? null;
            $clients = Client::forCompany($companyId)->get();
        }

        return Inertia::render('Clients/Index', ['clients' => $clients]);
    }

    public function create()
    {
        $this->authorize('create', Client::class);
        return Inertia::render('Clients/Create');
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $this->authorize('create', Client::class);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'detail' => 'nullable|string',
            'fromSA' => 'boolean',
            'company_id' => 'nullable|exists:companies,id',
        ]);

        // Non-superadmin users may only create clients for their own company
        if (!($user && $user->user_role === 'superadmin')) {
            $data['company_id'] = $user->company_id ?? null;
        }

        Client::create($data);
        return redirect()->route('leader.clients.index');
    }

    public function edit(Client $client)
    {
        $this->authorize('view', $client);
        return Inertia::render('Clients/Edit', ['client' => $client]);
    }

    public function update(Request $request, Client $client)
    {
        $this->authorize('update', $client);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'detail' => 'nullable|string',
            'fromSA' => 'boolean',
            'company_id' => 'nullable|exists:companies,id',
        ]);

        // Non-superadmin users should not be able to change company_id
        $user = Auth::user();
        if (!($user && $user->user_role === 'superadmin')) {
            unset($data['company_id']);
        }

        $client->update($data);
        return redirect()->route('leader.clients.index');
    }

    public function show(Client $client)
    {
        $this->authorize('view', $client);
        return Inertia::render('Clients/Show', ['client' => $client]);
    }

    public function destroy(Client $client)
    {
        $this->authorize('delete', $client);
        $client->delete();
        return redirect()->route('leader.clients.index');
    }
}
