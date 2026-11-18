<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\LegalPage;
use Illuminate\Contracts\View\View;

class LegalPageController extends Controller
{
    public function __invoke(string $slug): View
    {
        $page = LegalPage::findPublished($slug);

        abort_if($page === null, 404);

        $company = CompanySetting::query()->first();

        return view('legal.show', [
            'page' => $page,
            'company' => $company,
            'body' => $page->renderBody($company),
        ]);
    }
}
