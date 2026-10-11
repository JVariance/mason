{{-- Where bricks are dropped into a container brick in the editor preview, with the add line (inserter) --}}
<div
    class="mason-drop-zone mason-drop-zone-nested @if ($isEmpty) mason-drop-zone-empty @endif"
    data-drop-parent="{{ $parent }}"
    data-drop-index="{{ $index }}"
    @if (! empty($attrs))
        data-drop-attrs="{{ json_encode($attrs) }}"
    @endif
>
    @if ($isEmpty)
        <span class="mason-drop-zone-placeholder">
            {{ __("mason::mason.preview.placeholder") }}
        </span>
    @endif

    @include("mason::components.inserter")
</div>
