<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Models\Filing;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class PipelinePageController extends Controller
{
    public function __invoke(): Response
    {
        Gate::authorize('viewAny', Filing::class);

        return Inertia::render('Pipeline/Index');
    }
}
