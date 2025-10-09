<?php
// app/Http/Controllers/GatePassController.php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\GatePass;
use Illuminate\Http\Request;
use function redirect;
use function view;

class GatePassController extends Controller
{
    public function create()
    {
        return view('gate_passes.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'visitor_name' => 'required|string',
            'visitor_contact' => 'required|string',
            'entry_time' => 'required|date',
            'exit_time' => 'required|date|after:entry_time',
        ]);
        GatePass::create([
            'resident_id' => auth()->id(),
            'visitor_name' => $request->visitor_name,
            'visitor_contact' => $request->visitor_contact,
            'purpose' => $request->purpose,
            'vehicle_details' => $request->vehicle_details,
            'entry_time' => $request->entry_time,
            'exit_time' => $request->exit_time,
            'status' => 'pending',
            'token' => uniqid(),
        ]);
        return redirect()->route('gate_passes.index')->with('success', 'Gate pass created!');
    }
}