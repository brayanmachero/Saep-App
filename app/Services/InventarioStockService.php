<?php

namespace App\Services;

use App\Models\Configuracion;
use App\Models\EntregaBodega;
use App\Models\InventarioCentroCosto;
use App\Models\InventarioConteo;
use App\Models\InventarioConteoLinea;
use App\Models\InventarioCoordinador;
use App\Models\InventarioEntregaKizeoAplicacion;
use App\Models\InventarioEntregaKizeoLinea;
use App\Models\InventarioHistorialCosto;
use App\Models\InventarioImportacionMovimiento;
use App\Models\InventarioIngreso;
use App\Models\InventarioIngresoItem;
use App\Models\InventarioMovimiento;
use App\Models\InventarioProducto;
use App\Models\InventarioProveedor;
use App\Models\InventarioUbicacion;
use App\Models\InventarioVariante;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class InventarioStockService
{
    public const KIZEO_ORIGIN_LOCATION_CODE = 'SAEP-CENTRAL';

    public const KIZEO_AUTO_APPLY_KEY = 'inventario_kizeo_auto_aplicar';

    public const KIZEO_AUTO_APPLY_SINCE_KEY = 'inventario_kizeo_auto_aplicar_desde';

    public const KIZEO_AUTO_APPLY_BY_KEY = 'inventario_kizeo_auto_aplicar_por';

    public function createProduct(array $data, User $user): InventarioProducto
    {
        $requestedCode = trim((string) ($data['codigo'] ?? ''));
        if ($requestedCode !== '' && InventarioProducto::query()->where('codigo', $requestedCode)->exists()) {
            throw ValidationException::withMessages(['codigo' => 'Ya existe un producto con ese codigo.']);
        }

        $product = InventarioProducto::create([
            'codigo' => $requestedCode !== '' ? $requestedCode : $this->availableProductCode(Str::upper(Str::slug($data['nombre'], '-'))),
            'nombre' => trim($data['nombre']),
            'tipo' => $this->nullable($data['tipo'] ?? null),
            'categoria' => $this->nullable($data['categoria'] ?? null),
            'subcategoria' => $this->nullable($data['subcategoria'] ?? null),
            'unidad_medida' => $this->nullable($data['unidad_medida'] ?? null) ?: 'Unidad',
            'stock_minimo' => $this->decimal($data['stock_minimo'] ?? 0),
            'activo' => (bool) ($data['activo'] ?? true),
            'creado_por' => $user->id,
        ]);

        $this->syncProductVariants($product, $data['tallas'] ?? null);

        return $product;
    }

    public function updateProduct(InventarioProducto $product, array $data): void
    {
        $product->update([
            'nombre' => trim($data['nombre']),
            'tipo' => $this->nullable($data['tipo'] ?? null),
            'categoria' => $this->nullable($data['categoria'] ?? null),
            'subcategoria' => $this->nullable($data['subcategoria'] ?? null),
            'unidad_medida' => $this->nullable($data['unidad_medida'] ?? null) ?: 'Unidad',
            'stock_minimo' => $this->decimal($data['stock_minimo'] ?? 0),
            'activo' => (bool) ($data['activo'] ?? false),
        ]);

        $this->syncProductVariants($product, $data['tallas'] ?? null);
    }

    public function balances(?int $ubicacionId = null): Collection
    {
        $movementQuery = DB::table('inventario_movimientos')
            ->selectRaw('variante_id, SUM(cantidad) as stock_actual')
            ->when($ubicacionId, fn ($query) => $query->where('ubicacion_id', $ubicacionId))
            ->groupBy('variante_id');

        return InventarioVariante::query()
            ->with('producto')
            ->leftJoinSub($movementQuery, 'saldos', fn ($join) => $join->on('saldos.variante_id', '=', 'inventario_variantes.id'))
            ->where('inventario_variantes.activo', true)
            ->whereHas('producto', fn ($query) => $query->where('activo', true))
            ->select('inventario_variantes.*')
            ->selectRaw('COALESCE(saldos.stock_actual, 0) as stock_actual')
            ->orderBy('inventario_variantes.talla')
            ->get();
    }

    public function stockActual(int $ubicacionId, int $varianteId): float
    {
        return (float) InventarioMovimiento::query()
            ->where('ubicacion_id', $ubicacionId)
            ->where('variante_id', $varianteId)
            ->sum('cantidad');
    }

    public function registerReceipt(array $data, array $items, User $user): InventarioIngreso
    {
        return DB::transaction(function () use ($data, $items, $user) {
            $ingreso = InventarioIngreso::create([
                'codigo' => $this->code('ING'),
                'ubicacion_id' => $data['ubicacion_id'],
                'proveedor_id' => $data['proveedor_id'] ?: null,
                'tipo_documento' => $data['tipo_documento'],
                'numero_documento' => $data['numero_documento'] ?: null,
                'fecha_documento' => $data['fecha_documento'] ?: null,
                'fecha_recepcion' => $data['fecha_recepcion'],
                'observacion' => $data['observacion'] ?: null,
                'registrado_por' => $user->id,
            ]);

            foreach ($items as $item) {
                $variante = InventarioVariante::query()->with('producto')->findOrFail($item['variante_id']);
                $cantidad = (float) $item['cantidad'];

                InventarioIngresoItem::create([
                    'ingreso_id' => $ingreso->id,
                    'producto_id' => $variante->producto_id,
                    'variante_id' => $variante->id,
                    'cantidad' => $cantidad,
                    'costo_unitario' => $item['costo_unitario'] ?: null,
                ]);

                $this->createMovement([
                    'tipo' => 'INGRESO_COMPRA',
                    'origen' => 'INGRESO_BODEGA',
                    'ubicacion_id' => $ingreso->ubicacion_id,
                    'producto_id' => $variante->producto_id,
                    'variante_id' => $variante->id,
                    'cantidad' => $cantidad,
                    'costo_unitario' => $item['costo_unitario'] ?: null,
                    'referencia_tipo' => InventarioIngreso::class,
                    'referencia_id' => $ingreso->id,
                    'documento_tipo' => $ingreso->tipo_documento,
                    'documento_numero' => $ingreso->numero_documento,
                    'observacion' => $ingreso->observacion,
                    'ocurrido_en' => Carbon::parse($ingreso->fecha_recepcion)->startOfDay(),
                ], $user);

                $this->syncReferenceCost(
                    $variante,
                    $item['costo_unitario'] ?? null,
                    $user,
                    'INGRESO_BODEGA',
                    InventarioIngreso::class,
                    $ingreso->id,
                    Carbon::parse($ingreso->fecha_recepcion)->endOfDay(),
                );
            }

            return $ingreso;
        });
    }

    public function reverseReceipt(InventarioIngreso $receipt, string $reason, User $user): void
    {
        DB::transaction(function () use ($receipt, $reason, $user) {
            $receipt = InventarioIngreso::query()
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($receipt->id);

            if ($receipt->reversado_en) {
                throw ValidationException::withMessages([
                    'ingreso' => 'Este ingreso ya fue anulado y no puede reversarse nuevamente.',
                ]);
            }

            $quantitiesByVariant = $receipt->items
                ->groupBy('variante_id')
                ->map(fn (Collection $items) => (float) $items->sum('cantidad'));
            foreach ($quantitiesByVariant as $variantId => $quantity) {
                $this->ensureAvailability($receipt->ubicacion_id, (int) $variantId, $quantity);
            }

            $originalMovements = InventarioMovimiento::query()
                ->where('referencia_tipo', InventarioIngreso::class)
                ->where('referencia_id', $receipt->id)
                ->where('tipo', 'INGRESO_COMPRA')
                ->orderBy('id')
                ->get()
                ->groupBy('variante_id');

            foreach ($receipt->items as $item) {
                /** @var InventarioMovimiento|null $original */
                $original = ($originalMovements[$item->variante_id] ?? collect())->shift();
                if (! $original) {
                    throw ValidationException::withMessages([
                        'ingreso' => 'No se encontro el movimiento original de este ingreso. Contacta a soporte antes de anularlo.',
                    ]);
                }

                $this->createMovement([
                    'tipo' => 'REVERSO',
                    'origen' => 'REVERSO_INGRESO_BODEGA',
                    'ubicacion_id' => $receipt->ubicacion_id,
                    'producto_id' => $item->producto_id,
                    'variante_id' => $item->variante_id,
                    'cantidad' => -abs((float) $item->cantidad),
                    'costo_unitario' => $item->costo_unitario,
                    'referencia_tipo' => InventarioIngreso::class,
                    'referencia_id' => $receipt->id,
                    'documento_tipo' => $receipt->tipo_documento,
                    'documento_numero' => $receipt->numero_documento,
                    'observacion' => 'Anulacion de ingreso '.$receipt->codigo.': '.$reason,
                    'ocurrido_en' => now(),
                    'reverso_de_id' => $original->id,
                ], $user);
            }

            $receipt->update([
                'reversado_por' => $user->id,
                'reversado_en' => now(),
                'motivo_reversion' => $reason,
            ]);
        });
    }

    public function reverseManualMovement(InventarioMovimiento $movement, string $reason, User $user): void
    {
        DB::transaction(function () use ($movement, $reason, $user) {
            $movement = InventarioMovimiento::query()->lockForUpdate()->findOrFail($movement->id);

            if ($movement->origen !== 'MANUAL' || $movement->tipo === 'REVERSO') {
                throw ValidationException::withMessages([
                    'movimiento' => 'Este movimiento se generó desde otro proceso. Debe anularse desde su ingreso, entrega Kizeo o proceso de origen.',
                ]);
            }

            $originals = $movement->grupo_traslado
                ? InventarioMovimiento::query()
                    ->where('grupo_traslado', $movement->grupo_traslado)
                    ->where('origen', 'MANUAL')
                    ->lockForUpdate()
                    ->orderBy('id')
                    ->get()
                : collect([$movement]);

            if ($originals->isEmpty() || ($movement->grupo_traslado && $originals->count() !== 2)) {
                throw ValidationException::withMessages([
                    'movimiento' => 'No fue posible recuperar todos los movimientos del traslado para anularlos de forma segura.',
                ]);
            }

            if (InventarioMovimiento::query()->whereIn('reverso_de_id', $originals->pluck('id'))->exists()) {
                throw ValidationException::withMessages([
                    'movimiento' => 'Este movimiento ya fue anulado y no puede reversarse nuevamente.',
                ]);
            }

            foreach ($originals as $original) {
                if ((float) $original->cantidad > 0) {
                    $this->ensureAvailability($original->ubicacion_id, $original->variante_id, (float) $original->cantidad);
                }
            }

            $reverseGroup = $movement->grupo_traslado ? (string) Str::uuid() : null;
            foreach ($originals as $original) {
                $this->createMovement([
                    'tipo' => 'REVERSO',
                    'origen' => 'REVERSO_MOVIMIENTO_MANUAL',
                    'ubicacion_id' => $original->ubicacion_id,
                    'producto_id' => $original->producto_id,
                    'variante_id' => $original->variante_id,
                    'cantidad' => -1 * (float) $original->cantidad,
                    'costo_unitario' => $original->costo_unitario,
                    'grupo_traslado' => $reverseGroup,
                    'referencia_tipo' => InventarioMovimiento::class,
                    'referencia_id' => $original->id,
                    'documento_tipo' => $original->documento_tipo,
                    'documento_numero' => $original->documento_numero,
                    'destinatario_nombre' => $original->destinatario_nombre,
                    'destinatario_rut' => $original->destinatario_rut,
                    'centro_costo' => $original->centro_costo,
                    'centro_costo_id' => $original->centro_costo_id,
                    'coordinador_id' => $original->coordinador_id,
                    'observacion' => 'Anulación de movimiento '.$original->codigo.': '.trim($reason),
                    'ocurrido_en' => now(),
                    'reverso_de_id' => $original->id,
                ], $user);
            }
        });
    }

    public function setVariantStock(array $data, User $user): float
    {
        return DB::transaction(function () use ($data, $user) {
            $location = InventarioUbicacion::query()->where('activo', true)->find($data['ubicacion_id']);
            if (! $location) {
                throw ValidationException::withMessages(['ubicacion_id' => 'Selecciona una ubicacion activa.']);
            }

            $variant = InventarioVariante::query()
                ->with('producto')
                ->where('activo', true)
                ->whereHas('producto', fn ($query) => $query->where('activo', true))
                ->find($data['variante_id']);
            if (! $variant) {
                throw ValidationException::withMessages(['variante_id' => 'Selecciona un articulo y talla activos.']);
            }

            $currentStock = $this->stockActual($location->id, $variant->id);
            $targetStock = $this->decimal($data['stock_final']);
            $difference = $targetStock - $currentStock;
            if (abs($difference) < 0.0001) {
                return $targetStock;
            }

            $this->createMovement([
                'tipo' => $difference > 0 ? 'AJUSTE_POSITIVO' : 'AJUSTE_NEGATIVO',
                'origen' => 'AJUSTE_STOCK_TALLA',
                'ubicacion_id' => $location->id,
                'producto_id' => $variant->producto_id,
                'variante_id' => $variant->id,
                'cantidad' => $difference,
                'documento_tipo' => 'AJUSTE_STOCK_TALLA',
                'documento_numero' => $variant->codigo,
                'observacion' => 'Saldo fijado desde Catalogo para talla '.$variant->talla.': '.trim($data['observacion']),
                'ocurrido_en' => now(),
            ], $user);

            return $targetStock;
        });
    }

    public function registerManualMovement(array $data, User $user): InventarioMovimiento
    {
        return DB::transaction(function () use ($data, $user) {
            $variante = InventarioVariante::query()->with('producto')->findOrFail($data['variante_id']);
            $cantidad = (float) $data['cantidad'];
            $tipo = $data['tipo'];

            if ($tipo === 'TRASLADO') {
                $destino = (int) $data['ubicacion_destino_id'];
                if ($destino === (int) $data['ubicacion_id']) {
                    throw ValidationException::withMessages(['ubicacion_destino_id' => 'Selecciona una ubicacion de destino distinta.']);
                }

                $this->ensureAvailability((int) $data['ubicacion_id'], $variante->id, $cantidad);
                $grupo = (string) Str::uuid();
                $base = $this->movementPayload($data, $variante, $cantidad);
                $base['grupo_traslado'] = $grupo;
                $base['tipo'] = 'TRASLADO_SALIDA';
                $base['cantidad'] = -$cantidad;
                $sourceMovement = $this->createMovement($base, $user);

                $base['tipo'] = 'TRASLADO_ENTRADA';
                $base['ubicacion_id'] = $destino;
                $base['cantidad'] = $cantidad;
                $this->createMovement($base, $user);

                return $sourceMovement;
            }

            $signed = in_array($tipo, ['AJUSTE_POSITIVO', 'STOCK_INICIAL'], true) ? $cantidad : -$cantidad;
            if ($signed < 0) {
                $this->ensureAvailability((int) $data['ubicacion_id'], $variante->id, abs($signed));
            }

            $payload = $this->movementPayload($data, $variante, $cantidad);
            $payload['tipo'] = $tipo;
            $payload['cantidad'] = $signed;

            return $this->createMovement($payload, $user);
        });
    }

    public function createStocktake(array $data, User $user): InventarioConteo
    {
        return DB::transaction(function () use ($data, $user) {
            $includeEmpty = (bool) ($data['incluir_sin_stock'] ?? false);
            $balances = $this->balances((int) $data['ubicacion_id']);
            if (! $includeEmpty) {
                $balances = $balances->filter(fn (InventarioVariante $variante) => abs((float) $variante->stock_actual) > 0.0001);
            }

            if ($balances->isEmpty()) {
                throw ValidationException::withMessages(['ubicacion_id' => 'No hay variantes para contar. Activa "Incluir articulos sin stock" para iniciar un conteo fisico desde cero.']);
            }

            $conteo = InventarioConteo::create([
                'codigo' => $this->code('CNT'),
                'ubicacion_id' => $data['ubicacion_id'],
                'fecha_corte' => $data['fecha_corte'],
                'observacion' => $data['observacion'] ?: null,
                'creado_por' => $user->id,
            ]);

            foreach ($balances as $variante) {
                InventarioConteoLinea::create([
                    'conteo_id' => $conteo->id,
                    'producto_id' => $variante->producto_id,
                    'variante_id' => $variante->id,
                    'cantidad_sistema' => $variante->stock_actual,
                ]);
            }

            return $conteo;
        });
    }

    public function saveStocktake(InventarioConteo $conteo, array $lineas): void
    {
        if ($conteo->estado === 'APROBADO') {
            throw ValidationException::withMessages(['conteo' => 'Este conteo ya fue aprobado y no se puede editar.']);
        }

        DB::transaction(function () use ($conteo, $lineas) {
            foreach ($lineas as $lineaId => $values) {
                $linea = $conteo->lineas()->whereKey($lineaId)->first();
                if (! $linea) {
                    continue;
                }

                $linea->update([
                    'cantidad_fisica' => $values['cantidad_fisica'] === '' ? null : $values['cantidad_fisica'],
                    'observacion' => $values['observacion'] ?: null,
                ]);
            }

            $pending = $conteo->lineas()->whereNull('cantidad_fisica')->exists();
            $conteo->update(['estado' => $pending ? 'BORRADOR' : 'EN_REVISION']);
        });
    }

    public function approveStocktake(InventarioConteo $conteo, User $user): void
    {
        if ($conteo->estado !== 'EN_REVISION') {
            throw ValidationException::withMessages(['conteo' => 'Completa todas las cantidades fisicas antes de aprobar el conteo.']);
        }

        DB::transaction(function () use ($conteo, $user) {
            foreach ($conteo->lineas as $linea) {
                $difference = (float) $linea->cantidad_fisica - (float) $linea->cantidad_sistema;
                if (abs($difference) < 0.0001) {
                    continue;
                }

                $this->createMovement([
                    'tipo' => $difference > 0 ? 'AJUSTE_POSITIVO' : 'AJUSTE_NEGATIVO',
                    'origen' => 'CONTEO_FISICO',
                    'ubicacion_id' => $conteo->ubicacion_id,
                    'producto_id' => $linea->producto_id,
                    'variante_id' => $linea->variante_id,
                    'cantidad' => $difference,
                    'referencia_tipo' => InventarioConteo::class,
                    'referencia_id' => $conteo->id,
                    'documento_tipo' => 'CONTEO',
                    'documento_numero' => $conteo->codigo,
                    'observacion' => $linea->observacion ?: 'Ajuste aprobado desde conteo fisico.',
                    'ocurrido_en' => Carbon::parse($conteo->fecha_corte)->endOfDay(),
                ], $user);
            }

            $conteo->update([
                'estado' => 'APROBADO',
                'aprobado_por' => $user->id,
                'aprobado_en' => now(),
            ]);
        });
    }

    public function deleteStocktake(InventarioConteo $conteo): void
    {
        if ($conteo->estado === 'APROBADO' || ! $conteo->puedeEliminarse()) {
            throw ValidationException::withMessages([
                'conteo' => 'No se puede eliminar un conteo aprobado: ya dejó ajustes en el kardex.',
            ]);
        }

        $hasAdjustments = InventarioMovimiento::query()
            ->where('referencia_tipo', InventarioConteo::class)
            ->where('referencia_id', $conteo->id)
            ->exists();

        if ($hasAdjustments) {
            throw ValidationException::withMessages([
                'conteo' => 'Este conteo ya tiene ajustes en el kardex y no se puede eliminar.',
            ]);
        }

        DB::transaction(function () use ($conteo) {
            $conteo->lineas()->delete();
            $conteo->delete();
        });
    }

    /** Suggests the inventory variants used by an individual Kizeo delivery. */
    public function suggestedKizeoVariants(EntregaBodega $delivery, Collection $variants): array
    {
        $byNameAndSize = [];
        $byName = [];

        foreach ($variants as $variant) {
            $name = $this->comparisonKey($variant->producto->nombre);
            $size = $this->comparisonKey($variant->talla);
            $byNameAndSize[$name.'|'.$size] = $variant->id;
            $byName[$name][] = $variant;
        }

        $suggestions = [];
        foreach ($delivery->items as $item) {
            $name = $this->comparisonKey($item->articulo);
            $size = $this->comparisonKey($item->talla ?: 'ESTANDAR');
            $suggestions[$item->id] = $byNameAndSize[$name.'|'.$size]
                ?? $byNameAndSize[$name.'|estandar']
                ?? ($byName[$name][0]->id ?? null);
        }

        return $suggestions;
    }

    /** La operación Kizeo siempre descuenta desde la bodega central SAEP. */
    public function kizeoOriginLocation(): InventarioUbicacion
    {
        $location = InventarioUbicacion::query()
            ->where('activo', true)
            ->where('codigo', self::KIZEO_ORIGIN_LOCATION_CODE)
            ->first();

        if (! $location) {
            throw ValidationException::withMessages([
                'entrega' => 'No existe una ubicación activa con código SAEP-CENTRAL para descontar las entregas Kizeo.',
            ]);
        }

        return $location;
    }

    /**
     * Resuelve las líneas que pueden aplicarse en lote. Una relación ambigua se
     * detiene antes de tocar stock y debe revisarse individualmente.
     *
     * @return array<int, array{variante_id: int}>
     */
    public function suggestedKizeoLineMappings(EntregaBodega $delivery, ?Collection $variants = null): array
    {
        $delivery->loadMissing('items');
        $items = $delivery->items->where('cantidad', '>', 0)->values();
        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['entrega' => 'La entrega de Kizeo no tiene ítems con cantidad para aplicar.']);
        }

        $variants ??= InventarioVariante::query()
            ->with('producto')
            ->where('activo', true)
            ->whereHas('producto', fn ($query) => $query->where('activo', true))
            ->orderBy('talla')
            ->get();
        $byNameAndSize = [];
        foreach ($variants as $variant) {
            $byNameAndSize[$this->comparisonKey($variant->producto->nombre).'|'.$this->comparisonKey($variant->talla)] = $variant->id;
        }
        $mappings = [];

        foreach ($items as $item) {
            $name = $this->comparisonKey($item->articulo);
            $size = $this->comparisonKey($item->talla ?: 'ESTANDAR');
            $variantId = $byNameAndSize[$name.'|'.$size] ?? null;
            if (! $variantId && $this->isGenericKizeoSize($item->talla)) {
                // Kizeo usa NA para prendas sin talla. Solo en ese caso se puede
                // asociar automáticamente a la variante ESTANDAR del mismo artículo.
                $variantId = $byNameAndSize[$name.'|estandar'] ?? null;
            }
            if (! $variantId) {
                throw ValidationException::withMessages([
                    'entrega' => "No se pudo relacionar automáticamente '{$item->articulo}' talla '{$item->talla}' con el catálogo. Revísala de forma individual.",
                ]);
            }

            $mappings[$item->id] = ['variante_id' => $variantId];
        }

        return $mappings;
    }

    /**
     * @return array{enabled: bool, since: ?Carbon, by: ?string}
     */
    public function kizeoAutoApplyState(): array
    {
        return [
            'enabled' => $this->kizeoAutoApplyEnabled(),
            'since' => $this->kizeoAutoApplySince(),
            'by' => $this->nullable($this->configValue(self::KIZEO_AUTO_APPLY_BY_KEY)),
        ];
    }

    public function kizeoAutoApplyEnabled(): bool
    {
        return $this->configValue(self::KIZEO_AUTO_APPLY_KEY) === '1';
    }

    public function setKizeoAutoApply(bool $enabled, User $user): void
    {
        if (! Schema::hasTable('configuraciones')) {
            throw ValidationException::withMessages([
                'activo' => 'Falta la configuración de inventario. Ejecuta las migraciones antes de activar el descuento automático.',
            ]);
        }

        $this->storeConfigValue(self::KIZEO_AUTO_APPLY_KEY, $enabled ? '1' : '0');
        $this->storeConfigValue(self::KIZEO_AUTO_APPLY_BY_KEY, (string) $user->id);
        if ($enabled) {
            $this->storeConfigValue(self::KIZEO_AUTO_APPLY_SINCE_KEY, now()->toIso8601String());
        }
    }

    /**
     * Descuenta una entrega recién sincronizada si el interruptor está activo
     * y la respuesta de Kizeo es posterior a la activación. Nunca recorre la cola histórica.
     */
    public function tryAutoApplyNewKizeoDelivery(EntregaBodega $delivery, bool $isNewRecord): ?InventarioEntregaKizeoAplicacion
    {
        if (! $this->shouldAutoApplyNewKizeoDelivery($delivery, $isNewRecord)) {
            return null;
        }

        try {
            $application = $this->applyKizeoDelivery(
                $delivery->loadMissing('items'),
                $this->kizeoOriginLocation()->id,
                $this->suggestedKizeoLineMappings($delivery),
                null,
            );
            Log::info('Entrega Kizeo descontada automáticamente del stock.', [
                'entrega_bodega_id' => $delivery->id,
                'kizeo_form_id' => $delivery->kizeo_form_id,
                'kizeo_data_id' => $delivery->kizeo_data_id,
                'aplicacion_id' => $application->id,
            ]);

            return $application;
        } catch (ValidationException $exception) {
            Log::warning('Entrega Kizeo nueva no se descontó automáticamente.', [
                'entrega_bodega_id' => $delivery->id,
                'kizeo_form_id' => $delivery->kizeo_form_id,
                'kizeo_data_id' => $delivery->kizeo_data_id,
                'reason' => collect($exception->errors())->flatten()->first(),
            ]);

            return null;
        } catch (\Throwable $exception) {
            Log::error('Error al descontar automáticamente una entrega Kizeo.', [
                'entrega_bodega_id' => $delivery->id,
                'kizeo_form_id' => $delivery->kizeo_form_id,
                'kizeo_data_id' => $delivery->kizeo_data_id,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    public function shouldAutoApplyNewKizeoDelivery(EntregaBodega $delivery, bool $isNewRecord): bool
    {
        if (! $isNewRecord || ! $this->kizeoAutoApplyEnabled()) {
            return false;
        }

        $since = $this->kizeoAutoApplySince();
        if (! $since) {
            return false;
        }

        if (! EntregaBodegaSyncService::isCurrentBodegaForm($delivery->kizeo_form_id)) {
            return false;
        }

        $delivery->loadMissing('inventarioAplicacion');
        if ($delivery->inventarioAplicacion
            || $delivery->flujo_inventario !== 'SALIDA'
            || ($delivery->estado_fuente ?: 'ACTIVA') !== 'ACTIVA') {
            return false;
        }

        $createdAt = $delivery->kizeo_created_at ?? $delivery->created_at;
        if (! $createdAt || $createdAt->lt($since)) {
            return false;
        }

        return true;
    }

    public function kizeoAutoApplySince(): ?Carbon
    {
        $value = trim((string) $this->configValue(self::KIZEO_AUTO_APPLY_SINCE_KEY));
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Aplica una entrega Kizeo con relaciones inequívocas desde Sede Central. */
    public function applyKizeoDeliveryFromCentral(EntregaBodega $delivery, ?User $user = null): InventarioEntregaKizeoAplicacion
    {
        return $this->applyKizeoDelivery(
            $delivery,
            $this->kizeoOriginLocation()->id,
            $this->suggestedKizeoLineMappings($delivery),
            $user,
        );
    }

    public function kizeoDeliveryNeedsReview(EntregaBodega $delivery): bool
    {
        $application = $delivery->inventarioAplicacion;
        if (! $application || $application->estado !== 'APLICADA') {
            return false;
        }

        return $delivery->kizeo_updated_at
            && (! $application->fuente_actualizada_en || $delivery->kizeo_updated_at->gt($application->fuente_actualizada_en));
    }

    public function applyKizeoDelivery(EntregaBodega $delivery, int $locationId, array $lineMappings, ?User $user = null): InventarioEntregaKizeoAplicacion
    {
        if ($delivery->flujo_inventario === 'ENTRADA') {
            throw ValidationException::withMessages([
                'entrega' => 'Esta respuesta de Kizeo corresponde a una devolución. No se puede descontar: debe registrarse como ingreso en la ubicación que reciba el material.',
            ]);
        }

        if (EntregaBodegaSyncService::isHistoricalStockForm($delivery->kizeo_form_id)) {
            throw ValidationException::withMessages([
                'entrega' => 'Este comprobante pertenece al formulario histórico de Kizeo (Control de Entrega Bodega). No se descuenta del inventario actual.',
            ]);
        }

        if (in_array($delivery->estado_fuente, ['ELIMINADA_EN_KIZEO', 'INCOMPLETA', 'REQUIERE_REVISION'], true)) {
            throw ValidationException::withMessages([
                'entrega' => $delivery->alerta_fuente
                    ?: 'La fuente Kizeo fue modificada o ya no está disponible. Revisa el comprobante antes de afectar inventario.',
            ]);
        }

        return DB::transaction(function () use ($delivery, $locationId, $lineMappings, $user) {
            $existing = InventarioEntregaKizeoAplicacion::query()
                ->where('entrega_bodega_id', $delivery->id)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                throw ValidationException::withMessages(['entrega' => 'Esta entrega de Kizeo ya fue aplicada al inventario. No se puede descontar dos veces.']);
            }

            $location = InventarioUbicacion::query()->where('activo', true)->find($locationId);
            if (! $location) {
                throw ValidationException::withMessages(['ubicacion_id' => 'Selecciona una ubicacion activa para descontar el stock.']);
            }

            $items = $delivery->items()->where('cantidad', '>', 0)->orderBy('linea')->get();
            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['entrega' => 'La entrega de Kizeo no tiene items con cantidad para aplicar.']);
            }

            $resolved = [];
            foreach ($items as $item) {
                $variantId = $lineMappings[$item->id]['variante_id'] ?? null;
                $variant = $variantId
                    ? InventarioVariante::query()->with('producto')->where('activo', true)->find($variantId)
                    : null;
                if (! $variant || ! $variant->producto->activo) {
                    throw ValidationException::withMessages(["lineas.{$item->id}.variante_id" => "Relaciona '{$item->articulo}' con un articulo activo del inventario."]);
                }

                $quantity = (float) $item->cantidad;
                $this->ensureAvailability($location->id, $variant->id, $quantity);
                $resolved[] = compact('item', 'variant', 'quantity');
            }

            $application = InventarioEntregaKizeoAplicacion::create([
                'entrega_bodega_id' => $delivery->id,
                'ubicacion_id' => $location->id,
                'estado' => 'APLICADA',
                'fuente_actualizada_en' => $delivery->kizeo_updated_at,
                'aplicada_por' => $user?->id,
                'aplicada_en' => now(),
                'observacion' => $user
                    ? 'Salida confirmada desde entrega Kizeo.'
                    : 'Salida automática desde Kizeo (interruptor activo).',
            ]);

            foreach ($resolved as ['item' => $item, 'variant' => $variant, 'quantity' => $quantity]) {
                $line = InventarioEntregaKizeoLinea::create([
                    'aplicacion_id' => $application->id,
                    'linea_fuente' => $item->linea,
                    'articulo_fuente' => $item->articulo ?: 'Sin articulo',
                    'talla_fuente' => $item->talla,
                    'cantidad_fuente' => $quantity,
                    'producto_id' => $variant->producto_id,
                    'variante_id' => $variant->id,
                ]);

                $movement = $this->createMovement([
                    'tipo' => 'ENTREGA_EPP',
                    'origen' => 'KIZEO_EPP',
                    'ubicacion_id' => $location->id,
                    'producto_id' => $variant->producto_id,
                    'variante_id' => $variant->id,
                    'cantidad' => -$quantity,
                    'referencia_tipo' => InventarioEntregaKizeoLinea::class,
                    'referencia_id' => $line->id,
                    'documento_tipo' => 'KIZEO_EPP',
                    'documento_numero' => $this->kizeoDocumentNumber($delivery),
                    'destinatario_nombre' => $delivery->nombre,
                    'destinatario_rut' => $delivery->rut,
                    'centro_costo' => $delivery->centro,
                    'observacion' => $user
                        ? 'Entrega Kizeo #'.($delivery->kizeo_record_number ?: $delivery->kizeo_data_id).' aplicada por Bodega.'
                        : 'Entrega Kizeo #'.($delivery->kizeo_record_number ?: $delivery->kizeo_data_id).' descontada automáticamente.',
                    'ocurrido_en' => $this->kizeoOccurredAt($delivery),
                ], $user);

                $line->update(['movimiento_id' => $movement->id]);
            }

            return $application->load(['ubicacion', 'lineas.variante.producto']);
        });
    }

    public function reverseKizeoDelivery(InventarioEntregaKizeoAplicacion $application, string $reason, User $user): void
    {
        if ($application->estado !== 'APLICADA') {
            throw ValidationException::withMessages(['aplicacion' => 'Esta entrega ya fue reversada o no puede modificarse.']);
        }

        DB::transaction(function () use ($application, $reason, $user) {
            $application = InventarioEntregaKizeoAplicacion::query()
                ->with(['entrega', 'lineas.movimiento'])
                ->lockForUpdate()
                ->findOrFail($application->id);

            foreach ($application->lineas as $line) {
                $original = $line->movimiento;
                if (! $original || $line->reverso_movimiento_id) {
                    continue;
                }

                $reverse = $this->createMovement([
                    'tipo' => 'REVERSO',
                    'origen' => 'REVERSO_KIZEO_EPP',
                    'ubicacion_id' => $original->ubicacion_id,
                    'producto_id' => $original->producto_id,
                    'variante_id' => $original->variante_id,
                    'cantidad' => abs((float) $original->cantidad),
                    'referencia_tipo' => InventarioEntregaKizeoAplicacion::class,
                    'referencia_id' => $application->id,
                    'documento_tipo' => 'KIZEO_EPP',
                    'documento_numero' => $original->documento_numero,
                    'destinatario_nombre' => $original->destinatario_nombre,
                    'destinatario_rut' => $original->destinatario_rut,
                    'centro_costo' => $original->centro_costo,
                    'observacion' => 'Reverso de entrega Kizeo: '.$reason,
                    'ocurrido_en' => now(),
                    'reverso_de_id' => $original->id,
                ], $user);

                $line->update(['reverso_movimiento_id' => $reverse->id]);
            }

            $application->update([
                'estado' => 'REVERSADA',
                'revertida_por' => $user->id,
                'revertida_en' => now(),
                'motivo_reversion' => $reason,
            ]);
        });
    }

    public function importProducts(UploadedFile $file, User $user): array
    {
        $spreadsheet = IOFactory::load($file->getPathname());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);
        $headers = array_map(fn ($value) => $this->normalizeHeader($value), array_shift($rows) ?? []);

        return DB::transaction(function () use ($rows, $headers, $user) {
            $created = 0;
            $updated = 0;
            $variantsCreated = 0;
            $stocksSet = 0;
            $costsUpdated = 0;
            $skipped = 0;
            $createdProducts = [];
            $updatedProducts = [];
            $touchedProductIds = [];
            $touchedVariantIds = [];
            $centralStockRows = 0;

            foreach ($rows as $row) {
                $values = array_combine($headers, array_pad($row, count($headers), null));
                $nombre = $this->importText($values['producto'] ?? $values['nombre'] ?? $values['item'] ?? null);
                if ($nombre === '') {
                    $skipped++;

                    continue;
                }

                // The Bodega EPP roster stores the size in its Item column (for example, "Botin T-39").
                // Keep one product per item and turn that suffix into a reusable stock variant.
                $talla = $this->importText($values['talla'] ?? $values['variante'] ?? null);
                if (isset($values['item']) && ! isset($values['producto']) && ! isset($values['nombre'])) {
                    [$nombre, $detectedSize] = $this->eppItemAndVariant($nombre);
                    $talla = $talla ?: $detectedSize;
                }
                $talla = Str::upper($talla ?: 'ESTANDAR');

                $sourceLocation = $this->importText($values['ubicacion_codigo'] ?? $values['ubicacion'] ?? null);
                $isAllLocationsLabel = $this->isAllLocationsLabel($sourceLocation);
                $stockRaw = $this->importText($values['stock_inicial'] ?? $values['stock_actual'] ?? $values['stock'] ?? null);
                $usesCentralDefault = $sourceLocation === '' && $stockRaw !== '';
                $locationCode = ($isAllLocationsLabel || $usesCentralDefault)
                    ? self::KIZEO_ORIGIN_LOCATION_CODE
                    : $sourceLocation;
                $costRaw = $this->importText(
                    $values['costo_referencia']
                        ?? $values['precio_referencia']
                        ?? $values['precio']
                        ?? $values['costo']
                        ?? $values['costo_unitario']
                        ?? null,
                );
                $referenceCost = $costRaw === '' ? null : $this->importDecimal($costRaw);
                if ($costRaw !== '' && ($referenceCost === null || $referenceCost < 0)) {
                    throw ValidationException::withMessages(['archivo' => "El Costo_Referencia de '{$nombre}' debe ser un número igual o mayor que cero."]);
                }
                $hasInitialStock = $stockRaw !== '';
                $location = null;
                $initialStock = null;
                if ($hasInitialStock) {
                    if ($locationCode === '') {
                        throw ValidationException::withMessages(['archivo' => "El producto '{$nombre}' requiere Ubicacion_Codigo para cargar Stock_Inicial."]);
                    }
                    if ($stockRaw === '') {
                        throw ValidationException::withMessages(['archivo' => "El producto '{$nombre}' requiere Stock_Inicial para la ubicacion '{$locationCode}'."]);
                    }

                    $initialStock = $this->importDecimal($stockRaw);
                    if ($initialStock === null || $initialStock < 0) {
                        throw ValidationException::withMessages(['archivo' => "El Stock_Inicial de '{$nombre}' debe ser un numero igual o mayor que cero."]);
                    }

                    $location = InventarioUbicacion::query()
                        ->where('codigo', $locationCode)
                        ->where('activo', true)
                        ->first();
                    if (! $location) {
                        throw ValidationException::withMessages(['archivo' => "No existe una ubicacion activa con codigo '{$locationCode}' para '{$nombre}'."]);
                    }
                    $centralStockRows += ($isAllLocationsLabel || $usesCentralDefault) ? 1 : 0;
                } elseif ($locationCode !== '' && ! $isAllLocationsLabel) {
                    throw ValidationException::withMessages(['archivo' => "El producto '{$nombre}' tiene Ubicacion_Codigo '{$locationCode}' pero no informa Stock_Inicial."]);
                }

                $codigo = $this->importText($values['codigo'] ?? null);
                $product = $codigo !== ''
                    ? InventarioProducto::query()->where('codigo', $codigo)->first()
                    : InventarioProducto::query()->whereRaw('LOWER(nombre) = ?', [Str::lower($nombre)])->first();
                $codigo = $codigo ?: ($product?->codigo ?: $this->availableProductCode(Str::upper(Str::slug($nombre, '-'))));
                $variantActive = $this->importVariantStatus($values['estado'] ?? $values['activo'] ?? null, $nombre, $talla);

                $attributes = [
                    'nombre' => $nombre,
                    'tipo' => $this->nullable($values['tipo'] ?? null),
                    'categoria' => $this->nullable($values['categoria'] ?? null),
                    'subcategoria' => $this->nullable($values['subcategoria'] ?? $values['sub_categoria'] ?? null),
                    'unidad_medida' => $this->nullable($values['formato'] ?? $values['unidad_medida'] ?? null) ?: 'Unidad',
                    'stock_minimo' => $this->decimal($values['stock_critico'] ?? $values['stock_minimo'] ?? 0),
                    // The source status belongs to the item/size. The product state is
                    // reconciled after every imported size has been saved.
                    'activo' => $product?->activo ?? $variantActive,
                    'creado_por' => $user->id,
                ];

                if ($product) {
                    $product->update($attributes);
                    if (! isset($createdProducts[$product->id]) && ! isset($updatedProducts[$product->id])) {
                        $updatedProducts[$product->id] = true;
                        $updated++;
                    }
                } else {
                    $product = InventarioProducto::create(['codigo' => $codigo] + $attributes);
                    $createdProducts[$product->id] = true;
                    $created++;
                }

                $variant = $product->variantes()->firstOrNew(['talla' => $talla]);
                $isNewVariant = ! $variant->exists;
                $variant->fill([
                    'codigo' => $variant->exists ? $variant->codigo : $this->availableVariantCode($product->codigo, $talla),
                    'descripcion' => $this->nullable($values['descripcion_variante'] ?? null),
                    'activo' => $variantActive,
                ]);
                $variant->save();
                $variantsCreated += $isNewVariant ? 1 : 0;
                $touchedProductIds[$product->id] = true;
                $touchedVariantIds[$variant->id] = true;
                $costsUpdated += $this->syncReferenceCost($variant, $referenceCost, $user, 'IMPORTACION_CATALOGO') ? 1 : 0;

                if ($location && $initialStock !== null) {
                    $currentStock = $this->stockActual($location->id, $variant->id);
                    $difference = $initialStock - $currentStock;
                    if (abs($difference) >= 0.0001) {
                        $hasMovementHistory = InventarioMovimiento::query()
                            ->where('ubicacion_id', $location->id)
                            ->where('variante_id', $variant->id)
                            ->exists();
                        $this->createMovement([
                            'tipo' => $hasMovementHistory
                                ? ($difference > 0 ? 'AJUSTE_POSITIVO' : 'AJUSTE_NEGATIVO')
                                : 'STOCK_INICIAL',
                            'origen' => 'IMPORTACION_CATALOGO',
                            'ubicacion_id' => $location->id,
                            'producto_id' => $product->id,
                            'variante_id' => $variant->id,
                            'cantidad' => $difference,
                            'costo_unitario' => $referenceCost && $referenceCost > 0 ? $referenceCost : null,
                            'documento_tipo' => 'AJUSTE',
                            'documento_numero' => 'IMPORTACION-STOCK-INICIAL',
                            'observacion' => $hasMovementHistory
                                ? 'Saldo fijado desde importacion de catalogo: '.$this->number($initialStock).'.'
                                : 'Carga de stock inicial desde importacion de catalogo: '.$this->number($initialStock).'.',
                            'ocurrido_en' => now(),
                        ], $user);
                        $stocksSet++;
                    }
                }
            }

            foreach (array_keys($touchedProductIds) as $productId) {
                $product = InventarioProducto::query()->find($productId);
                if (! $product) {
                    continue;
                }

                $hasActiveVariant = $product->variantes()->where('activo', true)->exists();
                if ((bool) $product->activo !== $hasActiveVariant) {
                    $product->update(['activo' => $hasActiveVariant]);
                }
            }

            $variantsInactive = $touchedVariantIds === []
                ? 0
                : InventarioVariante::query()->whereKey(array_keys($touchedVariantIds))->where('activo', false)->count();

            return compact('created', 'updated', 'variantsCreated', 'stocksSet', 'costsUpdated', 'variantsInactive', 'centralStockRows', 'skipped');
        });
    }

    public function importReceipts(UploadedFile $file, User $user): array
    {
        $spreadsheet = IOFactory::load($file->getPathname());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);
        $headers = array_map(fn ($value) => $this->normalizeHeader($value), array_shift($rows) ?? []);
        $requiredHeaders = ['referencia_ingreso', 'ubicacion_codigo', 'codigo_producto', 'cantidad'];
        $missingHeaders = array_diff($requiredHeaders, $headers);
        if ($missingHeaders) {
            throw ValidationException::withMessages([
                'archivo' => 'La plantilla debe incluir las columnas: Referencia_Ingreso, Ubicacion_Codigo, Codigo_Producto y Cantidad.',
            ]);
        }

        $groups = [];
        $errors = [];
        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $values = array_combine($headers, array_pad($row, count($headers), null));
            if (collect($values)->filter(fn ($value) => $this->importText($value) !== '')->isEmpty()) {
                continue;
            }

            $reference = $this->importText($values['referencia_ingreso'] ?? null);
            $locationCode = $this->importText($values['ubicacion_codigo'] ?? null);
            $productCode = $this->importText($values['codigo_producto'] ?? $values['codigo'] ?? null);
            $size = Str::upper($this->importText($values['talla'] ?? $values['variante'] ?? 'ESTANDAR') ?: 'ESTANDAR');
            $quantity = $this->importDecimal($values['cantidad'] ?? null);
            $cost = $this->importText($values['costo_unitario'] ?? null) === '' ? null : $this->importDecimal($values['costo_unitario']);
            $documentType = Str::upper($this->importText($values['tipo_documento'] ?? null) ?: 'FACTURA');
            $documentNumber = $this->importText($values['numero_documento'] ?? null);
            $receiptDate = $this->importReceiptDate($values['fecha_recepcion'] ?? null);
            $documentDate = $this->importText($values['fecha_documento'] ?? null) === '' ? null : $this->importReceiptDate($values['fecha_documento'] ?? null);
            $providerName = $this->importText($values['proveedor'] ?? null);
            $providerRut = $this->importText($values['proveedor_rut'] ?? null);

            if ($reference === '') {
                $errors[] = "Fila {$line}: Referencia_Ingreso es obligatoria para agrupar las lineas del comprobante.";
            }
            $location = $locationCode === '' ? null : InventarioUbicacion::query()->where('codigo', $locationCode)->where('activo', true)->first();
            if (! $location) {
                $errors[] = "Fila {$line}: no existe una ubicacion activa con codigo '{$locationCode}'.";
            }
            $product = $productCode === '' ? null : InventarioProducto::query()->where('codigo', $productCode)->where('activo', true)->first();
            $variant = $product?->variantes()->where('talla', $size)->where('activo', true)->first();
            if (! $variant) {
                $errors[] = "Fila {$line}: no existe el articulo activo '{$productCode}' con talla '{$size}'.";
            }
            if (! in_array($documentType, array_keys(InventarioIngreso::TIPOS_DOCUMENTO), true)) {
                $errors[] = "Fila {$line}: Tipo_Documento debe ser FACTURA, GUIA_DESPACHO u OTRO.";
            }
            if (! $receiptDate) {
                $errors[] = "Fila {$line}: Fecha_Recepcion debe usar formato AAAA-MM-DD o DD/MM/AAAA.";
            }
            if ($this->importText($values['fecha_documento'] ?? null) !== '' && ! $documentDate) {
                $errors[] = "Fila {$line}: Fecha_Documento debe usar formato AAAA-MM-DD o DD/MM/AAAA.";
            }
            if ($quantity === null || $quantity <= 0) {
                $errors[] = "Fila {$line}: Cantidad debe ser mayor que cero.";
            }
            if ($cost !== null && $cost < 0) {
                $errors[] = "Fila {$line}: Costo_Unitario no puede ser negativo.";
            }
            if ($providerRut !== '' && $providerName === '') {
                $errors[] = "Fila {$line}: informa Proveedor junto con Proveedor_Rut, o deja ambos vacios.";
            }
            if (count($errors) > 15) {
                break;
            }

            if (! $reference || ! $location || ! $variant || ! $receiptDate || ($quantity === null || $quantity <= 0)) {
                continue;
            }

            $header = [
                'ubicacion_id' => $location->id,
                'proveedor' => $providerName,
                'proveedor_rut' => $providerRut,
                'tipo_documento' => $documentType,
                'numero_documento' => $documentNumber,
                'fecha_documento' => $documentDate,
                'fecha_recepcion' => $receiptDate,
                'observacion' => $this->nullable($values['observacion'] ?? null),
            ];
            if (isset($groups[$reference]) && $groups[$reference]['header'] !== $header) {
                $errors[] = "Fila {$line}: las lineas con Referencia_Ingreso '{$reference}' deben tener los mismos datos de cabecera.";

                continue;
            }
            $groups[$reference] ??= ['header' => $header, 'items' => []];
            $groups[$reference]['items'][] = [
                'variante_id' => $variant->id,
                'cantidad' => $quantity,
                'costo_unitario' => $cost,
            ];
        }

        if ($errors) {
            throw ValidationException::withMessages(['archivo' => implode(' ', array_slice($errors, 0, 15))]);
        }
        if (! $groups) {
            throw ValidationException::withMessages(['archivo' => 'El archivo no contiene lineas de ingresos validas.']);
        }

        $importedDocumentKeys = [];
        foreach ($groups as $reference => $group) {
            $header = $group['header'];
            if ($header['numero_documento'] === '') {
                continue;
            }
            $documentKey = implode('|', [$header['ubicacion_id'], $header['tipo_documento'], Str::lower($header['numero_documento'])]);
            if (isset($importedDocumentKeys[$documentKey])) {
                throw ValidationException::withMessages([
                    'archivo' => "Las referencias '{$importedDocumentKeys[$documentKey]}' y '{$reference}' repiten el mismo documento dentro de la planilla.",
                ]);
            }
            $importedDocumentKeys[$documentKey] = $reference;
            $existing = InventarioIngreso::query()
                ->where('ubicacion_id', $header['ubicacion_id'])
                ->where('tipo_documento', $header['tipo_documento'])
                ->where('numero_documento', $header['numero_documento'])
                ->whereNull('reversado_en')
                ->first();
            if ($existing) {
                throw ValidationException::withMessages([
                    'archivo' => "La referencia '{$reference}' repite el documento ya vigente {$existing->codigo}. Anulalo primero si fue registrado por error.",
                ]);
            }
        }

        return DB::transaction(function () use ($groups, $user) {
            $receipts = 0;
            $lines = 0;
            foreach ($groups as $group) {
                $header = $group['header'];
                $provider = $this->resolveImportedProvider($header);
                $this->registerReceipt([
                    'ubicacion_id' => $header['ubicacion_id'],
                    'proveedor_id' => $provider?->id,
                    'tipo_documento' => $header['tipo_documento'],
                    'numero_documento' => $header['numero_documento'],
                    'fecha_documento' => $header['fecha_documento'],
                    'fecha_recepcion' => $header['fecha_recepcion'],
                    'observacion' => $header['observacion'],
                ], $group['items'], $user);
                $receipts++;
                $lines += count($group['items']);
            }

            return compact('receipts', 'lines');
        });
    }

    /**
     * Imports manual operational movements. Kizeo exits are intentionally not
     * accepted here: they are applied from their source voucher in the Kizeo tab.
     */
    public function importManualMovements(UploadedFile $file, User $user): array
    {
        $spreadsheet = IOFactory::load($file->getPathname());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);
        $headers = array_map(fn ($value) => $this->normalizeHeader($value), array_shift($rows) ?? []);
        $requiredHeaders = ['referencia_movimiento', 'tipo', 'ubicacion_origen_codigo', 'codigo_producto', 'cantidad', 'fecha_hora'];
        if (array_diff($requiredHeaders, $headers)) {
            throw ValidationException::withMessages([
                'archivo' => 'La plantilla debe incluir Referencia_Movimiento, Tipo, Ubicacion_Origen_Codigo, Codigo_Producto, Cantidad y Fecha_Hora.',
            ]);
        }

        $allowedTypes = ['ENTREGA_EPP', 'DESPACHO_CENTRO', 'TRASLADO', 'AJUSTE_POSITIVO', 'AJUSTE_NEGATIVO', 'STOCK_INICIAL'];
        $prepared = [];
        $seenReferences = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $values = array_combine($headers, array_pad($row, count($headers), null));
            if (collect($values)->filter(fn ($value) => $this->importText($value) !== '')->isEmpty()) {
                continue;
            }

            $reference = $this->importText($values['referencia_movimiento'] ?? null);
            $type = Str::upper($this->importText($values['tipo'] ?? null));
            $originCode = $this->importText($values['ubicacion_origen_codigo'] ?? null);
            $destinationCode = $this->importText($values['ubicacion_destino_codigo'] ?? null);
            $productCode = $this->importText($values['codigo_producto'] ?? $values['codigo'] ?? null);
            $size = Str::upper($this->importText($values['talla'] ?? $values['variante'] ?? 'ESTANDAR') ?: 'ESTANDAR');
            $quantity = $this->importDecimal($values['cantidad'] ?? null);
            $occurredAt = $this->importMovementDate($values['fecha_hora'] ?? null);
            $cost = $this->importText($values['costo_unitario'] ?? null) === '' ? null : $this->importDecimal($values['costo_unitario']);
            $documentType = Str::upper($this->importText($values['tipo_documento'] ?? null));
            $documentNumber = $this->importText($values['numero_documento'] ?? null);
            $costCenterText = $this->importText($values['centro_costo'] ?? null);
            $coordinatorText = $this->importText($values['coordinador'] ?? null);

            if ($reference === '') {
                $errors[] = "Fila {$line}: Referencia_Movimiento es obligatoria.";
            } elseif (isset($seenReferences[$reference])) {
                $errors[] = "Fila {$line}: la referencia '{$reference}' ya fue usada en la fila {$seenReferences[$reference]}. Cada fila debe tener una referencia única.";
            } else {
                $seenReferences[$reference] = $line;
            }
            if (! in_array($type, $allowedTypes, true)) {
                $errors[] = "Fila {$line}: Tipo debe ser ENTREGA_EPP, DESPACHO_CENTRO, TRASLADO, AJUSTE_POSITIVO, AJUSTE_NEGATIVO o STOCK_INICIAL. Las salidas Kizeo se aplican desde la pestaña Entregas Kizeo.";
            }
            $origin = $originCode === '' ? null : InventarioUbicacion::query()->where('codigo', $originCode)->where('activo', true)->first();
            if (! $origin) {
                $errors[] = "Fila {$line}: no existe una ubicación de origen activa con código '{$originCode}'.";
            }
            $destination = null;
            if ($type === 'TRASLADO') {
                $destination = $destinationCode === '' ? null : InventarioUbicacion::query()->where('codigo', $destinationCode)->where('activo', true)->first();
                if (! $destination) {
                    $errors[] = "Fila {$line}: Ubicacion_Destino_Codigo es obligatoria y debe existir para un TRASLADO.";
                } elseif ($origin && $destination->id === $origin->id) {
                    $errors[] = "Fila {$line}: el origen y destino del traslado deben ser distintos.";
                }
            }
            $product = $productCode === '' ? null : InventarioProducto::query()->where('codigo', $productCode)->where('activo', true)->first();
            $variant = $product?->variantes()->where('talla', $size)->where('activo', true)->first();
            if (! $variant) {
                $errors[] = "Fila {$line}: no existe el artículo activo '{$productCode}' con talla '{$size}'.";
            }
            if ($quantity === null || $quantity <= 0) {
                $errors[] = "Fila {$line}: Cantidad debe ser mayor que cero.";
            }
            if (! $occurredAt) {
                $errors[] = "Fila {$line}: Fecha_Hora debe usar AAAA-MM-DD HH:MM, DD/MM/AAAA HH:MM o una fecha de Excel válida.";
            }
            if ($cost !== null && $cost < 0) {
                $errors[] = "Fila {$line}: Costo_Unitario no puede ser negativo.";
            }
            if ($documentType !== '' && ! array_key_exists($documentType, InventarioMovimiento::TIPOS_DOCUMENTO)) {
                $errors[] = "Fila {$line}: Tipo_Documento debe ser ACTA, FACTURA, GUIA_DESPACHO, AJUSTE u OTRO.";
            }

            $costCenter = $costCenterText === '' ? null : $this->findImportedCostCenter($costCenterText);
            if ($costCenterText !== '' && ! $costCenter) {
                $errors[] = "Fila {$line}: no se encontró el Centro_Costo '{$costCenterText}' en los maestros activos.";
            }
            $coordinator = $coordinatorText === '' ? null : $this->findImportedCoordinator($coordinatorText);
            if ($coordinatorText !== '' && ! $coordinator) {
                $errors[] = "Fila {$line}: no se encontró el Coordinador '{$coordinatorText}' en los maestros activos.";
            }
            if (count($errors) > 15) {
                break;
            }

            if (! $reference || ! $origin || ! $variant || ! $occurredAt || $quantity === null || $quantity <= 0 || ! in_array($type, $allowedTypes, true) || ($type === 'TRASLADO' && ! $destination) || ($costCenterText !== '' && ! $costCenter) || ($coordinatorText !== '' && ! $coordinator)) {
                continue;
            }

            $coordinator ??= $costCenter?->coordinador;
            $prepared[] = [
                'referencia' => $reference,
                'data' => [
                    'tipo' => $type,
                    'ubicacion_id' => $origin->id,
                    'ubicacion_destino_id' => $destination?->id,
                    'variante_id' => $variant->id,
                    'cantidad' => $quantity,
                    'ocurrido_en' => $occurredAt,
                    'destinatario_nombre' => $this->nullable($values['destinatario'] ?? $values['destinatario_nombre'] ?? null) ?: $coordinator?->nombre,
                    'destinatario_rut' => $this->nullable($values['rut_destinatario'] ?? $values['destinatario_rut'] ?? null) ?: $coordinator?->rut,
                    'centro_costo' => $costCenter?->nombre,
                    'centro_costo_id' => $costCenter?->id,
                    'coordinador_id' => $coordinator?->id,
                    'documento_tipo' => $documentType ?: null,
                    'documento_numero' => $documentNumber ?: null,
                    'costo_unitario' => $cost,
                    'observacion' => $this->importMovementObservation($reference, $this->importText($values['observacion'] ?? null)),
                ],
            ];
        }

        if ($errors) {
            throw ValidationException::withMessages(['archivo' => implode(' ', array_slice($errors, 0, 15))]);
        }
        if ($prepared === []) {
            throw ValidationException::withMessages(['archivo' => 'El archivo no contiene filas de movimientos válidas.']);
        }

        $existingReferences = InventarioImportacionMovimiento::query()
            ->whereIn('referencia', collect($prepared)->pluck('referencia'))
            ->pluck('referencia')
            ->flip();

        return DB::transaction(function () use ($prepared, $existingReferences, $user) {
            $movements = 0;
            $skipped = 0;

            foreach ($prepared as $row) {
                if ($existingReferences->has($row['referencia'])) {
                    $skipped++;

                    continue;
                }

                $movement = $this->registerManualMovement($row['data'], $user);
                InventarioImportacionMovimiento::create([
                    'referencia' => $row['referencia'],
                    'movimiento_id' => $movement->id,
                    'registrado_por' => $user->id,
                ]);
                $movements++;
            }

            return compact('movements', 'skipped');
        });
    }

    private function movementPayload(array $data, InventarioVariante $variante, float $cantidad): array
    {
        return [
            'origen' => 'MANUAL',
            'ubicacion_id' => $data['ubicacion_id'],
            'producto_id' => $variante->producto_id,
            'variante_id' => $variante->id,
            'cantidad' => $cantidad,
            'costo_unitario' => $data['costo_unitario'] ?? null,
            'documento_tipo' => $data['documento_tipo'] ?? null,
            'documento_numero' => $data['documento_numero'] ?? null,
            'destinatario_nombre' => $data['destinatario_nombre'] ?? null,
            'destinatario_rut' => $data['destinatario_rut'] ?? null,
            'centro_costo' => $data['centro_costo'] ?? null,
            'centro_costo_id' => $data['centro_costo_id'] ?? null,
            'coordinador_id' => $data['coordinador_id'] ?? null,
            'observacion' => $data['observacion'] ?? null,
            'ocurrido_en' => Carbon::parse($data['ocurrido_en']),
        ];
    }

    private function createMovement(array $attributes, ?User $user = null): InventarioMovimiento
    {
        return InventarioMovimiento::create($attributes + [
            'codigo' => $this->code('MOV'),
            'registrado_por' => $user?->id,
            'registrado_por_nombre' => $user
                ? trim($user->name.' '.($user->apellido_paterno ?? ''))
                : 'Kizeo automático',
        ]);
    }

    private function configValue(string $key): string
    {
        if (! Schema::hasTable('configuraciones')) {
            return '';
        }

        return (string) (Configuracion::get($key, '') ?? '');
    }

    private function storeConfigValue(string $key, string $value): void
    {
        $existing = Configuracion::query()->where('clave', $key)->first();
        if ($existing) {
            $existing->update(['valor' => $value]);

            return;
        }

        Configuracion::query()->create([
            'clave' => $key,
            'valor' => $value,
            'tipo' => $key === self::KIZEO_AUTO_APPLY_KEY ? 'BOOLEAN' : 'TEXT',
            'categoria' => 'inventario',
            'descripcion' => null,
            'editable' => false,
        ]);
    }

    private function ensureAvailability(int $ubicacionId, int $varianteId, float $quantity): void
    {
        $available = $this->stockActual($ubicacionId, $varianteId);
        if ($available + 0.0001 < $quantity) {
            throw ValidationException::withMessages([
                'cantidad' => 'Stock insuficiente en la ubicacion seleccionada. Disponible: '.$this->number($available).'.',
            ]);
        }
    }

    private function code(string $prefix): string
    {
        return $prefix.'-'.now()->format('Ymd').'-'.Str::upper(Str::random(5));
    }

    private function availableProductCode(string $base): string
    {
        $base = Str::limit($base ?: 'PRODUCTO', 64, '');
        $candidate = $base;
        $index = 2;
        while (InventarioProducto::query()->where('codigo', $candidate)->exists()) {
            $candidate = Str::limit($base, 58, '').'-'.$index++;
        }

        return $candidate;
    }

    private function availableVariantCode(string $productCode, string $size): string
    {
        $base = Str::limit($productCode.'-'.Str::upper(Str::slug($size ?: 'ESTANDAR')), 94, '');
        $candidate = $base;
        $index = 2;
        while (InventarioVariante::query()->where('codigo', $candidate)->exists()) {
            $candidate = Str::limit($base, 94, '').'-'.$index++;
        }

        return $candidate;
    }

    private function syncProductVariants(InventarioProducto $product, mixed $sizes): void
    {
        $values = collect(explode(',', (string) $sizes))
            ->map(fn ($size) => Str::upper(trim($size)))
            ->filter()
            ->unique()
            ->values();

        if ($values->isEmpty()) {
            $values->push('ESTANDAR');
        }

        foreach ($values as $size) {
            $variant = $product->variantes()->firstOrCreate(
                ['talla' => $size],
                ['codigo' => $this->availableVariantCode($product->codigo, $size), 'activo' => true],
            );

            if (! $variant->activo) {
                $variant->update(['activo' => true]);
            }
        }
    }

    /**
     * Persists only meaningful changes. Zero represents an unknown value and must never
     * overwrite an already-known cost or create a fictitious financial record.
     */
    private function syncReferenceCost(
        InventarioVariante $variant,
        mixed $cost,
        User $user,
        string $source,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?Carbon $effectiveAt = null,
    ): bool {
        $cost = $cost === null ? null : (float) $cost;
        if ($cost === null || $cost <= 0) {
            return false;
        }

        $cost = round($cost, 2);
        $effectiveAt ??= now();
        $latest = $variant->historialCostos()->first();

        if ($latest && abs((float) $latest->costo_unitario - $cost) < 0.005) {
            if ($variant->costo_referencia === null) {
                $variant->update(['costo_referencia' => $cost]);
            }

            return false;
        }

        InventarioHistorialCosto::create([
            'variante_id' => $variant->id,
            'costo_unitario' => $cost,
            'origen' => $source,
            'referencia_tipo' => $referenceType,
            'referencia_id' => $referenceId,
            'vigente_desde' => $effectiveAt,
            'registrado_por' => $user->id,
        ]);

        if (! $latest || $effectiveAt->gte($latest->vigente_desde)) {
            $variant->update(['costo_referencia' => $cost]);
        }

        return true;
    }

    private function normalizeHeader(mixed $header): string
    {
        return Str::of((string) $header)->ascii()->lower()->replace([' ', '-', '.'], '_')->replaceMatches('/_+/', '_')->trim('_')->toString();
    }

    private function isAllLocationsLabel(string $location): bool
    {
        $normalized = Str::of($location)
            ->ascii()
            ->lower()
            ->replace([' ', '-', '_'], '')
            ->toString();

        return in_array($normalized, ['todaslasubicaciones', 'todaubicacion', 'todas'], true);
    }

    private function importVariantStatus(mixed $value, string $productName, string $size): bool
    {
        $status = Str::of($this->importText($value))
            ->ascii()
            ->lower()
            ->replace([' ', '-', '_'], '')
            ->toString();

        if ($status === '' || in_array($status, ['activo', 'active', 'habilitado', 'habilitada', 'si', 'yes', 'true', '1'], true)) {
            return true;
        }

        if (in_array($status, ['inactivo', 'inactiva', 'inactive', 'inhabilitado', 'inhabilitada', 'deshabilitado', 'deshabilitada', 'no', 'false', '0'], true)) {
            return false;
        }

        throw ValidationException::withMessages([
            'archivo' => "El Estado de '{$productName}' talla '{$size}' debe ser Activo/Habilitado o Inactivo/Inhabilitado.",
        ]);
    }

    private function importText(mixed $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $value));
    }

    private function importDecimal(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $value = $this->importText($value);
        if ($value === '') {
            return null;
        }

        // PhpSpreadsheet returns formatted cells during import. A Chilean price such as
        // 41.590 can therefore arrive as "41,590" depending on the workbook locale.
        // Treat a separator followed by three-digit groups as a thousands separator;
        // only the remaining comma/dot form is considered a decimal separator.
        if (str_contains($value, ',') && str_contains($value, '.')) {
            $lastComma = strrpos($value, ',');
            $lastDot = strrpos($value, '.');
            $decimalSeparator = $lastComma > $lastDot ? ',' : '.';
            $thousandsSeparator = $decimalSeparator === ',' ? '.' : ',';
            $value = str_replace($thousandsSeparator, '', $value);
            $value = $decimalSeparator === ',' ? str_replace(',', '.', $value) : $value;
        } elseif (str_contains($value, ',')) {
            $value = preg_match('/^[+-]?\d{1,3}(?:,\d{3})+$/', $value)
                ? str_replace(',', '', $value)
                : str_replace(',', '.', $value);
        } elseif (str_contains($value, '.') && preg_match('/^[+-]?\d{1,3}(?:\.\d{3})+$/', $value)) {
            $value = str_replace('.', '', $value);
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function importReceiptDate(mixed $value): ?string
    {
        $value = $this->importText($value);
        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
            } catch (\Throwable) {
                continue;
            }
            if ($date && $date->format($format) === $value) {
                return $date->toDateString();
            }
        }

        return null;
    }

    private function importMovementDate(mixed $value): ?string
    {
        if (is_int($value) || is_float($value)) {
            if ($value > 1 && $value < 100000) {
                try {
                    return Date::excelToDateTimeObject((float) $value)->format('Y-m-d H:i:s');
                } catch (\Throwable) {
                    return null;
                }
            }
        }

        $value = $this->importText($value);
        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d\\TH:i:s', 'Y-m-d\\TH:i', 'd/m/Y H:i:s', 'd/m/Y H:i', 'd-m-Y H:i', 'Y/m/d H:i', 'Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
            } catch (\Throwable) {
                continue;
            }
            if ($date && $date->format($format) === $value) {
                return $date->format('Y-m-d H:i:s');
            }
        }

        return null;
    }

    private function findImportedCostCenter(string $value): ?InventarioCentroCosto
    {
        $normalized = $this->comparisonKey($value);

        return InventarioCentroCosto::query()
            ->with('coordinador')
            ->where('activo', true)
            ->where(function ($query) use ($value, $normalized) {
                $query->where('numero_maestro', $value)
                    ->orWhere('nombre_normalizado', $normalized);
            })
            ->first();
    }

    private function findImportedCoordinator(string $value): ?InventarioCoordinador
    {
        return InventarioCoordinador::query()
            ->where('activo', true)
            ->where(function ($query) use ($value) {
                $query->where('rut', $value)
                    ->orWhere('nombre_normalizado', $this->comparisonKey($value));
            })
            ->first();
    }

    private function importMovementObservation(string $reference, string $observation): string
    {
        return Str::limit(
            'Importación masiva · Ref. '.$reference.($observation !== '' ? ' · '.$observation : ''),
            500,
            '',
        );
    }

    private function resolveImportedProvider(array $header): ?InventarioProveedor
    {
        if ($header['proveedor'] === '' && $header['proveedor_rut'] === '') {
            return null;
        }

        $provider = $header['proveedor_rut'] !== ''
            ? InventarioProveedor::query()->where('rut', $header['proveedor_rut'])->first()
            : InventarioProveedor::query()->whereRaw('LOWER(nombre) = ?', [Str::lower($header['proveedor'])])->first();

        return $provider ?: InventarioProveedor::create([
            'nombre' => $header['proveedor'],
            'rut' => $header['proveedor_rut'] ?: null,
            'activo' => true,
        ]);
    }

    private function eppItemAndVariant(string $item): array
    {
        if (! preg_match('/\s+T[-\s]*(NA|XXXL|XXL|XL|XS|S|M|L|\d{1,3})\s*$/iu', $item, $matches, PREG_OFFSET_CAPTURE)) {
            return [$item, 'ESTANDAR'];
        }

        $size = Str::upper($matches[1][0]);
        $name = trim(substr($item, 0, $matches[0][1]));

        return [$name ?: $item, $size === 'NA' ? 'ESTANDAR' : $size];
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function decimal(mixed $value): float
    {
        return (float) str_replace(',', '.', (string) $value);
    }

    private function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, ',', '.'), '0'), ',');
    }

    private function comparisonKey(?string $value): string
    {
        return Str::of((string) $value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->toString();
    }

    /** Identifica tallas genéricas de Kizeo que equivalen a la variante ESTANDAR. */
    private function isGenericKizeoSize(?string $size): bool
    {
        return in_array($this->comparisonKey($size), [
            '', 'na', 'n a', 'estandar', 'sin talla', 'unica', 'unitalla',
        ], true);
    }

    private function kizeoDocumentNumber(EntregaBodega $delivery): string
    {
        return 'KZ-'.($delivery->kizeo_record_number ?: $delivery->kizeo_data_id);
    }

    private function kizeoOccurredAt(EntregaBodega $delivery): Carbon
    {
        if ($delivery->kizeo_created_at) {
            return $delivery->kizeo_created_at->copy();
        }

        if ($delivery->fecha_pedido) {
            return Carbon::parse($delivery->fecha_pedido)->setTime(12, 0);
        }

        return now();
    }
}
