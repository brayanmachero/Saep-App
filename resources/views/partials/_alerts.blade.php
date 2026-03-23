@if(session('success'))
<div class="alert alert-success">
    <i class="bi bi-check-circle-fill"></i>
    {{ session('success') }}
</div>
@endif

@if(session('error'))
<div class="alert alert-danger">
    <i class="bi bi-exclamation-circle-fill"></i>
    {{ session('error') }}
</div>
@endif
