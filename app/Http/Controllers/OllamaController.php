<?php
namespace App\Http\Controllers;
use App\Services\OllamaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;
class OllamaController extends Controller
{
    public function chat(Request $request, OllamaService $ollama): JsonResponse
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:4000']]);
        try { return response()->json(['reply' => $ollama->chat($data['message'])]); }
        catch (Throwable $e) {
            report($e);
            return response()->json(['message' => 'El asistente no está disponible en este momento.'], 503);
        }
    }
}
