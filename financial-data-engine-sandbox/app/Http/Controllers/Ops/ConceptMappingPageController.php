<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class ConceptMappingPageController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('ConceptMapping/Index');
    }
}
