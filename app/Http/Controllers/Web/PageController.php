<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\PageResource;
use App\Models\Page;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    use AuthorizesRequests;

    public function show(Page $page): Response
    {
        $this->authorize('view', $page);

        $page->load('user:id,name');

        return Inertia::render('Pages/Show', [
            'page' => new PageResource($page),
        ]);
    }
}
