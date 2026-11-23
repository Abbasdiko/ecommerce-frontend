<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NewsletterController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $this->authorizePermission($request, 'newsletter.view');

        $subs = NewsletterSubscriber::query()
            ->when($request->search, fn ($q, $s) => $q->where('email', 'like', "%$s%"))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->source, fn ($q, $s) => $q->where('source', $s))
            ->latest()
            ->paginate($this->perPage($request));

        return $this->ok([
            'subscribers' => $subs,
            'summary' => [
                'total' => NewsletterSubscriber::count(),
                'subscribed' => NewsletterSubscriber::where('status', 'subscribed')->count(),
                'unsubscribed' => NewsletterSubscriber::where('status', 'unsubscribed')->count(),
                'last_30_days' => NewsletterSubscriber::where('created_at', '>=', now()->subDays(30))->count(),
            ],
        ]);
    }

    public function update(Request $request, NewsletterSubscriber $subscriber)
    {
        $this->authorizePermission($request, 'newsletter.view');

        $data = $request->validate(['status' => ['required', 'in:subscribed,unsubscribed']]);
        $subscriber->update($data);
        AuditLogger::record('newsletter.status_changed', $subscriber, [], $data);

        return $this->ok($subscriber->fresh(), 'Subscriber updated');
    }

    public function destroy(Request $request, NewsletterSubscriber $subscriber)
    {
        $this->authorizePermission($request, 'newsletter.view');
        $subscriber->delete();

        return $this->ok(null, 'Subscriber removed');
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorizePermission($request, 'newsletter.view');

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['email', 'name', 'source', 'status', 'subscribed_at']);
            NewsletterSubscriber::orderBy('id')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $r) {
                    fputcsv($out, [$r->email, $r->name, $r->source, $r->status, $r->created_at]);
                }
            });
            fclose($out);
        }, 'newsletter-subscribers.csv', ['Content-Type' => 'text/csv']);
    }
}
