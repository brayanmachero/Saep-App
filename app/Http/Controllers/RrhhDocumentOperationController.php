<?php

namespace App\Http\Controllers;

use App\Services\RrhhDocumentLivePilot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class RrhhDocumentOperationController extends Controller
{
    public function store(Request $request, string $id, RrhhDocumentLivePilot $service)
    {
        $data = $request->validate(['action'=>'required|in:upload,signature,refresh', 'confirmed'=>'exclude_if:action,refresh|required|accepted']);
        $lock = Cache::lock('rrhh-document-operation:'.$id, 300);
        if (!$lock->get()) return back()->withErrors(['operation'=>'Hay una operación en curso. Espera y actualiza la página.']);
        try {
            $message = $service->run($id, $data['action'], (int)$request->user()->id);
            return back()->with('success', $message);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['operation'=>$e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);
            return back()->withErrors(['operation'=>'No se pudo confirmar la operación. Revisa el estado antes de repetir; no se reintentó automáticamente.']);
        } finally { $lock->release(); }
    }
}
