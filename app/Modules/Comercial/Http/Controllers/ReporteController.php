<?php

namespace App\Modules\Comercial\Http\Controllers;

use App\Modules\Comercial\Models\Cliente;
use App\Modules\Comercial\Models\Cotizacion;
use App\Modules\Comercial\Models\Parametro;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReporteController
{
    public function cotizaciones(Request $request)
    {
        $query = $this->queryCotizaciones($request);

        $resumenQuery = clone $query;
        $totalCotizaciones = (clone $resumenQuery)->count();
        $vigentes = (clone $resumenQuery)->whereIn('estado', Cotizacion::estadosParaFiltro(Cotizacion::ESTADO_VIGENTE))->count();
        $valorTotal = (clone $resumenQuery)->sum('precio_venta');
        $valorPromedio = $totalCotizaciones > 0 ? $valorTotal / $totalCotizaciones : 0;

        $cotizaciones = $query->latest('fecha_cotizacion')->paginate(50)->withQueryString();
        $clientes = Cliente::orderBy('nombre')->get();

        return view('comercial::reportes.cotizaciones', compact(
            'cotizaciones',
            'clientes',
            'totalCotizaciones',
            'vigentes',
            'valorTotal',
            'valorPromedio'
        ));
    }

    public function clientes(Request $request)
    {
        $query = Cliente::withCount('cotizaciones', 'centrosCosto');

        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        $totalClientes = (clone $query)->count();
        $clientesActivos = (clone $query)->where('estado', 'activo')->count();
        $totalCotizaciones = Cotizacion::count();
        $cotizacionesVigentes = Cotizacion::whereIn('estado', Cotizacion::estadosParaFiltro(Cotizacion::ESTADO_VIGENTE))->count();
        $clientes = $query->paginate(50)->withQueryString();

        return view('comercial::reportes.clientes', compact(
            'clientes',
            'totalClientes',
            'clientesActivos',
            'totalCotizaciones',
            'cotizacionesVigentes'
        ));
    }

    public function exportExcel(Request $request)
    {
        $cotizaciones = $this->queryCotizaciones($request)
            ->latest('fecha_cotizacion')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Cotizaciones');
        $sheet->fromArray([
            'Numero',
            'Titulo',
            'Cargo',
            'Cliente',
            'Centro Costo',
            'Modalidad',
            'Estado',
            'Fecha',
            'Total Haberes',
            'Cotizaciones',
            'Provisiones',
            'Gastos',
            'Subtotal',
            'Margen',
            'Precio Venta',
        ], null, 'A1');

        $row = 2;
        foreach ($cotizaciones as $cotizacion) {
            $sheet->fromArray([
                $cotizacion->numero,
                $cotizacion->titulo,
                $cotizacion->cargo,
                $cotizacion->cliente?->nombre_comercial ?? $cotizacion->cliente?->nombre,
                $cotizacion->centroCosto?->codigo,
                $cotizacion->modalidad?->codigo,
                Cotizacion::etiquetaEstado($cotizacion->estado),
                optional($cotizacion->fecha_cotizacion)->format('Y-m-d'),
                (float) $cotizacion->total_remuneraciones,
                (float) $cotizacion->total_cotizaciones,
                (float) $cotizacion->total_provisiones,
                (float) $cotizacion->total_gastos,
                (float) $cotizacion->subtotal,
                (float) $cotizacion->margen,
                (float) $cotizacion->precio_venta,
            ], null, "A{$row}");
            $row++;
        }

        foreach (range('A', 'O') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $catalogoSheet = $spreadsheet->createSheet();
        $catalogoSheet->setTitle('Catalogo Uniformes');
        $catalogoSheet->fromArray([
            'Clave',
            'Item',
            'Precio Referencial',
            'Actualizado',
        ], null, 'A1');

        $uniformes = Parametro::editables()
            ->porCategoria('UNIFORMES')
            ->orderBy('nombre')
            ->get();

        $catalogRow = 2;
        foreach ($uniformes as $uniforme) {
            $catalogoSheet->fromArray([
                $uniforme->clave,
                $uniforme->nombre,
                (float) $uniforme->valor_actual,
                optional($uniforme->updated_at)->format('Y-m-d H:i:s'),
            ], null, "A{$catalogRow}");
            $catalogRow++;
        }

        foreach (range('A', 'D') as $column) {
            $catalogoSheet->getColumnDimension($column)->setAutoSize(true);
        }

        $spreadsheet->setActiveSheetIndex(0);

        $path = tempnam(sys_get_temp_dir(), 'comercial_cotizaciones_').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return response()->download($path, 'cotizaciones-comercial-saep.xlsx')->deleteFileAfterSend(true);
    }

    private function queryCotizaciones(Request $request)
    {
        $query = Cotizacion::with(['cliente', 'centroCosto', 'modalidad']);

        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->integer('cliente_id'));
        }

        if ($request->filled('estado')) {
            $query->whereIn('estado', Cotizacion::estadosParaFiltro($request->input('estado')));
        }

        if ($request->filled('fecha_desde')) {
            $query->whereDate('fecha_cotizacion', '>=', $request->input('fecha_desde'));
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fecha_cotizacion', '<=', $request->input('fecha_hasta'));
        }

        return $query;
    }
}
