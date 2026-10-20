<?php

namespace App\Http\Controllers;

use App\Jobs\ImportLektionPdf;
use App\Models\Category;
use App\Models\ImportPreview;
use App\Models\Lektion;
use App\Models\Word;
use App\Services\Import\AnthropicClient;
use App\Services\Import\ImportPersister;
use App\Services\Import\ImportSchemaValidator;
use App\Services\Import\LektionParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ImportController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Import/Index', [
            'previews' => $request->user()->importPreviews()->latest()->limit(20)->get()
                ->map(fn (ImportPreview $p) => $p->only(['id', 'lektion_number', 'title', 'status', 'error', 'chunks_done', 'chunks_total', 'created_at'])
                    + ['words' => count($p->payload['words'] ?? [])]),
            'lektionen' => Lektion::orderBy('number')->get(['id', 'number', 'title']),
            'anthropicConfigured' => AnthropicClient::fromConfig()->isConfigured(),
            'model' => config('trainer.anthropic.model'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:20480'],
            'number' => ['required', 'integer', 'min:1', 'max:999'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $disk = config('trainer.import.disk');
        $path = $request->file('pdf')->storeAs(
            config('trainer.import.pdf_dir'),
            'lektion-'.$data['number'].'-'.now()->format('Ymd-His').'.pdf',
            $disk
        );

        $preview = $request->user()->importPreviews()->create([
            'lektion_number' => $data['number'],
            'title' => $data['title'] ?: 'Lektion '.$data['number'],
            'pdf_path' => $path,
            'status' => 'pending',
        ]);

        ImportLektionPdf::dispatch($preview);

        return redirect()->route('import.show', $preview);
    }

    public function show(Request $request, ImportPreview $preview): Response
    {
        abort_unless($preview->user_id === $request->user()->id, 403);

        $existing = Lektion::where('number', $preview->lektion_number)->first();
        $existingWords = $existing
            ? $existing->words()->pluck('german')->map(fn ($g) => mb_strtolower($g))->flip()->all()
            : [];

        $payload = $preview->payload ?? ['words' => [], 'conjugations' => [], 'dialog_lines' => [], 'grammar_notes' => []];
        $payload['words'] = array_values(array_map(function (array $w, int $i) use ($existingWords) {
            $w['_key'] = $i;
            $w['_duplicate'] = isset($existingWords[mb_strtolower(trim($w['german']))]);

            return $w;
        }, $payload['words'], array_keys($payload['words'])));

        return Inertia::render('Import/Preview', [
            'preview' => $preview->only(['id', 'lektion_number', 'title', 'status', 'error', 'chunks_done', 'chunks_total', 'imported_at']),
            'payload' => $payload,
            'types' => Word::TYPES,
            'categories' => Category::CANONICAL,
            'rawTextExcerpt' => $preview->raw_text ? mb_substr($preview->raw_text, 0, 4000) : null,
        ]);
    }

    /** Polling endpoint for the preview page while the job runs. */
    public function status(Request $request, ImportPreview $preview)
    {
        abort_unless($preview->user_id === $request->user()->id, 403);

        return response()->json($preview->only(['status', 'error', 'chunks_done', 'chunks_total']));
    }

    /** Save edited rows (whole payload replaced). */
    public function update(Request $request, ImportPreview $preview, ImportSchemaValidator $validator, LektionParser $parser): RedirectResponse
    {
        abort_unless($preview->user_id === $request->user()->id, 403);

        $payload = $request->validate(['payload' => ['required', 'array']])['payload'];
        $payload += ['words' => [], 'conjugations' => [], 'dialog_lines' => [], 'grammar_notes' => []];
        $payload['words'] = array_map(fn ($w) => collect($w)->except(['_key', '_duplicate'])->all(), $payload['words']);

        $result = $validator->validate($payload);
        if (! $result['valid']) {
            return back()->withErrors(['payload' => implode("\n", array_slice($result['errors'], 0, 10))]);
        }

        $preview->update(['payload' => $parser->postProcess($payload)]);

        return back()->with('success', __('messages.preview_saved'));
    }

    public function confirm(Request $request, ImportPreview $preview, ImportSchemaValidator $validator, ImportPersister $persister): RedirectResponse
    {
        abort_unless($preview->user_id === $request->user()->id, 403);
        abort_unless($preview->status === 'ready', 422, 'Preview is not ready.');

        $result = $validator->validate($preview->payload);
        if (! $result['valid']) {
            return back()->withErrors(['payload' => implode("\n", array_slice($result['errors'], 0, 10))]);
        }

        $saved = $persister->persist($preview->payload, $preview->lektion_number, $preview->title, $preview->pdf_path);
        $preview->update(['status' => 'imported', 'imported_at' => now()]);

        return redirect()->route('lektionen.show', $saved['lektion'])
            ->with('success', __('messages.import_done', [
                'words' => $saved['words'], 'skipped' => $saved['skipped'], 'conjugations' => $saved['conjugations'],
                'dialog' => $saved['dialog_lines'], 'notes' => $saved['grammar_notes'],
            ]));
    }

    public function retry(Request $request, ImportPreview $preview): RedirectResponse
    {
        abort_unless($preview->user_id === $request->user()->id, 403);
        $preview->update(['status' => 'pending', 'error' => null, 'chunks_done' => 0]);
        ImportLektionPdf::dispatch($preview);

        return back();
    }

    public function destroy(Request $request, ImportPreview $preview): RedirectResponse
    {
        abort_unless($preview->user_id === $request->user()->id, 403);
        if ($preview->status !== 'imported') {
            Storage::disk(config('trainer.import.disk'))->delete($preview->pdf_path);
        }
        $preview->delete();

        return redirect()->route('import.index');
    }
}
