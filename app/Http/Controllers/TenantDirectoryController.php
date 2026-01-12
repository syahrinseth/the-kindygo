<?php

namespace App\Http\Controllers;

use App\Models\Tenant;

class TenantDirectoryController extends Controller
{
    public function index()
    {
        $tenants = Tenant::where('personal_tenant', false)
            ->orderBy('name')
            ->get();

        return view('tenant-directory', compact('tenants'));
    }
}
