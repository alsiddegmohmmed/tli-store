<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCompanyProfileRequest;
use App\Models\CompanyProfile;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CompanyProfileController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('company/edit', ['company' => CompanyProfile::find(1)?->only(CompanyProfile::SELLER_FIELDS)]);
    }

    public function update(UpdateCompanyProfileRequest $request): RedirectResponse
    {
        CompanyProfile::updateOrCreate(['id' => 1], [...$request->validated(), 'updated_by' => $request->user()->id]);

        return to_route('company.edit')->with('success', 'حُفظت بيانات الشركة. التغيير يخص المبيعات الجديدة فقط.');
    }
}
