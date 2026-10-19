{{-- A brick in the editor preview with its controls; also used for the bricks inside a container brick --}}
<div
    class="mason-block"
    draggable="{{ $block["movementLocked"] ?? false ? "false" : "true" }}"
    data-block-index="{{ $block["index"] }}"
    data-block-path="{{ $block["path"] }}"
    @if ($block["uid"] ?? null)
        data-uid="{{ $block["uid"] }}"
    @endif
    data-brick-id="{{ $block["id"] }}"
    data-config="{{ json_encode($block["config"]) }}"
    data-total-blocks="{{ $total }}"
    @if (is_array($block["accepts"] ?? null))
        data-accepts="{{ implode(",", $block["accepts"]) }}"
    @endif
    @if ($block["template"] ?? null)
        data-template="{{ $block["template"] }}"
    @endif
    @if ($block["movementLocked"] ?? false)
        data-movement-locked
    @endif
    @if ($block["hidden"] ?? false)
        data-hidden
    @endif
>
    @if (($block["template"] ?? null) && (($block["removalLocked"] ?? false) || ($block["movementLocked"] ?? false)))
        <span class="mason-block-badge">
            {{ __("mason::mason.preview.template") }}@if ($block["hidden"] ?? false)· {{ __("mason::mason.preview.hidden") }}
            @endif
        </span>
    @elseif ($block["hidden"] ?? false)
        <span class="mason-block-badge">
            {{ __("mason::mason.preview.hidden") }}
        </span>
    @endif
    <div class="mason-block-controls" data-focus="mason-block-controls">
        @unless ($block["movementLocked"] ?? false)
            <button
                class="mason-block-btn"
                title="Move Up"
                data-action="move-up"
                data-block-index="{{ $block["index"] }}"
                data-total-blocks="{{ $total }}"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <title>{{ __("mason::mason.preview.move_up") }}</title>
                    <polyline points="18 15 12 9 6 15"></polyline>
                </svg>
            </button>
            <button
                class="mason-block-btn"
                title="Move Down"
                data-action="move-down"
                data-block-index="{{ $block["index"] }}"
                data-total-blocks="{{ $total }}"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <title>{{ __("mason::mason.preview.move_down") }}</title>
                    <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
            </button>
        @endunless

        <button
            class="mason-block-btn"
            title="{{ __("mason::mason.preview.add") }}"
            data-action="add"
            data-focus-action="mason-add-brick"
        >
            @include("mason::components.icons.plus", ["title" => __("mason::mason.preview.add")])
        </button>
        {{-- Bricks without a form have nothing to edit --}}
        @if ($block["editable"] ?? true)
            <button class="mason-block-btn" title="Edit" data-action="edit">
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    class="size-5"
                >
                    <title>{{ __("mason::mason.preview.edit") }}</title>
                    <path
                        d="m5.433 13.917 1.262-3.155A4 4 0 0 1 7.58 9.42l6.92-6.918a2.121 2.121 0 0 1 3 3l-6.92 6.918c-.383.383-.84.685-1.343.886l-3.154 1.262a.5.5 0 0 1-.65-.65Z"
                    />
                    <path
                        d="M3.5 5.75c0-.69.56-1.25 1.25-1.25H10A.75.75 0 0 0 10 3H4.75A2.75 2.75 0 0 0 2 5.75v9.5A2.75 2.75 0 0 0 4.75 18h9.5A2.75 2.75 0 0 0 17 15.25V10a.75.75 0 0 0-1.5 0v5.25c0 .69-.56 1.25-1.25 1.25h-9.5c-.69 0-1.25-.56-1.25-1.25v-9.5Z"
                    />
                </svg>
            </button>
        @endif

        @if ($block["removalLocked"] ?? false)
            <button
                class="mason-block-btn"
                title="{{ $block["hidden"] ?? false ? __("mason::mason.preview.show") : __("mason::mason.preview.hide") }}"
                data-action="toggle-hidden"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    class="size-5"
                >
                    <title>
                        {{ $block["hidden"] ?? false ? __("mason::mason.preview.show") : __("mason::mason.preview.hide") }}
                        
                    </title>
                    @if ($block["hidden"] ?? false)
                        <path
                            d="M10 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z"
                        />
                        <path
                            fill-rule="evenodd"
                            d="M.664 10.59a1.651 1.651 0 0 1 0-1.186A10.004 10.004 0 0 1 10 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0 1 10 17c-4.257 0-7.893-2.66-9.336-6.41ZM14 10a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z"
                            clip-rule="evenodd"
                        />
                    @else
                        <path
                            fill-rule="evenodd"
                            d="M3.28 2.22a.75.75 0 0 0-1.06 1.06l14.5 14.5a.75.75 0 1 0 1.06-1.06l-1.745-1.745a10.029 10.029 0 0 0 3.3-4.38 1.651 1.651 0 0 0 0-1.185A10.004 10.004 0 0 0 9.999 3a9.956 9.956 0 0 0-4.744 1.194L3.28 2.22ZM7.752 6.69l1.092 1.092a2.5 2.5 0 0 1 3.374 3.373l1.091 1.092a4 4 0 0 0-5.557-5.557Z"
                            clip-rule="evenodd"
                        />
                        <path
                            d="m10.748 13.93 2.523 2.523a9.987 9.987 0 0 1-3.27.547c-4.258 0-7.894-2.66-9.337-6.41a1.651 1.651 0 0 1 0-1.186A10.007 10.007 0 0 1 2.839 6.02L6.07 9.252a4 4 0 0 0 4.678 4.678Z"
                        />
                    @endif
                </svg>
            </button>
        @else
            <button class="mason-block-btn" title="Delete" data-action="delete">
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    class="size-5"
                >
                    <title>{{ __("mason::mason.preview.delete") }}</title>
                    <path
                        fill-rule="evenodd"
                        d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z"
                        clip-rule="evenodd"
                    />
                </svg>
            </button>
        @endif
    </div>
    <div class="mason-block-content">
        {!! $block["html"] ?? "" !!}
    </div>
</div>
