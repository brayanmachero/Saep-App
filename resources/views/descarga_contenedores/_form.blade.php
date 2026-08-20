@php
    $descarga = $descarga ?? null;
    $selectedParticipantes = old('participantes_json');
    if ($selectedParticipantes) {
        $selectedParticipantes = json_decode($selectedParticipantes, true) ?: [];
    } else {
        $selectedParticipantes = $descarga?->participantes?->map(fn ($p) => [
            'id' => $p->talana_trabajador_id,
            'porcentaje' => $p->porcentaje_participacion,
        ])->filter(fn ($p) => !empty($p['id']))->values()->all() ?? [];
    }
    $supervisorActual = $descarga?->supervisor ?: ($supervisorSistema ?? auth()->user());
    $supervisorActualNombre = $supervisorActual?->nombre_completo ?: $supervisorActual?->name;
    $supervisorActualMeta = collect([
        $supervisorActual?->cargo?->nombre,
        $supervisorActual?->centroCosto?->nombre,
    ])->filter()->implode(' · ');
    $selectedTarifaId = old('tarifa_id', $descarga->tarifa_id ?? '');
    $selectedTarifa = $tarifas->first(fn ($t) => (string) $t->id === (string) $selectedTarifaId);
    $selectedFactCodigo = old('fact_codigo', $descarga->fact_codigo ?? ($selectedTarifa?->codigo ?? ''));
    $selectedTarifaSearch = $selectedTarifa
        ? trim($selectedTarifa->codigo . ' · ' . $selectedTarifa->cliente . ' · ' . ($selectedTarifa->centroCosto?->nombre ?: 'General') . ' · ' . $selectedTarifa->proceso)
        : $selectedFactCodigo;
    $puedeGestionarCostos = auth()->user()->puedeGestionarCostosDescargaContenedores();
    $tarifasPickerData = $tarifas->map(function ($t) use ($puedeGestionarCostos) {
        $data = [
            'id' => $t->id,
            'cliente' => $t->cliente,
            'centro_costo_id' => $t->centro_costo_id,
            'centro' => $t->centroCosto?->nombre,
            'codigo' => $t->codigo,
            'proceso' => $t->proceso,
            'requiere_revision' => $t->requiere_revision,
        ];

        if ($puedeGestionarCostos) {
            $data['costo_unitario'] = $t->costo_unitario;
        }
        $data['pago_colaborador'] = $t->pago_colaborador;

        return $data;
    })->values();
@endphp

@csrf
@if($descarga)
    @method('PUT')
@endif

<div class="form-panel">
    @include('descarga_contenedores._context_help', [
        'title' => 'Criterio de llenado',
        'items' => [
            'Guarda primero en borrador; coordinación valida cuando el registro queda completo.',
            'Supervisor sistema se toma desde el usuario conectado para mantener trazabilidad.',
            'El código FACT puede seleccionarse desde tarifa o escribirse manualmente para revisión.',
            'Los participantes se guardan con copia histórica de nombre, RUT, cargo y centro Talana.',
        ],
    ])

    <h4 class="section-title">Datos de la descarga</h4>
    <div class="form-grid">
        <div class="form-group">
            <label>Estado @include('descarga_contenedores._help_icon', ['text' => 'Todo registro nuevo entra como borrador. Validar corresponde a coordinación.'])</label>
            <input type="text" class="form-control readonly-control" value="{{ $descarga?->estadoBadge['label'] ?? 'Borrador' }}" readonly>
            <small class="muted-hint">Todo registro nuevo queda en borrador. La validación la realiza coordinación desde acciones rápidas.</small>
        </div>
        <div class="form-group">
            <label>Fecha @include('descarga_contenedores._help_icon', ['text' => 'Fecha en que se ejecuta o programa la descarga del contenedor.'])</label>
            <input type="date" name="fecha" value="{{ old('fecha', optional($descarga?->fecha)->format('Y-m-d')) }}" class="form-control">
        </div>
        <div class="form-group">
            <label>Operación @include('descarga_contenedores._help_icon', ['text' => 'Cliente o línea operativa asociada al contenedor. Sirve para reportes.'])</label>
            <input type="text" name="operacion" value="{{ old('operacion', $descarga->operacion ?? 'Walmart') }}" class="form-control" placeholder="Walmart, Maersk, DHL...">
        </div>
        <div class="form-group">
            <label>Centro de costo @include('descarga_contenedores._help_icon', ['text' => 'Centro gestionado por la operación. También filtra trabajadores disponibles en Talana.'])</label>
            <select name="centro_costo_id" class="form-control">
                <option value="">Sin asociar</option>
                @foreach($centros as $centro)
                    <option value="{{ $centro->id }}" {{ old('centro_costo_id', $descarga->centro_costo_id ?? '') == $centro->id ? 'selected' : '' }}>
                        {{ $centro->nombre }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label>Bodega / CD @include('descarga_contenedores._help_icon', ['text' => 'Nombre operativo de la bodega o centro de distribución informado en la programación.'])</label>
            <input type="text" name="bodega" value="{{ old('bodega', $descarga->bodega ?? '') }}" class="form-control" placeholder="LTS Peñón, Quilicura, Campos de Chile...">
        </div>
        <div class="form-group">
            <label>Facturación / mes @include('descarga_contenedores._help_icon', ['text' => 'Periodo interno de facturación o ciclo recibido en la programación.'])</label>
            <input type="text" name="facturacion_mes" value="{{ old('facturacion_mes', $descarga->facturacion_mes ?? '') }}" class="form-control" placeholder="Julio, P072026...">
        </div>
        <div class="form-group">
            <label>Contenedor @include('descarga_contenedores._help_icon', ['text' => 'Identificador o número del contenedor. Se usa para buscar duplicados y trazabilidad.'])</label>
            <input type="text" name="contenedor" value="{{ old('contenedor', $descarga->contenedor ?? '') }}" class="form-control" placeholder="N° contenedor">
        </div>
        <div class="form-group">
            <label>Equipo descarga @include('descarga_contenedores._help_icon', ['text' => 'Nombre del equipo, cuadrilla o grupo responsable de ejecutar la descarga.'])</label>
            <input type="text" name="equipo_descarga" value="{{ old('equipo_descarga', $descarga->equipo_descarga ?? '') }}" class="form-control" placeholder="SAEP 1, Equipo 3, Brazo AM...">
        </div>
        <div class="form-group">
            <label>Supervisor sistema @include('descarga_contenedores._help_icon', ['text' => 'Usuario autenticado que crea o edita el registro. No se escribe manualmente.'])</label>
            <input type="text" class="form-control readonly-control" value="{{ $supervisorActualNombre ?: 'Se asignará por login' }}" readonly>
            <small class="muted-hint">
                {{ $supervisorActualMeta ?: 'Se completa automáticamente con el usuario autenticado.' }}
            </small>
        </div>
        <div class="form-group">
            <label>Supervisor / encargado texto @include('descarga_contenedores._help_icon', ['text' => 'Nombre informado en correo, Excel o reporte cuando no coincide con el usuario conectado.'])</label>
            <input type="text" name="supervisor_nombre" value="{{ old('supervisor_nombre', $descarga->supervisor_nombre ?? '') }}" class="form-control" placeholder="Nombre recibido en correo o reporte">
        </div>
    </div>

    <h4 class="section-title">Detalle operativo</h4>
    <div class="form-grid">
        <div class="form-group">
            <label>Hora cita @include('descarga_contenedores._help_icon', ['text' => 'Hora programada para recibir o iniciar la operación.'])</label>
            <input type="time" name="hora_cita" value="{{ old('hora_cita', $descarga?->hora_cita ? substr($descarga->hora_cita, 0, 5) : '') }}" class="form-control">
        </div>
        <div class="form-group">
            <label>Inicio descarga @include('descarga_contenedores._help_icon', ['text' => 'Hora real de inicio. Permite medir desviaciones contra la cita.'])</label>
            <input type="time" name="hora_inicio_descarga" value="{{ old('hora_inicio_descarga', $descarga?->hora_inicio_descarga ? substr($descarga->hora_inicio_descarga, 0, 5) : '') }}" class="form-control">
        </div>
        <div class="form-group">
            <label>Término descarga @include('descarga_contenedores._help_icon', ['text' => 'Hora real de término de la descarga.'])</label>
            <input type="time" name="hora_termino_descarga" value="{{ old('hora_termino_descarga', $descarga?->hora_termino_descarga ? substr($descarga->hora_termino_descarga, 0, 5) : '') }}" class="form-control">
        </div>
        <div class="form-group">
            <label>Ítems @include('descarga_contenedores._help_icon', ['text' => 'Cantidad de líneas, SKU o ítems informados para el contenedor.'])</label>
            <input type="number" name="item" value="{{ old('item', $descarga->item ?? '') }}" class="form-control" min="0">
        </div>
        <div class="form-group">
            <label>Cajas @include('descarga_contenedores._help_icon', ['text' => 'Volumen de cajas descargadas. Se usa en reportes operativos.'])</label>
            <input type="number" name="cajas" value="{{ old('cajas', $descarga->cajas ?? '') }}" class="form-control" min="0">
        </div>
        <div class="form-group">
            <label>Pallets @include('descarga_contenedores._help_icon', ['text' => 'Cantidad de pallets asociados al contenedor cuando aplica.'])</label>
            <input type="number" name="pallets" value="{{ old('pallets', $descarga->pallets ?? '') }}" class="form-control" min="0" step="0.01">
        </div>
        <div class="form-group">
            <label>Producto @include('descarga_contenedores._help_icon', ['text' => 'Descripción corta del contenido descargado.'])</label>
            <input type="text" name="producto" value="{{ old('producto', $descarga->producto ?? '') }}" class="form-control" placeholder="Productos varios, pastas, aceites...">
        </div>
        <div class="form-group">
            <label>Tarifa FACT @include('descarga_contenedores._help_icon', ['text' => $puedeGestionarCostos ? 'Código FACT asociado a costo empresa y pago colaborador. Queda congelado en el registro.' : 'Código FACT operativo. El pago colaborador queda visible; el costo empresa queda reservado para coordinación.'])</label>
            <input type="hidden" name="tarifa_id" id="tarifa_id" value="{{ $selectedTarifaId }}">
            <input type="hidden" name="fact_codigo" id="fact_codigo" value="{{ $selectedFactCodigo }}">
            <div class="tarifa-picker" id="tarifa_picker">
                <div class="tarifa-search-wrap">
                    <input type="text" class="form-control tarifa-search" autocomplete="off" value="{{ $selectedTarifaSearch }}" placeholder="Buscar código FACT, cliente, centro o proceso...">
                    <div class="tarifa-dropdown"></div>
                </div>
                <div class="tarifa-selected {{ $selectedTarifa ? '' : 'is-empty' }}" data-tarifa-selected>
                    <span data-tarifa-selected-text>
                        @if($selectedTarifa)
                            <strong>{{ $selectedTarifa->codigo }}</strong> · {{ $selectedTarifa->cliente }} · {{ $selectedTarifa->centroCosto?->nombre ?: 'General' }} · {{ $selectedTarifa->proceso }}
                        @else
                            Sin tarifa asociada
                        @endif
                    </span>
                    <button type="button" class="icon-btn" data-clear-tarifa title="Limpiar tarifa"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>
            <small class="muted-hint">
                {{ $puedeGestionarCostos ? 'Busca por código, cliente, centro o proceso. Si eliges centro de costo, se priorizan tarifas del centro y tarifas generales del cliente.' : 'Busca por código, cliente, centro o proceso. El pago colaborador se muestra; el costo empresa queda reservado para coordinación.' }}
            </small>
        </div>
        <div class="form-group">
            <label>Código FACT seleccionado @include('descarga_contenedores._help_icon', ['text' => 'Vista de solo lectura del código que se guardará. Si no hay tarifa asociada, queda como pendiente de revisión.'])</label>
            <input type="text" id="fact_codigo_preview" class="form-control readonly-control" value="{{ $selectedFactCodigo }}" placeholder="Se completa desde la tarifa o al escribir un código" readonly>
            <small class="muted-hint">Si escribes un código manual sin elegir tarifa, se guardará como código FACT para revisión.</small>
        </div>
        <div class="form-group" style="grid-column:1/-1">
            <label>Observación @include('descarga_contenedores._help_icon', ['text' => 'Notas de apoyo: diferencias, apoyo de otro centro, incidencias o instrucciones de coordinación.'])</label>
            <textarea name="observacion" class="form-control" rows="3" placeholder="Notas operativas, apoyo de otro centro, diferencias, etc.">{{ old('observacion', $descarga->observacion ?? '') }}</textarea>
        </div>
        <div class="form-group" style="grid-column:1/-1">
            <label>Evidencia fotográfica @include('descarga_contenedores._help_icon', ['text' => 'Fotografías de respaldo asociadas al registro. JPG, PNG o WebP; hasta 8 archivos de 8 MB cada uno.'])</label>
            <input type="file" name="evidencias[]" class="form-control" accept="image/jpeg,image/png,image/webp" multiple data-evidence-input>
            <small class="muted-hint">Las imágenes quedan asociadas al registro y se conservan como respaldo privado. Máximo 8 fotos de 8 MB cada una.</small>
            <div class="evidence-selected-summary" data-evidence-summary>Sin fotos seleccionadas.</div>
            <div class="evidence-selected-list" data-evidence-list></div>
            @if($descarga && $descarga->evidencias->isNotEmpty())
                <div class="evidence-inline-list">
                    @foreach($descarga->evidencias as $evidencia)
                        <a href="{{ route('descarga-contenedores.evidencias.ver', $evidencia) }}" target="_blank" rel="noopener">
                            <i class="bi bi-image"></i>
                            {{ $evidencia->nombre_original }}
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <h4 class="section-title">Trabajadores que participaron @include('descarga_contenedores._help_icon', ['text' => 'Selecciona la dotación real que participó. Los porcentajes deben sumar 100% para validar.'])</h4>
    <input type="hidden" name="participantes_json" id="participantes_json" value='@json($selectedParticipantes)'>
    <div id="participantes_picker" class="worker-picker"></div>
    <small class="muted-hint">Fuente: nómina Talana acotada a los centros gestionados en los Excel de descarga. El sistema guardará una copia histórica de nombre, RUT, cargo y centro al momento del registro.</small>
</div>

<script type="application/json" id="trabajadores_data">@json($trabajadores)</script>
<script type="application/json" id="tarifas_data">@json($tarifasPickerData)</script>
<script>
const canViewCosts = @json($puedeGestionarCostos);

function initWorkerPicker(container, hiddenInput, initialIds) {
    const workers = JSON.parse(document.getElementById('trabajadores_data').textContent || '[]');
    const tarifas = JSON.parse(document.getElementById('tarifas_data').textContent || '[]');
    const selected = new Map();
    const byId = new Map(workers.map(w => [String(w.id), w]));
    const byTarifaId = new Map(tarifas.map(t => [String(t.id), t]));
    const tarifaSelect = document.getElementById('tarifa_id');
    const factInput = document.querySelector('[name="fact_codigo"]');
    const factPreview = document.getElementById('fact_codigo_preview');

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function normalizeText(value) {
        return String(value || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim();
    }

    function normalizeRut(value) {
        return String(value || '').toUpperCase().replace(/[^0-9K]/g, '');
    }

    function workerCenterKey(worker) {
        return worker.centro_costo_id
            ? `id:${worker.centro_costo_id}`
            : `name:${normalizeText(worker.centro || 'Sin centro')}`;
    }

    function workerCargoKey(worker) {
        return worker.cargo_id
            ? `id:${worker.cargo_id}`
            : `name:${normalizeText(worker.cargo || 'Sin cargo')}`;
    }

    function tarifaLabel(tarifa) {
        return `${tarifa.codigo || ''} · ${tarifa.cliente || ''} · ${tarifa.centro || 'General'} · ${tarifa.proceso || ''}`;
    }

    function setFactCodigo(value) {
        const clean = String(value || '').trim().toUpperCase();
        if (factInput) factInput.value = clean;
        if (factPreview) factPreview.value = clean;
    }

    function initTarifaPicker() {
        const picker = document.getElementById('tarifa_picker');
        if (!picker || !tarifaSelect || !factInput) return;

        const search = picker.querySelector('.tarifa-search');
        const dropdown = picker.querySelector('.tarifa-dropdown');
        const selectedBox = picker.querySelector('[data-tarifa-selected]');
        const selectedText = picker.querySelector('[data-tarifa-selected-text]');
        const clearBtn = picker.querySelector('[data-clear-tarifa]');
        const recordCenterSelect = document.querySelector('[name="centro_costo_id"]');

        function tarifaMatchesRecordCenter(tarifa) {
            const centerId = recordCenterSelect?.value || '';
            return !centerId || !tarifa.centro_costo_id || String(tarifa.centro_costo_id) === String(centerId);
        }

        function clienteFromOperacion(operacion) {
            const text = normalizeText(operacion);
            if (!text) return '';
            if (text.includes('smu') || text.includes('unimarc')) return 'SMU';
            if (text.includes('walmart') || text === 'wm' || text.startsWith('wm ')) return 'WM';
            return '';
        }

        function uniqueTarifaByCode(code, centerId = '', cliente = '') {
            const clean = String(code || '').trim().toUpperCase();
            if (!clean) return null;

            let matches = tarifas.filter(t => {
                if (String(t.codigo || '').trim().toUpperCase() !== clean) return false;
                return !centerId || !t.centro_costo_id || String(t.centro_costo_id) === String(centerId);
            });

            if (cliente) {
                const byClient = matches.filter(t => String(t.cliente || '').toUpperCase() === cliente);
                if (byClient.length) matches = byClient;
            }

            const scoped = centerId
                ? matches.filter(t => String(t.centro_costo_id || '') === String(centerId))
                : [];
            if (scoped.length === 1) return scoped[0];

            const general = matches.filter(t => !t.centro_costo_id);
            if (general.length === 1) return general[0];

            return matches.length === 1 ? matches[0] : null;
        }

        function currentCliente() {
            return clienteFromOperacion(document.querySelector('[name="operacion"]')?.value || '');
        }

        function resolveTypedCode(raw) {
            const manualCode = String(raw || '').trim().toUpperCase();
            const auto = uniqueTarifaByCode(manualCode, recordCenterSelect?.value || '', currentCliente());

            if (auto) {
                tarifaSelect.value = String(auto.id);
                setFactCodigo(auto.codigo);
                updateSelected(auto);
                return auto;
            }

            tarifaSelect.value = '';
            setFactCodigo(manualCode);
            updateSelected(null, manualCode);
            return null;
        }

        function updateSelected(tarifa = null, manualCode = '') {
            const hasValue = !!tarifa || !!manualCode;
            selectedBox?.classList.toggle('is-empty', !hasValue);

            if (!selectedText) return;

            if (tarifa) {
                const badge = tarifa.requiere_revision ? '<span class="badge warning">Revisar</span>' : '';
                selectedText.innerHTML = `<strong>${escapeHtml(tarifa.codigo)}</strong> · ${escapeHtml(tarifa.cliente)} · ${escapeHtml(tarifa.centro || 'General')} · ${escapeHtml(tarifa.proceso)} ${badge}`;
                return;
            }

            selectedText.textContent = manualCode ? `Código manual: ${manualCode}` : 'Sin tarifa asociada';
        }

        function renderTarifas(query = '') {
            const q = query.trim().toLowerCase();
            const matches = tarifas.filter(t => {
                const searchable = [
                    t.codigo,
                    t.cliente,
                    t.centro,
                    t.proceso,
                ].join(' ').toLowerCase();

                return tarifaMatchesRecordCenter(t) && (!q || searchable.includes(q));
            }).slice(0, 80);

            dropdown.innerHTML = matches.length
                ? matches.map(t => `
                    <div class="tarifa-option" data-id="${escapeHtml(t.id)}" role="button" tabindex="0">
                        <strong>${escapeHtml(t.codigo)}</strong>
                        <span>${escapeHtml(t.cliente)} · ${escapeHtml(t.centro || 'General')} · ${escapeHtml(t.proceso)}</span>
                        ${t.requiere_revision ? '<em>Revisar</em>' : ''}
                    </div>
                `).join('')
                : '<div class="tarifa-empty">Sin resultados. Puedes dejar el código escrito para revisión.</div>';
            dropdown.style.display = 'block';

            dropdown.querySelectorAll('.tarifa-option').forEach(option => {
                const selectOption = event => {
                    event.preventDefault();
                    if (dropdown.style.display === 'none') return;

                    const tarifa = byTarifaId.get(String(option.dataset.id));
                    if (!tarifa) return;

                    tarifaSelect.value = String(tarifa.id);
                    search.value = tarifaLabel(tarifa);
                    setFactCodigo(tarifa.codigo);
                    updateSelected(tarifa);
                    dropdown.style.display = 'none';
                    tarifaSelect.dispatchEvent(new Event('change', { bubbles: true }));
                };

                option.addEventListener('mousedown', selectOption);
                option.addEventListener('click', selectOption);
                option.addEventListener('keydown', event => {
                    if (event.key === 'Enter' || event.key === ' ') selectOption(event);
                });
            });
        }

        search?.addEventListener('focus', () => renderTarifas(search.value));
        search?.addEventListener('input', () => {
            const auto = resolveTypedCode(search.value);
            renderTarifas(search.value);
            tarifaSelect.dispatchEvent(new Event('change', { bubbles: true }));
            if (auto && document.activeElement !== search) {
                search.value = tarifaLabel(auto);
            }
        });
        search?.addEventListener('blur', () => {
            const auto = resolveTypedCode(search.value);
            if (auto && search) search.value = tarifaLabel(auto);
            tarifaSelect.dispatchEvent(new Event('change', { bubbles: true }));
            setTimeout(() => dropdown.style.display = 'none', 150);
        });
        clearBtn?.addEventListener('click', () => {
            tarifaSelect.value = '';
            if (search) search.value = '';
            setFactCodigo('');
            updateSelected();
            dropdown.style.display = 'none';
            tarifaSelect.dispatchEvent(new Event('change', { bubbles: true }));
        });
        recordCenterSelect?.addEventListener('change', () => {
            resolveTypedCode(factInput.value || search?.value || '');
            tarifaSelect.dispatchEvent(new Event('change', { bubbles: true }));
            if (search === document.activeElement) {
                renderTarifas(search.value);
            }
        });
        document.querySelector('[name="operacion"]')?.addEventListener('change', () => {
            resolveTypedCode(factInput.value || search?.value || '');
            tarifaSelect.dispatchEvent(new Event('change', { bubbles: true }));
        });
        document.querySelector('[name="operacion"]')?.addEventListener('input', () => {
            resolveTypedCode(factInput.value || search?.value || '');
            tarifaSelect.dispatchEvent(new Event('change', { bubbles: true }));
        });

        const initialTarifa = byTarifaId.get(String(tarifaSelect.value || ''));
        if (initialTarifa) {
            updateSelected(initialTarifa);
            setFactCodigo(initialTarifa.codigo);
        } else {
            const auto = resolveTypedCode(factInput.value);
            if (auto && search) search.value = tarifaLabel(auto);
        }
    }

    initTarifaPicker();

    container.innerHTML = `
        <div class="worker-tags"></div>
        <div class="distribution-summary">
            <span>Total: <strong data-total>0%</strong></span>
            <span class="distribution-status" data-total-hint>Debe sumar 100% para validar.</span>
            <span>Pago estimado: <strong data-pago>$0</strong></span>
            <button type="button" class="btn-secondary btn-mini" data-equalize>Repartir igual</button>
            <button type="button" class="btn-secondary btn-mini" data-add-filtered>Agregar lista filtrada</button>
        </div>
        <div class="worker-filter-row">
            <select class="form-control worker-center-filter" aria-label="Filtrar trabajadores por centro">
                <option value="">Todos los centros</option>
            </select>
            <select class="form-control worker-cargo-filter" aria-label="Filtrar trabajadores por cargo">
                <option value="">Todos los cargos</option>
            </select>
            <span class="worker-filter-count" data-worker-count></span>
        </div>
        <div class="worker-search-wrap">
            <input type="text" class="form-control worker-search" autocomplete="off" placeholder="Buscar trabajador por nombre, RUT, cargo o centro...">
            <div class="worker-dropdown"></div>
        </div>
    `;

    const tags = container.querySelector('.worker-tags');
    const input = container.querySelector('.worker-search');
    const dropdown = container.querySelector('.worker-dropdown');
    const centerFilter = container.querySelector('.worker-center-filter');
    const cargoFilter = container.querySelector('.worker-cargo-filter');
    const countEl = container.querySelector('[data-worker-count]');
    const descargaCenterSelect = document.querySelector('[name="centro_costo_id"]');
    const totalEl = container.querySelector('[data-total]');
    const totalHintEl = container.querySelector('[data-total-hint]');
    const pagoEl = container.querySelector('[data-pago]');
    const equalizeBtn = container.querySelector('[data-equalize]');
    const addFilteredBtn = container.querySelector('[data-add-filtered]');

    const centerOptions = [...new Map(workers.map(worker => [
        workerCenterKey(worker),
        worker.centro || 'Sin centro',
    ])).entries()]
        .sort((a, b) => a[1].localeCompare(b[1], 'es'));
    centerOptions.forEach(([value, label]) => {
        centerFilter.insertAdjacentHTML('beforeend', `<option value="${escapeHtml(value)}">${escapeHtml(label)}</option>`);
    });

    function cargoOptionsForCenter(centerValue = '') {
        const source = centerValue
            ? workers.filter(worker => workerCenterKey(worker) === centerValue)
            : workers;

        return [...new Map(source.map(worker => [
            workerCargoKey(worker),
            worker.cargo || 'Sin cargo',
        ])).entries()]
            .sort((a, b) => a[1].localeCompare(b[1], 'es'));
    }

    function refreshCargoOptions() {
        const currentCargo = cargoFilter.value;
        const options = cargoOptionsForCenter(centerFilter.value);

        cargoFilter.innerHTML = '<option value="">Todos los cargos</option>';
        options.forEach(([value, label]) => {
            cargoFilter.insertAdjacentHTML('beforeend', `<option value="${escapeHtml(value)}">${escapeHtml(label)}</option>`);
        });

        cargoFilter.value = options.some(([value]) => value === currentCargo) ? currentCargo : '';
    }

    function syncCenterFilterFromRecord() {
        const selectedCenterId = descargaCenterSelect?.value ? `id:${descargaCenterSelect.value}` : '';
        if (selectedCenterId && centerOptions.some(([value]) => value === selectedCenterId)) {
            centerFilter.value = selectedCenterId;
        } else if (!selectedCenterId) {
            centerFilter.value = '';
        }
        refreshCargoOptions();
    }

    refreshCargoOptions();
    syncCenterFilterFromRecord();

    (initialIds || []).forEach(item => {
        const id = typeof item === 'object' ? item.id : item;
        const porcentaje = typeof item === 'object' && item.porcentaje !== null && item.porcentaje !== undefined
            ? parseFloat(item.porcentaje)
            : null;
        const worker = byId.get(String(id));
        if (worker) selected.set(String(id), { worker, porcentaje });
    });

    if ([...selected.values()].every(item => item.porcentaje === null || Number.isNaN(item.porcentaje))) {
        equalize();
    }

    function currentTarifa() {
        return byTarifaId.get(String(tarifaSelect?.value || '')) || null;
    }

    function formatCurrency(value) {
        if (value === null || value === undefined || value === '' || Number.isNaN(Number(value))) return '—';
        return '$' + Math.round(Number(value)).toLocaleString('es-CL');
    }

    function pagoTotal() {
        const tarifa = currentTarifa();
        if (!tarifa || tarifa.requiere_revision || tarifa.pago_colaborador === null || tarifa.pago_colaborador === undefined) {
            return null;
        }
        return Number(tarifa.pago_colaborador);
    }

    function equalize() {
        const ids = [...selected.keys()];
        if (!ids.length) return;
        const base = Math.round((100 / ids.length) * 100) / 100;
        let assigned = 0;
        ids.forEach((id, index) => {
            const entry = selected.get(id);
            entry.porcentaje = index === ids.length - 1
                ? Math.round((100 - assigned) * 100) / 100
                : base;
            assigned += entry.porcentaje;
            selected.set(id, entry);
        });
    }

    function sync(renderTags = true) {
        const pago = pagoTotal();
        let total = 0;
        hiddenInput.value = JSON.stringify([...selected.entries()].map(([id, entry]) => {
            const porcentaje = Number(entry.porcentaje || 0);
            total += porcentaje;
            return { id: parseInt(id, 10), porcentaje };
        }));

        const roundedTotal = Math.round(total * 100) / 100;
        const difference = Math.round((100 - roundedTotal) * 100) / 100;
        const isComplete = Math.abs(difference) <= 0.05;

        totalEl.textContent = `${roundedTotal}%`;
        totalEl.style.color = isComplete ? 'var(--success-color)' : '#d97706';
        if (totalHintEl) {
            totalHintEl.classList.toggle('is-ok', isComplete);
            totalHintEl.textContent = isComplete
                ? 'Listo para validar.'
                : (difference > 0 ? `Falta ${difference}% por asignar.` : `Sobra ${Math.abs(difference)}%. Ajusta el reparto.`);
        }
        if (pagoEl) {
            pagoEl.textContent = pago !== null ? formatCurrency(pago) : 'Revisar tarifa';
        }

        if (!renderTags) return;

        tags.innerHTML = '';
        selected.forEach((entry, id) => {
            const worker = entry.worker;
            const porcentaje = Number(entry.porcentaje || 0);
            const monto = pago !== null ? pago * porcentaje / 100 : null;
            const tag = document.createElement('span');
            tag.className = `worker-tag${canViewCosts ? '' : ' no-costs'}`;
            tag.innerHTML = `
                <span class="worker-main">
                    <strong>${worker.label}</strong>
                    <small>${worker.centro || 'Sin centro'}${worker.cargo ? ' · ' + worker.cargo : ''}</small>
                </span>
                <label class="percent-control">
                    <input type="number" min="0" max="100" step="0.01" value="${porcentaje}">
                    <span>%</span>
                </label>
                <small class="worker-amount">${monto !== null ? formatCurrency(monto) : 'Monto por revisar'}</small>
                <button type="button" title="Quitar">&times;</button>
            `;
            tag.querySelector('input').addEventListener('input', event => {
                entry.porcentaje = parseFloat(event.target.value || '0');
                selected.set(id, entry);
                const updatedPago = pagoTotal();
                const updatedMonto = updatedPago !== null ? updatedPago * Number(entry.porcentaje || 0) / 100 : null;
                const amountEl = tag.querySelector('.worker-amount');
                if (amountEl) {
                    amountEl.textContent = updatedMonto !== null ? formatCurrency(updatedMonto) : 'Monto por revisar';
                }
                sync(false);
            });
            tag.querySelector('button').addEventListener('click', () => {
                selected.delete(id);
                equalize();
                sync();
            });
            tags.appendChild(tag);
        });
    }

    function positionWorkerDropdown() {
        const rect = input.getBoundingClientRect();
        const gap = 4;
        const viewportPadding = 16;
        const minDropdownHeight = 120;
        const actions = document.querySelector('.container-form-actions');
        const actionsRect = actions?.getBoundingClientRect();
        const bottomLimit = actionsRect && actionsRect.top < window.innerHeight && actionsRect.bottom > 0
            ? Math.max(viewportPadding, actionsRect.top - viewportPadding)
            : window.innerHeight - viewportPadding;
        const spaceBelow = bottomLimit - rect.bottom;
        const spaceAbove = rect.top - viewportPadding;
        const openUp = spaceBelow < minDropdownHeight && spaceAbove > spaceBelow;
        const availableSpace = Math.max(minDropdownHeight, openUp ? spaceAbove - gap : spaceBelow - gap);
        const maxHeight = Math.min(320, availableSpace);

        dropdown.classList.add('is-floating');
        dropdown.style.left = `${rect.left}px`;
        dropdown.style.width = `${rect.width}px`;
        dropdown.style.right = 'auto';
        dropdown.style.top = 'auto';
        dropdown.style.bottom = 'auto';
        dropdown.style.maxHeight = `${maxHeight}px`;

        if (openUp) {
            dropdown.style.bottom = `${Math.max(viewportPadding, window.innerHeight - rect.top + gap)}px`;
        } else {
            dropdown.style.top = `${rect.bottom + gap}px`;
        }
    }

    function showWorkerDropdown() {
        if (dropdown.parentElement !== document.body) {
            document.body.appendChild(dropdown);
        }

        dropdown.style.display = 'block';
        positionWorkerDropdown();
    }

    function hideWorkerDropdown() {
        dropdown.style.display = 'none';
        dropdown.style.top = '';
        dropdown.style.bottom = '';
    }

    function availableWorkers(query = '') {
        const q = normalizeText(query);
        const rutQuery = normalizeRut(query);
        const centerValue = centerFilter.value;
        const cargoValue = cargoFilter.value;

        return workers.filter(w => {
            if (selected.has(String(w.id))) return false;
            if (centerValue && workerCenterKey(w) !== centerValue) return false;
            if (cargoValue && workerCargoKey(w) !== cargoValue) return false;

            return !q
                || normalizeText(w.label).includes(q)
                || normalizeText(w.rut).includes(q)
                || normalizeRut(w.rut).includes(rutQuery)
                || normalizeText(w.cargo).includes(q)
                || normalizeText(w.centro).includes(q)
                || normalizeText(w.centro_talana).includes(q);
        });
    }

    function render(query = '') {
        const available = availableWorkers(query);
        const matches = available
            .sort((a, b) => `${a.centro || ''} ${a.cargo || ''} ${a.label || ''}`.localeCompare(`${b.centro || ''} ${b.cargo || ''} ${b.label || ''}`, 'es'))
            .slice(0, 70);
        countEl.textContent = `${available.length} disponible${available.length === 1 ? '' : 's'}`;

        if (matches.length) {
            let currentGroup = '';
            dropdown.innerHTML = matches.map(w => {
                const group = `${w.centro || 'Sin centro'} · ${w.cargo || 'Sin cargo'}`;
                const heading = group !== currentGroup
                    ? `<div class="worker-group">${escapeHtml(group)}</div>`
                    : '';
                currentGroup = group;

                return `
                    ${heading}
                    <div class="worker-option" data-id="${w.id}" role="button" tabindex="0">
                        <strong>${escapeHtml(w.label)}</strong>
                        <small>${escapeHtml(w.rut || '')}${w.cargo ? ' · ' + escapeHtml(w.cargo) : ''}</small>
                        <em>${escapeHtml(w.centro || 'Sin centro')}</em>
                    </div>
                `;
            }).join('');
        } else {
            dropdown.innerHTML = '<div class="worker-empty">Sin resultados para ese centro/cargo</div>';
        }
        showWorkerDropdown();

        dropdown.querySelectorAll('.worker-option').forEach(option => {
            const selectOption = event => {
                event.preventDefault();
                if (dropdown.style.display === 'none') return;

                const worker = byId.get(String(option.dataset.id));
                if (worker) {
                    selected.set(String(worker.id), { worker, porcentaje: null });
                    equalize();
                    input.value = '';
                    hideWorkerDropdown();
                    sync();
                }
            };

            option.addEventListener('mousedown', selectOption);
            option.addEventListener('click', selectOption);
            option.addEventListener('keydown', event => {
                if (event.key === 'Enter' || event.key === ' ') selectOption(event);
            });
        });
    }

    input.addEventListener('focus', () => render(input.value.trim()));
    input.addEventListener('input', () => render(input.value.trim()));
    input.addEventListener('blur', () => setTimeout(hideWorkerDropdown, 150));
    centerFilter.addEventListener('change', () => {
        refreshCargoOptions();
        render(input.value.trim());
    });
    cargoFilter.addEventListener('change', () => render(input.value.trim()));
    descargaCenterSelect?.addEventListener('change', () => {
        syncCenterFilterFromRecord();
        render(input.value.trim());
    });
    equalizeBtn.addEventListener('click', () => {
        equalize();
        sync();
    });
    addFilteredBtn.addEventListener('click', () => {
        const ids = availableWorkers(input.value.trim()).map(worker => worker.id);
        if (!ids.length) {
            if (window.showToast) window.showToast('No hay trabajadores disponibles para agregar con ese filtro.', 'warning');
            return;
        }

        ids.forEach(id => {
            const worker = byId.get(String(id));
            if (worker) selected.set(String(id), { worker, porcentaje: null });
        });
        equalize();
        input.value = '';
        hideWorkerDropdown();
        sync();
    });
    tarifaSelect?.addEventListener('change', () => sync());
    window.addEventListener('resize', () => {
        if (dropdown.style.display !== 'none') positionWorkerDropdown();
    });
    const closeDropdownOnViewportMove = event => {
        if (dropdown.style.display === 'none') return;
        if (event.target === dropdown || dropdown.contains(event.target)) return;

        hideWorkerDropdown();
    };
    window.addEventListener('scroll', closeDropdownOnViewportMove, true);
    document.addEventListener('scroll', closeDropdownOnViewportMove, true);
    window.addEventListener('wheel', closeDropdownOnViewportMove, { capture: true, passive: true });
    window.addEventListener('touchmove', closeDropdownOnViewportMove, { capture: true, passive: true });
    sync();
}

function initEvidenceInput(input) {
    const summary = document.querySelector('[data-evidence-summary]');
    const list = document.querySelector('[data-evidence-list]');
    const maxFiles = 8;
    const maxBytes = 8 * 1024 * 1024;

    function escapeEvidenceHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatBytes(bytes) {
        if (!bytes) return '0 KB';
        if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
        return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
    }

    function renderEvidenceSelection() {
        const files = Array.from(input.files || []);
        const tooLarge = files.filter(file => file.size > maxBytes).length;
        const overLimit = files.length > maxFiles;
        const hasWarning = tooLarge > 0 || overLimit;

        if (summary) {
            summary.classList.toggle('is-warning', hasWarning);
            if (files.length === 0) {
                summary.textContent = 'Sin fotos seleccionadas.';
            } else {
                summary.textContent = `${files.length} foto${files.length === 1 ? '' : 's'} seleccionada${files.length === 1 ? '' : 's'}${hasWarning ? ' - revisa limites antes de guardar.' : '.'}`;
            }
        }

        if (!list) return;
        list.innerHTML = files.map((file, index) => {
            const warning = file.size > maxBytes ? '<span class="evidence-file-warning">Supera 8 MB</span>' : '';

            return `
                <div class="evidence-selected-item">
                    <i class="bi bi-image"></i>
                    <span title="${escapeEvidenceHtml(file.name)}">${index + 1}. ${escapeEvidenceHtml(file.name)}</span>
                    <small>${formatBytes(file.size)}</small>
                    ${warning}
                </div>
            `;
        }).join('');
    }

    input.addEventListener('change', renderEvidenceSelection);
    renderEvidenceSelection();
}

document.addEventListener('DOMContentLoaded', () => {
    const picker = document.getElementById('participantes_picker');
    const hidden = document.getElementById('participantes_json');
    if (picker && hidden) {
        let initial = [];
        try { initial = JSON.parse(hidden.value || '[]'); } catch (e) {}
        initWorkerPicker(picker, hidden, initial);
    }

    const evidenceInput = document.querySelector('[data-evidence-input]');
    if (evidenceInput) {
        initEvidenceInput(evidenceInput);
    }
});
</script>

<style>
.form-panel { max-width: 1120px; margin: 0 auto; }
.form-panel .form-grid { gap: 1rem; }
.form-panel .form-group { min-width: 0; }
.form-panel label {
    display: inline-flex;
    align-items: center;
    gap: .25rem;
    color: var(--text-main);
    font-size: .92rem;
    font-weight: 700;
    line-height: 1.25;
    margin-bottom: .35rem;
}
.form-panel .form-control {
    min-height: 44px;
    font-size: .95rem;
    line-height: 1.25;
    border-radius: 8px;
}
.form-panel textarea.form-control {
    min-height: 104px;
}
.section-title {
    margin: 0 0 1rem;
    color: var(--text-muted);
    font-size: .86rem;
    text-transform: uppercase;
    letter-spacing: .06em;
    font-weight: 700;
    padding-bottom: .5rem;
    border-bottom: 1px solid var(--surface-border);
}
.section-title:not(:first-child) { margin-top: 1.5rem; }
.muted-hint {
    display: block;
    margin-top: .45rem;
    color: var(--text-muted);
    font-size: .82rem;
    line-height: 1.4;
}
.evidence-inline-list {
    display: flex;
    flex-wrap: wrap;
    gap: .45rem;
    margin-top: .65rem;
}
.evidence-inline-list a {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    max-width: 260px;
    padding: .42rem .6rem;
    border: 1px solid var(--surface-border);
    border-radius: 8px;
    color: var(--text-main);
    background: var(--surface-bg);
    font-size: .82rem;
    text-decoration: none;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.evidence-selected-summary {
    margin-top: .55rem;
    color: var(--text-muted);
    font-size: .84rem;
    line-height: 1.35;
}
.evidence-selected-summary.is-warning {
    color: #d97706;
    font-weight: 700;
}
.evidence-selected-list {
    display: grid;
    gap: .35rem;
    margin-top: .5rem;
    max-width: 640px;
}
.evidence-selected-item {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto auto;
    align-items: center;
    gap: .45rem;
    padding: .45rem .55rem;
    border: 1px solid var(--surface-border);
    border-radius: 8px;
    background: rgba(15, 27, 76, .05);
    color: var(--text-main);
    font-size: .84rem;
}
.evidence-selected-item span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.evidence-selected-item small {
    color: var(--text-muted);
    white-space: nowrap;
}
.evidence-file-warning {
    color: #d97706;
    font-size: .76rem;
    font-weight: 800;
    white-space: nowrap;
}
.readonly-control { background: var(--surface-bg); color: var(--text-main); cursor: default; }
.tarifa-picker { display: grid; gap: .45rem; }
.tarifa-search-wrap { position: relative; }
.tarifa-dropdown {
    display: none;
    position: absolute;
    left: 0;
    right: 0;
    z-index: 1000;
    max-height: 280px;
    overflow-y: auto;
    background: var(--surface-card-solid);
    border: 1px solid var(--surface-border);
    border-radius: 10px;
    margin-top: 3px;
    box-shadow: 0 10px 30px rgba(0,0,0,.14);
}
.tarifa-option {
    display: grid;
    grid-template-columns: 98px 1fr auto;
    align-items: center;
    gap: .55rem;
    padding: .8rem .9rem;
    cursor: pointer;
}
.tarifa-option:hover { background: rgba(15, 27, 76, .06); }
.tarifa-option span,
.tarifa-empty { color: var(--text-muted); font-size: .84rem; }
.tarifa-option em {
    font-style: normal;
    color: #d97706;
    font-size: .72rem;
    font-weight: 700;
}
.tarifa-empty { padding: .7rem .8rem; }
.tarifa-selected {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .5rem;
    min-height: 44px;
    padding: .55rem .65rem;
    border-radius: 8px;
    background: rgba(15, 27, 76, .06);
    color: var(--text-main);
    font-size: .9rem;
}
.tarifa-selected.is-empty { color: var(--text-muted); background: rgba(107, 114, 128, .08); }
.tarifa-selected .icon-btn { width: 38px; height: 38px; flex: 0 0 auto; }
.worker-picker { display: grid; gap: .5rem; }
.distribution-summary {
    display: flex;
    align-items: center;
    gap: .75rem;
    flex-wrap: wrap;
    color: var(--text-muted);
    font-size: .92rem;
    padding: .65rem .75rem;
    border: 1px solid var(--surface-border);
    border-radius: 8px;
    background: rgba(15, 27, 76, .04);
}
.distribution-summary [data-total] {
    font-size: 1.05rem;
    font-weight: 800;
}
.distribution-status {
    color: #d97706;
    font-weight: 700;
}
.distribution-status.is-ok {
    color: var(--success-color);
}
.worker-filter-row {
    display: grid;
    grid-template-columns: minmax(170px, 1fr) minmax(170px, 1fr) auto;
    gap: .5rem;
    align-items: center;
    max-width: 760px;
}
.worker-filter-count {
    color: var(--text-muted);
    font-size: .84rem;
    white-space: nowrap;
}
.btn-mini { padding: .5rem .75rem; font-size: .84rem; min-height: 38px; }
.worker-tags { display: flex; flex-wrap: wrap; gap: .45rem; min-height: 2rem; }
.worker-tag {
    display: grid;
    grid-template-columns: minmax(180px, 1fr) auto auto auto;
    align-items: center;
    gap: .55rem;
    padding: .6rem .7rem;
    border-radius: 8px;
    background: rgba(15, 27, 76, .08);
    color: var(--text-main);
    font-size: .9rem;
    min-width: 460px;
}
.worker-tag.no-costs {
    grid-template-columns: minmax(180px, 1fr) auto auto;
    min-width: 360px;
}
.worker-main { display: grid; gap: .1rem; min-width: 0; }
.worker-tag small { color: var(--text-muted); font-size: .78rem; }
.percent-control {
    display: inline-flex;
    align-items: center;
    gap: .25rem;
    color: var(--text-muted);
    font-size: .85rem;
}
.percent-control input {
    width: 84px;
    min-height: 38px;
    padding: .45rem .55rem;
    border: 1px solid var(--surface-border);
    border-radius: 8px;
    background: var(--surface-card-solid);
    color: var(--text-main);
    font-size: .9rem;
}
.worker-amount { min-width: 92px; text-align: right; }
.worker-tag button {
    width: 36px;
    height: 36px;
    border: 1px solid var(--surface-border);
    border-radius: 8px;
    background: var(--surface-card-solid);
    color: var(--text-muted);
    cursor: pointer;
    font-size: 1.1rem;
    line-height: 1;
}
.worker-search-wrap { position: relative; max-width: 620px; }
.worker-dropdown {
    display: none;
    position: absolute;
    left: 0;
    right: 0;
    z-index: 1000;
    max-height: 280px;
    overflow-y: auto;
    background: var(--surface-card-solid);
    border: 1px solid var(--surface-border);
    border-radius: 10px;
    margin-top: 3px;
    box-shadow: 0 10px 30px rgba(0,0,0,.14);
}
.worker-dropdown.is-floating {
    position: fixed;
    right: auto;
    margin-top: 0;
    z-index: 5000;
}
.worker-option { padding: .8rem .9rem; cursor: pointer; display: grid; gap: .2rem; }
.worker-option:hover { background: rgba(15, 27, 76, .06); }
.worker-option em {
    color: var(--text-muted);
    font-size: .78rem;
    font-style: normal;
}
.worker-group {
    padding: .55rem .9rem .3rem;
    color: var(--text-muted);
    font-size: .72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .04em;
    background: rgba(107, 114, 128, .08);
}
.worker-option small, .worker-empty { color: var(--text-muted); font-size: .84rem; }
.worker-empty { padding: .7rem .8rem; }
.container-form-actions {
    position: sticky;
    bottom: .75rem;
    z-index: 20;
    display: flex;
    justify-content: flex-end;
    gap: .75rem;
    margin-top: 1.5rem;
    padding: .75rem;
    border: 1px solid var(--surface-border);
    border-radius: 10px;
    background: var(--surface-card-solid);
    box-shadow: 0 12px 28px rgba(15, 23, 42, .14);
}
.container-form-actions .btn-secondary,
.container-form-actions .btn-premium {
    min-height: 46px;
    padding: .7rem 1.1rem;
    font-size: .95rem;
}
.container-form-actions .primary-save {
    min-width: 190px;
    justify-content: center;
}
@media (max-width: 640px) {
    .tarifa-option { grid-template-columns: 1fr; align-items: start; }
    .worker-filter-row { grid-template-columns: 1fr; max-width: none; }
    .worker-filter-count { white-space: normal; }
    .worker-search-wrap { max-width: none; }
    .worker-tag { width: 100%; min-width: 0; grid-template-columns: 1fr auto; }
    .worker-amount { text-align: left; }
    .evidence-selected-item { grid-template-columns: auto minmax(0, 1fr); }
    .evidence-selected-item small,
    .evidence-file-warning { grid-column: 2; }
    .container-form-actions {
        bottom: .4rem;
        flex-direction: column-reverse;
    }
    .container-form-actions .btn-secondary,
    .container-form-actions .btn-premium {
        width: 100%;
        justify-content: center;
    }
}
</style>
