<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\AboutPageContent;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class AboutPageController extends Controller
{
    use ApiResponse;

    public function show(Request $request)
    {
        $this->authorizePermission($request, 'setting.view');

        return $this->ok(AboutPageContent::get());
    }

    public function update(Request $request)
    {
        $this->authorizePermission($request, 'setting.update');

        $content = $request->validate([
            'eyebrow' => ['required', 'string', 'max:120'],
            'hero_title' => ['required', 'string', 'max:220'],
            'hero_body' => ['required', 'string', 'max:1500'],
            'stats' => ['required', 'array', 'min:1', 'max:4'],
            'stats.*.value' => ['required', 'string', 'max:60'],
            'stats.*.label' => ['required', 'string', 'max:100'],
            'story_heading' => ['required', 'string', 'max:160'],
            'story_name' => ['nullable', 'string', 'max:120'],
            'story_role' => ['nullable', 'string', 'max:120'],
            'story_photo' => ['nullable', 'url:http,https', 'max:500'],
            'story_bio' => ['required', 'string', 'max:3000'],
            'story_quote' => ['nullable', 'string', 'max:500'],
            'story_link' => ['nullable', 'url:http,https', 'max:500'],
            'values' => ['required', 'array', 'min:1', 'max:8'],
            'values.*.title' => ['required', 'string', 'max:120'],
            'values.*.description' => ['required', 'string', 'max:500'],
            'buyers_title' => ['required', 'string', 'max:120'],
            'buyer_steps' => ['required', 'array', 'min:1', 'max:8'],
            'buyer_steps.*' => ['required', 'string', 'max:300'],
            'agents_title' => ['required', 'string', 'max:120'],
            'agent_steps' => ['required', 'array', 'min:1', 'max:8'],
            'agent_steps.*' => ['required', 'string', 'max:300'],
            'milestones' => ['required', 'array', 'min:1', 'max:12'],
            'milestones.*.year' => ['required', 'string', 'max:60'],
            'milestones.*.title' => ['required', 'string', 'max:120'],
            'milestones.*.description' => ['required', 'string', 'max:400'],
            'team_members' => ['present', 'array', 'max:20'],
            'team_members.*.name' => ['required', 'string', 'max:120'],
            'team_members.*.role' => ['required', 'string', 'max:120'],
            'team_members.*.photo' => ['nullable', 'url:http,https', 'max:500'],
            'team_members.*.bio' => ['nullable', 'string', 'max:400'],
            'team_members.*.link' => ['nullable', 'url:http,https', 'max:500'],
            'faqs' => ['required', 'array', 'min:1', 'max:20'],
            'faqs.*.question' => ['required', 'string', 'max:240'],
            'faqs.*.answer' => ['required', 'string', 'max:1000'],
        ]);

        AboutPageContent::save($content);
        AuditLogger::record('about_page.updated', null, [], ['team_members' => count($content['team_members'])]);

        return $this->ok(AboutPageContent::get(), 'About page content saved');
    }
}
