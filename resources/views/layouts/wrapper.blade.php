<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'Laravel') }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- ONNX Runtime for Web -->
    <script src="https://cdn.jsdelivr.net/npm/onnxruntime-web/dist/ort.js"></script>
    <script src="{{ asset('js/convert.js') }}"></script>
    <!-- VAD.js -->
    <script src="https://cdn.jsdelivr.net/npm/@ricky0123/vad-web@0.0.7/dist/bundle.min.js"></script>
    <style>
        /* Ensure the wrapper covers the entire viewport */
        .video-wrapper {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100vh;
            overflow: hidden;
            z-index: -1;
        }

        video {
            position: absolute;
            top: 50%;
            left: 50%;
            width: auto;
            height: 100%;
            min-width: 100%;
            min-height: 100%;
            transform: translate(-50%, -50%);
            object-fit: cover;
            object-position: center;
        }

        /* Adjustments for landscape and portrait orientations */
        @media (orientation: portrait) {
            video {
                width: 100%;
                height: auto;
            }
        }

        @media (orientation: landscape) {
            video {
                width: auto;
                height: 100%;
            }
        }

        .pulse {
            display: inline-block;
            width: 20px;
            height: 20px;
            background-color: #3182ce;
            border-radius: 50%;
            animation: pulse-animation 1.5s infinite ease-in-out;
        }

        @keyframes pulse-animation {
            0% {
                transform: scale(1);
                opacity: 1;
            }

            100% {
                transform: scale(2);
                opacity: 0;
            }
        }

        /* Styling for Lottie */
        #lottie-container {
            width: 150px;
            height: 150px;
            margin-bottom: 1rem;
        }

        /* Hidden class */
        .hidden {
            display: none;
        }

        .mb-4 {
            margin-bottom: 1rem;
        }

        /* Loading Spinner */
        .spinner {
            border: 4px solid rgba(0, 0, 0, 0.1);
            width: 24px;
            height: 24px;
            border-radius: 50%;
            border-left-color: #3182ce;
            animation: spin 1s linear infinite;
            display: inline-block;
            vertical-align: middle;
            margin-left: 8px;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Disabled state for input */
        .disabled {
            background-color: #e2e8f0;
            cursor: not-allowed;
        }

        /* Button Styling */
        .modal-button,
        .top-buttons button {
            padding: 0.5rem 1rem;
            margin-right: 0.5rem;
            background-color: #3182ce;
            color: white;
            border: none;
            border-radius: 0.375rem;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .modal-button:hover,
        .top-buttons button:hover {
            background-color: #2b6cb0;
        }

        /* Iframe Styling */
        #split-iframe {
            width: 100%;
            height: 100%;
            border: none;
        }

        /* Split View Container */
        .split-view {
            display: flex;
            flex-direction: row;
            width: 100%;
            height: 100vh;
        }

        /* Main Content Area */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 2rem;
            overflow-y: auto;
            margin-top: 400px;
        }

        /* Iframe Area */
        .iframe-container {
            flex: 1;
            border-left: 2px solid #ccc;
            display: none;
            /* Hidden by default */
            position: relative;
        }

        /* Responsive adjustments for split view */
        @media (max-width: 768px) {
            .split-view {
                flex-direction: column;
            }

            .iframe-container {
                border-left: none;
                border-top: 2px solid #ccc;
                height: 50vh;
                display: none;
                /* Hidden by default */
            }
        }

        /* Chat Container */
        .chat-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 100;
            width: 100%;
            max-width: 400px;
            padding: 1rem;
        }

        /* Responsive adjustments for chat container */
        @media (max-width: 768px) {
            .chat-container {
                right: 10px;
                left: 10px;
                max-width: none;
            }
        }

        /* Show iframe container when active */
        .iframe-container.active {
            display: block;
        }

        /* Select2 Container Styling */
        .select2-container {
            width: 200px !important;
        }

        .fixed-select-container {
            position: fixed;
            top: 20px;
            left: 20px;
            /* Ganti dari right ke left */
            z-index: 101;
            background: rgba(255, 255, 255, 0.9);
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        /* Responsive adjustments for fixed select */
        @media (max-width: 768px) {
            .fixed-select-container {
                top: 10px;
                left: 10px;
                /* Ganti dari right ke left */
                width: calc(100% - 20px);
                box-sizing: border-box;
            }

            .select2-container {
                width: 100% !important;
            }
        }
    </style>
    @yield('css')
</head>

<body>

 

    @yield('content')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"
        integrity="sha512-v2CJ7UaYy4JwqLDIrZUI/4hqeoQieOmAZNXBeQyjo21dadnwR+8ZaIJVT8EE2iyI61OV8e6M8PP2/4hpQINQ/g=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <!-- Select2 JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>

    <!-- Lottie Animation Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bodymovin/5.7.13/lottie.min.js"></script>
    @yield('js')
   

</body>

</html>
