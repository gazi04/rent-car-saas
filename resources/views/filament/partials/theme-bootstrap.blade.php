{{--
    Replaces Filament's refused inline dark-mode bootstrap with an external,
    self-hosted equivalent. Emitted at PanelsRenderHook::HEAD_END — after the
    panel's stylesheets, still inside <head>, so it runs before the body paints.

    Deliberately NOT async/defer/module: each of those would push execution past
    first paint and reintroduce the flash this exists to remove.
--}}
<script
    src="{{ \Filament\Support\Facades\FilamentAsset::getScriptSrc('theme-bootstrap') }}"
    data-default-theme-mode="{{ filament()->getDefaultThemeMode()->value }}"
></script>
