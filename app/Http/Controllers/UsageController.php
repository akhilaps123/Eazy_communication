<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Handles the eSIM usage lookup form (pages/check-usage.blade.php).
 *
 * Form fields posted by the page: order_number, email
 *
 * TODO: Implement in the backend phase (validation, persistence, business
 * logic). Until then this endpoint intentionally responds with HTTP 501 so
 * nothing pretends to work.
 */
class UsageController extends Controller
{
    public function store(Request $request)
    {
        abort(501, 'This feature has not been implemented yet.');
    }
}
