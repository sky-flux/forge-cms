<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\PageResource;
use App\Models\Page;
use App\Settings\GeneralSettings;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    use AuthorizesRequests;

    public function show(Page $page): Response
    {
        $this->authorize('view', $page);

        $page->load([
            'user:id,name',
            'approvedComments' => fn ($q) => $q->with('user:id,name')->whereNull('parent_id')->orderBy('created_at', 'asc'),
            'approvedComments.approvedChildren' => fn ($q) => $q->with('user:id,name')->orderBy('created_at', 'asc'),
            'approvedComments.approvedChildren.approvedChildren' => fn ($q) => $q->with('user:id,name')->orderBy('created_at', 'asc'),
        ]);

        $featuredUrl = $page->getFirstMediaUrl('featured') ?: null;

        return Inertia::render('Pages/Show', [
            'page' => new PageResource($page),
            'canonical' => route('pages.show', ['page' => $page]),
            'ogImage' => $featuredUrl ?? app(GeneralSettings::class)->default_og_image,
        ]);
    }
}
