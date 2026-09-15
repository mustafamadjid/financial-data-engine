<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class PipelinePageController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Pipeline/Index');
    }
}
