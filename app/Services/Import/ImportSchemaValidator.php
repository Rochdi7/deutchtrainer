<?php

namespace App\Services\Import;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

class ImportSchemaValidator
{
    private Validator $validator;

    private string $schemaId = 'https://deutschtrainer.local/schemas/import.json';

    public function __construct()
    {
        $this->validator = new Validator;
        $this->validator->resolver()->registerFile(
            $this->schemaId,
            resource_path('schemas/import.schema.json')
        );
    }

    /**
     * @return array{valid: bool, errors: array<int, string>}
     */
    public function validate(mixed $payload): array
    {
        // Opis works on objects, not associative arrays.
        $data = json_decode(json_encode($payload));
        $result = $this->validator->validate($data, $this->schemaId);

        if ($result->isValid()) {
            return ['valid' => true, 'errors' => []];
        }

        $formatted = (new ErrorFormatter)->format($result->error(), true);
        $errors = [];
        foreach ($formatted as $path => $messages) {
            foreach ((array) $messages as $m) {
                $errors[] = $path.': '.$m;
            }
        }

        return ['valid' => false, 'errors' => $errors];
    }

    /** Decode a model reply that may be wrapped in ```json fences or chatter. Returns null if no JSON object found. */
    public function extractJson(string $raw): ?array
    {
        $raw = trim($raw);

        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/su', $raw, $m)) {
            $raw = $m[1];
        } elseif (! str_starts_with($raw, '{')) {
            $start = strpos($raw, '{');
            $end = strrpos($raw, '}');
            if ($start === false || $end === false || $end <= $start) {
                return null;
            }
            $raw = substr($raw, $start, $end - $start + 1);
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }
}
