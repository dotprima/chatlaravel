<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Talk with Robot</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- ONNX Runtime for Web -->
    <script src="https://cdn.jsdelivr.net/npm/onnxruntime-web/dist/ort.js"></script>
    <script src="{{ asset('js/convert.js') }}"></script>
    <!-- VAD.js -->
    <script src="https://cdn.jsdelivr.net/npm/@ricky0123/vad-web@0.0.7/dist/bundle.min.js"></script>
    @yield('css')
</head>

<body>

 

    @yield('content')
    @yield('js')
   

</body>

</html>
