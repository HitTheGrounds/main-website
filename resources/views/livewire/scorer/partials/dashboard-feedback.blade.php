{{-- Notices and errors for the scorer dashboard. Rendered once, wherever the
     scorer is working (page, create popup or edit popup), so it is never hidden
     behind an open dialog. --}}
<div class="scorer-feedback" aria-live="polite">
    @if($notice)<p role="status" class="alert alert-success">{{ $notice }}</p>@endif
    @if($errors->any())
        <div role="alert" class="alert alert-error"><ul>
            @foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach
        </ul></div>
    @endif
</div>
