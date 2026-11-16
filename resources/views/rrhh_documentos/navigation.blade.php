<nav class="doc-tabs" aria-label="Gestión documental">
<a href="{{ route('rrhh-documentos.index') }}" @if(($section ?? '')==='batteries') aria-current="page" @endif>Baterías</a>
<a href="{{ route('rrhh-documentos.lots') }}" @if(($section ?? '')==='lots') aria-current="page" @endif>Lotes de trabajadores</a>
<a href="{{ route('rrhh-documentos.est') }}" @if(($section ?? '')==='guide') aria-current="page" @endif>Guía de documentos EST</a>
</nav>
