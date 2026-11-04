<?php

namespace App\Http\Controllers;

use App\Support\RecruiterCatalog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RecruiterPageController extends Controller
{
    public function __construct(private RecruiterCatalog $catalog) {}

    public function show(Request $request): View
    {
        $page = $request->route()->defaults['page'];
        $title = $request->route()->defaults['title'];
        $section = $request->route()->defaults['section'];

        return view('employer.'.$page, [
            'page' => $page,
            'title' => $title,
            'section' => $section,
            'seed' => $this->catalog->data(),
        ]);
    }
}
