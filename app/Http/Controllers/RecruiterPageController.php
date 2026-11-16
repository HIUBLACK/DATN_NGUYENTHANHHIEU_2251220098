<?php

namespace App\Http\Controllers;

use App\Services\CompanyProfiles;
use App\Support\RecruiterCatalog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RecruiterPageController extends Controller
{
    public function __construct(private RecruiterCatalog $catalog, private CompanyProfiles $profiles) {}

    public function show(Request $request): View
    {
        $page = $request->route()->defaults['page'];
        $title = $request->route()->defaults['title'];
        $section = $request->route()->defaults['section'];

        $company = $this->profiles->current();
        $seed = $this->catalog->data();
        $profile = $this->profiles->publicData($company);
        $seed['company'] = $profile + array_intersect_key($company, array_flip(['email', 'phone', 'status']));
        $canSaveCompany = $this->profiles->canEdit($company['id']);

        return view('employer.'.$page, [
            'page' => $page,
            'title' => $title,
            'section' => $section,
            'seed' => $seed,
            'companyRecord' => $company,
            'companyForm' => array_intersect_key($company, array_flip(CompanyProfiles::FIELDS)),
            'companyProfile' => $profile,
            'canSaveCompany' => $canSaveCompany,
        ]);
    }
}
