<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

trait ReadsImportFile
{
    /**
     * Read an uploaded JSON import file (or a JSON request body), check its "format"
     * marker and validate it, so errors come back keyed by their path in the file.
     *
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    protected function readImportFile(Request $request, string $format, array $rules): array
    {
        if ($request->hasFile('file')) {
            $request->validate(['file' => ['file', 'max:51200']]);
            $data = json_decode(file_get_contents($request->file('file')->getRealPath()), true);

            if (! is_array($data)) {
                throw ValidationException::withMessages(['file' => 'The file is not valid JSON ('.json_last_error_msg().').']);
            }
        } else {
            $data = $request->except(['dry_run', 'replace']);
        }

        if (isset($data['format']) && $data['format'] !== $format) {
            throw ValidationException::withMessages(['file' => "This is a \"{$data['format']}\" file; expected \"{$format}\"."]);
        }

        Validator::make($data, $rules)->validate();

        return $data;
    }
}
