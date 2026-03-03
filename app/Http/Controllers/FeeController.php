<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * FeeController — DISABLED
 *
 * Fee Management has been removed from this application.
 * This stub controller prevents autoload errors.
 * All routes pointing to this controller are commented out in routes/web.php.
 */
class FeeController extends Controller
{
    public function index()            { abort(404, 'Fee Management has been disabled.'); }
    public function create()           { abort(404, 'Fee Management has been disabled.'); }
    public function store()            { abort(404, 'Fee Management has been disabled.'); }
    public function show()             { abort(404, 'Fee Management has been disabled.'); }
    public function edit()             { abort(404, 'Fee Management has been disabled.'); }
    public function update()           { abort(404, 'Fee Management has been disabled.'); }
    public function destroy()          { abort(404, 'Fee Management has been disabled.'); }
    public function toggleStatus()     { abort(404, 'Fee Management has been disabled.'); }
    public function assignToStudents() { abort(404, 'Fee Management has been disabled.'); }
}