<?php

namespace App\Http\Controllers;

use App\Support\AdminCatalog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminPageController extends Controller
{
    public function __construct(private AdminCatalog $catalog) {}

    public function show(Request $request): View
    {
        $page = $request->route('page');

        return view('Admin.ad_pages.'.$page, [
            'page' => $page,
            'title' => $request->route('title'),
            'section' => $request->route('section'),
            'seed' => $this->catalog->data(),
        ]);
    }
}
