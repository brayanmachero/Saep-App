<?php

namespace App\Services;

use App\Models\EntregaBodega;
use App\Models\InventarioConteo;
use App\Models\InventarioConteoLinea;
use App\Models\InventarioEntregaKizeoAplicacion;
use App\Models\InventarioEntregaKizeoLinea;
use App\Models\InventarioIngreso;
use App\Models\InventarioIngresoItem;
use App\Models\InventarioMovimiento;
use App\Models\InventarioProducto;
use App\Models\InventarioUbicacion;
use App\Models\InventarioVariante;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class InventarioStockService
{
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
            }

            return $ingreso;
        });
    }

    public function registerManualMovement(array $data, User $user): void
    {
        DB::transaction(function () use ($data, $user) {
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
                $this->createMovement($base, $user);

                $base['tipo'] = 'TRASLADO_ENTRADA';
                $base['ubicacion_id'] = $destino;
                $base['cantidad'] = $cantidad;
                $this->createMovement($base, $user);

                return;
            }

            $signed = in_array($tipo, ['AJUSTE_POSITIVO', 'STOCK_INICIAL'], true) ? $cantidad : -$cantidad;
            if ($signed < 0) {
                $this->ensureAvailability((int) $data['ubicacion_id'], $variante->id, abs($signed));
            }

            $payload = $this->movementPayload($data, $variante, $cantidad);
            $payload['tipo'] = $tipo;
            $payload['cantidad'] = $signed;
            $this->createMovement($payload, $user);
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

    /**
     * Suggests inventory variants without applying a Kizeo delivery automatically.
     * The warehouse must always confirm the source location before stock is affected.
     */
    public function suggestedKizeoVariants(EntregaBodega $delivery, Collection $variants): array
    {
        $byNameAndSize = [];
        $byName = [];

        foreach ($variants as $variant) {
            $name = $this->comparisonKey($variant->producto->nombre);
            $size = $this->comparisonKey($variant->talla);
            $byNameAndSize[$name . '|' . $size] = $variant->id;
            $byName[$name][] = $variant;
        }

        $suggestions = [];
        foreach ($delivery->items as $item) {
            $name = $this->comparisonKey($item->articulo);
            $size = $this->comparisonKey($item->talla ?: 'ESTANDAR');
            $suggestions[$item->id] = $byNameAndSize[$name . '|' . $size]
                ?? $byNameAndSize[$name . '|estandar']
                ?? ($byName[$name][0]->id ?? null);
        }

        return $suggestions;
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

    public function applyKizeoDelivery(EntregaBodega $delivery, int $locationId, array $lineMappings, User $user): InventarioEntregaKizeoAplicacion
    {
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
                'aplicada_por' => $user->id,
                'aplicada_en' => now(),
                'observacion' => 'Salida confirmada desde entrega Kizeo.',
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
                    'observacion' => 'Entrega Kizeo #' . ($delivery->kizeo_record_number ?: $delivery->kizeo_data_id) . ' aplicada por Bodega.',
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
                    'observacion' => 'Reverso de entrega Kizeo: ' . $reason,
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
        $created = 0;
        $updated = 0;
        $variantsCreated = 0;
        $skipped = 0;
        $createdProducts = [];
        $updatedProducts = [];

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

            $codigo = $this->importText($values['codigo'] ?? null);
            $product = $codigo !== ''
                ? InventarioProducto::query()->where('codigo', $codigo)->first()
                : InventarioProducto::query()->whereRaw('LOWER(nombre) = ?', [Str::lower($nombre)])->first();
            $codigo = $codigo ?: ($product?->codigo ?: $this->availableProductCode(Str::upper(Str::slug($nombre, '-'))));

            $attributes = [
                'nombre' => $nombre,
                'tipo' => $this->nullable($values['tipo'] ?? null),
                'categoria' => $this->nullable($values['categoria'] ?? null),
                'subcategoria' => $this->nullable($values['subcategoria'] ?? $values['sub_categoria'] ?? null),
                'unidad_medida' => $this->nullable($values['formato'] ?? $values['unidad_medida'] ?? null) ?: 'Unidad',
                'stock_minimo' => $this->decimal($values['stock_critico'] ?? $values['stock_minimo'] ?? 0),
                'activo' => true,
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
                'activo' => true,
            ]);
            $variant->save();
            $variantsCreated += $isNewVariant ? 1 : 0;
        }

        return compact('created', 'updated', 'variantsCreated', 'skipped');
    }

    private function movementPayload(array $data, InventarioVariante $variante, float $cantidad): array
    {
        return [
            'origen' => 'MANUAL',
            'ubicacion_id' => $data['ubicacion_id'],
            'producto_id' => $variante->producto_id,
            'variante_id' => $variante->id,
            'cantidad' => $cantidad,
            'costo_unitario' => $data['costo_unitario'] ?: null,
            'documento_tipo' => $data['documento_tipo'] ?: null,
            'documento_numero' => $data['documento_numero'] ?: null,
            'destinatario_nombre' => $data['destinatario_nombre'] ?: null,
            'destinatario_rut' => $data['destinatario_rut'] ?: null,
            'centro_costo' => $data['centro_costo'] ?: null,
            'observacion' => $data['observacion'] ?: null,
            'ocurrido_en' => Carbon::parse($data['ocurrido_en']),
        ];
    }

    private function createMovement(array $attributes, User $user): InventarioMovimiento
    {
        return InventarioMovimiento::create($attributes + [
            'codigo' => $this->code('MOV'),
            'registrado_por' => $user->id,
            'registrado_por_nombre' => trim($user->name . ' ' . ($user->apellido_paterno ?? '')),
        ]);
    }

    private function ensureAvailability(int $ubicacionId, int $varianteId, float $quantity): void
    {
        $available = $this->stockActual($ubicacionId, $varianteId);
        if ($available + 0.0001 < $quantity) {
            throw ValidationException::withMessages([
                'cantidad' => 'Stock insuficiente en la ubicacion seleccionada. Disponible: ' . $this->number($available) . '.',
            ]);
        }
    }

    private function code(string $prefix): string
    {
        return $prefix . '-' . now()->format('Ymd') . '-' . Str::upper(Str::random(5));
    }

    private function availableProductCode(string $base): string
    {
        $base = Str::limit($base ?: 'PRODUCTO', 64, '');
        $candidate = $base;
        $index = 2;
        while (InventarioProducto::query()->where('codigo', $candidate)->exists()) {
            $candidate = Str::limit($base, 58, '') . '-' . $index++;
        }

        return $candidate;
    }

    private function availableVariantCode(string $productCode, string $size): string
    {
        $base = Str::limit($productCode . '-' . Str::upper(Str::slug($size ?: 'ESTANDAR')), 94, '');
        $candidate = $base;
        $index = 2;
        while (InventarioVariante::query()->where('codigo', $candidate)->exists()) {
            $candidate = Str::limit($base, 94, '') . '-' . $index++;
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

    private function normalizeHeader(mixed $header): string
    {
        return Str::of((string) $header)->ascii()->lower()->replace([' ', '-', '.'], '_')->replaceMatches('/_+/', '_')->trim('_')->toString();
    }

    private function importText(mixed $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $value));
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

    private function kizeoDocumentNumber(EntregaBodega $delivery): string
    {
        return 'KZ-' . ($delivery->kizeo_record_number ?: $delivery->kizeo_data_id);
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
