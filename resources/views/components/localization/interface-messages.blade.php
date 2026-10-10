@php
    $interfaceMessages = [];
    foreach (app(\App\Modules\Localization\Services\InterfaceTranslationCatalog::class)->javascriptPhrases() as $phrase) {
        $interfaceMessages[$phrase] = __($phrase);
    }
@endphp
<script type="application/json" data-player-interface-messages>{!! json_encode($interfaceMessages, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) !!}</script>
