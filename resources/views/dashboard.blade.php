@extends('layouts.wrapper')

@section('css')
    <!-- Select2 CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
@endsection

@section('content')
    <!-- Fixed Select Menu Container -->
    <div class="fixed-select-container">
        <label for="channel-select" class="sr-only">Select Channel</label>
        <select id="channel-select" name="channel" class="w-full" required>
            <option value="google_tts">Google Voice</option>
            <option value="chatgpt">Riska Voice</option>
        </select>

        <!-- Logout Form -->
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="mt-2 modal-button">Logout</button>
        </form>
    </div>


    <!-- Video Background -->
    <div class="video-wrapper">
        <video src="{{ asset('assets/agent.mp4') }}" muted autoplay loop></video>
    </div>

    <!-- Split View Container -->
    <div class="split-view">
        <!-- Main Content Area -->
        <div class="main-content">
            <!-- Lottie Animation -->
            <div id="lottie-container" class="hidden mb-4">
                <!-- Lottie Animation will load here -->
            </div>

            <!-- Input Chat and Top Buttons -->
            <div class="top-buttons w-full flex justify-center mb-4" style="display: none">
                <button type="button" id="open-portal" class="px-4 py-2">
                    Portal
                </button>
                <button type="button" id="open-pa-cirebon" class="px-4 py-2">
                    PA Cirebon
                </button>
            </div>

            <form id="chat-form"
                class="mt-8 rounded-full bg-neutral-200/80 dark:bg-neutral-800/80 flex items-center w-full max-w-3xl">
                <input id="chat-input" type="text" class="bg-transparent focus:outline-none p-4 w-full" required
                    placeholder="Tolong bukan Halaman Portal" />
                <button type="submit" id="submitButton"
                    class="p-4 bg-gradient-to-r from-blue-500 to-purple-700 rounded-full flex items-center">
                    Submit
                    <span id="submit-spinner" class="spinner hidden"></span>
                </button>
            </form>

            <!-- Status Indicator -->
            <div class="text-center mt-3 flex items-center justify-center">
                <span id="status" class="text-xl font-semibold">Start talking to chat.</span>
                <span id="text-submit-spinner" class="spinner hidden ml-2"></span>
            </div>

            <!-- Pulse animation for speaking status -->
            <div id="pulse-container" class="text-center hidden">
                <div class="pulse"></div>
            </div>

            <!-- Chat History -->
            <div class="text-neutral-400 dark:text-neutral-600 pt-4 text-center max-w-xl min-h-28 space-y-4">
                <div id="chat-history"></div>
            </div>
        </div>

        <!-- Iframe Area -->
        <div class="iframe-container" id="iframe-container">
            <button id="close-iframe" class="modal-button"
                style="position: absolute; top: 10px; right: 10px; z-index: 10;">Close</button>
            <iframe id="split-iframe" src=""></iframe>
        </div>
    </div>

    <!-- Audio Player for Responses -->
    <audio id="responseAudio"></audio>
@endsection

@section('js')
    <script src="{{asset('js/main.js')}}"></script>
@endsection
