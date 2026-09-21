@php
    /**
     * Print document layout.
     *
     * One layout for every artifact and every engine. The paper geometry, the
     * `@page` rule and the design tokens all arrive inside the document model,
     * so the SPA preview, the browser print, DomPDF and headless Chromium are
     * laying out the same page — there is no per-engine template to drift.
     */
    $paper = $document['paper'];
    $render = $document['render'];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $document['documentKey'] }} — {{ $document['title'] }}</title>
    <style>
        {!! $document['tokensCss'] !!}
        {!! $document['pageCss'] !!}
        {!! $document['documentCss'] !!}
    </style>
</head>
<body class="pd-doc pd-{{ $paper }}">
    <div class="pd-paper">
        <div class="pd-body">
            @include('print.bodies.'.$document['artifact'], ['document' => $document])
        </div>
    </div>
    @include('print.partials.pagefoot', ['document' => $document])
</body>
</html>
