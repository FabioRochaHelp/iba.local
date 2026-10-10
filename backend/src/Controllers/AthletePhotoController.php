<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Http\JsonResponse;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Validation\Validator;
use App\Services\Athletes\AthletePhotoService;

final class AthletePhotoController extends Controller
{
    public function __construct(Validator $validator, private AthletePhotoService $photos)
    {
        parent::__construct($validator);
    }

    public function show(Request $request): Response
    {
        $photo = $this->photos->get($this->id($request), $this->user($request));
        $etag = '"' . $photo['version'] . '"';
        // A URL leva ?v=<versão>; cache só no navegador do usuário logado (private).
        $headers = ['Content-Type' => $photo['mime'], 'Cache-Control' => 'private, max-age=604800', 'ETag' => $etag];

        if (trim((string) $request->header('if-none-match', '')) === $etag) {
            return new Response('', 304, $headers);
        }

        return new Response($photo['data'], 200, $headers + ['Content-Length' => (string) strlen($photo['data'])]);
    }

    public function update(Request $request): JsonResponse
    {
        // ~1 MB de JSON (limite do JsonBodyGuard) comporta a foto já reduzida pelo navegador.
        $data = $this->validate($request, ['image' => 'required|string|max:1000000']);
        $version = $this->photos->save($this->id($request), $data['image'], $this->user($request), $request);

        return JsonResponse::data(['photo_version' => $version]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $this->photos->delete($this->id($request), $request);

        return JsonResponse::noContent();
    }
}
