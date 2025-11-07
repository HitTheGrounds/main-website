<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\Request;

class TeamExportController extends Controller
{
    public function export(Request $request)
    {
        $type = $request->query('type', 'all');

        $query = Team::with(['company', 'user', 'members']);

        if ($type === 'locked') {
            $query->where('locked', true);
        }

        $teams = $query->orderBy('team_name')->get();

        return view('admin.teams.export', ['teams' => $teams, 'type' => $type]);
    }
}
