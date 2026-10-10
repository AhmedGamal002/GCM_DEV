<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * The largest page a list endpoint will return. The biggest the UI asks for is 1000
     * (DataTables' "All"); without a cap `?per_page=1000000` loads a whole table in one request.
     */
    protected const MAX_PER_PAGE = 1000;

    /** `per_page` from the request, clamped to 1..MAX_PER_PAGE (a missing, zero or negative value gives $default). */
    protected function perPage(Request $request, int $default = 15): int
    {
        $perPage = $request->integer('per_page', $default);

        return $perPage < 1 ? $default : min($perPage, self::MAX_PER_PAGE);
    }
}
