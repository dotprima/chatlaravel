@extends('layouts.wrapper')

@section('css')
    <style>
        /* Ensure the wrapper covers the entire viewport */
        .video-wrapper {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100vh;
            /* Use viewport height */
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
            /* Adjust size as needed */
            height: 150px;
            margin-bottom: 1rem;
            /* Space below */
        }

        /* Hidden class */
        .hidden {
            display: none;
        }

        .mb-4 {
            margin-bottom: 1rem;
            /* Adjust if needed */
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

        /* Additional styling for modal buttons */
        .modal-button {
            padding: 0.5rem 1rem;
            margin-right: 0.5rem;
            background-color: #3182ce;
            color: white;
            border: none;
            border-radius: 0.375rem;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .modal-button:hover {
            background-color: #2b6cb0;
        }

        /* Iframe Styling */
        #modal-iframe {
            width: 100%;
            height: 600px;
            /* Increased from 500px */
            border: none;
            margin-top: 1rem;
        }

        /* Responsive adjustments for iframe */
        @media (max-width: 768px) {
            #modal-iframe {
                height: 300px;
            }
        }

        /* Button Styling Above Textfield */
        .top-buttons {
            display: flex;
            gap: 1rem;
        }

        .top-buttons button {
            padding: 0.75rem 1.5rem;
            background-color: #3182ce;
            color: white;
            border: none;
            border-radius: 9999px;
            cursor: pointer;
            transition: background-color 0.3s;
            font-size: 1rem;
        }

        .top-buttons button:hover {
            background-color: #2b6cb0;
        }

        .chat-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 100;
            /* Lebih tinggi dari modal */
            width: 100%;
            max-width: 400px;
            padding: 1rem;
        }

        /* Responsif untuk ukuran layar kecil */
        @media (max-width: 768px) {
            .chat-container {
                right: 10px;
                left: 10px;
                max-width: none;
            }
        }
    </style>
@endsection

@section('content')
    <!-- Video Background -->
    <div class="video-wrapper">
        <video src="{{ asset('assets/agent.mp4') }}" muted autoplay loop></video>
    </div>


    <!-- Modal Section -->
    <div id="modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden">
        <div class="bg-white max-w-6xl w-full mx-auto p-6 rounded-lg relative">
            <!-- Close Button -->
            <button id="close-modal" class="text-red-600 text-2xl font-bold absolute top-4 right-4">&times;</button>

            <!-- Modal Buttons -->
            <div class="flex justify-center space-x-4">
                <button class="modal-button" data-url="https://portal.pa-cirebon.go.id/">Portal</button>
                <button class="modal-button" data-url="https://pa-cirebon.go.id/">PA Cirebon</button>
            </div>

            <!-- Iframe to Display Websites -->
            <iframe id="modal-iframe" src=""></iframe>
        </div>
    </div>


    <!-- Main Container -->
    <div class="container mx-auto flex flex-col items-center justify-center min-h-screen p-4">
        <!-- Lottie Animation -->
        <div id="lottie-container" class="hidden mb-4">
            <!-- Lottie Animation will load here -->
        </div>

        <!-- Input Chat and Top Buttons -->
        <div class="top-buttons w-full flex justify-center">
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
                placeholder="Ask me anything" />
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

    <!-- Audio Player for Responses -->
    <audio id="responseAudio"></audio>



    <!-- Script to handle modal functionality -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const modal = document.getElementById('modal');
            const closeModalButton = document.getElementById('close-modal');
            const modalButtons = document.querySelectorAll('.modal-button');
            const modalIframe = document.getElementById('modal-iframe');

            // Function to open modal with specified URL
            function openModal(url) {
                modalIframe.src = url;
                modal.classList.remove('hidden');
            }

            // Add event listeners to modal buttons inside the modal
            modalButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const url = button.getAttribute('data-url');
                    openModal(url);
                });
            });

            // Close modal when clicking the close button
            closeModalButton.addEventListener('click', () => {
                modal.classList.add('hidden');
                modalIframe.src = ''; // Clear iframe src when closing
            });

            // Close modal when clicking outside the modal content
            window.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.classList.add('hidden');
                    modalIframe.src = ''; // Clear iframe src when closing
                }
            });

            // New Buttons to open modal with specific URLs
            const openPortalButton = document.getElementById('open-portal');
            const openPaCirebonButton = document.getElementById('open-pa-cirebon');

            openPortalButton.addEventListener('click', () => {
                openModal('https://portal.pa-cirebon.go.id/');
            });

            openPaCirebonButton.addEventListener('click', () => {
                openModal('https://pa-cirebon.go.id/');
            });
        });
    </script>
@endsection


@section('js')
    <!-- Lottie Animation Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bodymovin/5.7.13/lottie.min.js"></script>


    <script>
        let isRecording = false;
        let isSubmitting = false; // Flag to prevent double submissions
        let myvad;
        const agentVideo = document.querySelector('video');
        let lottieAnimation;
        const statusElement = document.getElementById('status'); // Status Text Element

        // Elements for UI feedback
        const chatForm = document.getElementById('chat-form');
        const chatInput = document.getElementById('chat-input');
        const submitButton = document.getElementById('submitButton');
        const submitSpinner = document.getElementById('submit-spinner');
        const textSubmitSpinner = document.getElementById('text-submit-spinner');

        // Load Lottie animation on page load
        window.addEventListener('load', function() {
            // Lottie animation setup
            const lottieContainer = document.getElementById('lottie-container');

            // Initialize Lottie
            lottieAnimation = lottie.loadAnimation({
                container: lottieContainer, // the dom element that will contain the animation
                renderer: 'svg',
                loop: true,
                autoplay: false,
                path: '/assets/speak.json' // path to your animation file
            });

            // Stop video at the start
            agentVideo.pause();
            agentVideo.currentTime = 0;

            // Start VAD
            startVAD();
        });

        // VAD initialization
        async function startVAD() {
            try {
                updateStatus('loading');
                myvad = await vad.MicVAD.new({
                    positiveSpeechThreshold: 0.9,
                    minSpeechFrames: 4,
                    redemptionFrames: 5,
                    onSpeechEnd: (audio) => {
                        console.log('VAD Event: Speech ended');
                        if (isSubmitting) {
                            console.log('VAD Event: Submission in progress, ignoring speech end');
                            return; // Prevent if already submitting
                        }

                        // Handle when speech ends
                        const sampleRate = 16000; // Fixed sample rate
                        const channelBuffers = [audio]; // Wrap audio as channelBuffers
                        togglePulse(false); // Show pulse animation
                        toggleLottie(false); // Show Lottie animation when voice is detected
                        // Send audio to server
                        submitVoice(channelBuffers, sampleRate);
                    },
                    onSpeechStart: () => {
                        console.log('VAD Event: Speech started');
                        if (isSubmitting) {
                            console.log('VAD Event: Submission in progress, ignoring speech start');
                            return; // Prevent if already submitting
                        }
                        updateStatus('talking');
                        togglePulse(true); // Show pulse animation
                        toggleLottie(true); // Show Lottie animation when voice is detected
                    }
                });

                myvad.start();
                isRecording = true;
                updateStatus('talking');
                console.log('VAD: Started recording');
            } catch (error) {
                updateStatus('error');
                console.error("VAD Error: Failed to load speech detection:", error);
            }
        }

        function stopVAD() {
            isRecording = false;
            updateStatus('idle');
            if (myvad) myvad.destroy();
            console.log('VAD: Stopped recording');
        }

        // Unified submission function for voice
        function submitVoice(channelBuffers, sampleRate) {

            agentVideo.pause();
            agentVideo.currentTime = 0;

            console.log('Submitting voice input');
            isSubmitting = true;
            toggleUIState(true);

            // Convert audio buffer to WAV format using audio-buffer-to-wav library
            const wavBuffer = audioBufferToWav(sampleRate, channelBuffers);

            // Create a blob from the WAV buffer
            const blob = new Blob([wavBuffer], {
                type: 'audio/wav'
            });

            // Prepare FormData for sending the audio to the server
            const formData = new FormData();
            formData.append('audio', blob, 'audio.wav');

            // Get CSRF token
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            // Send audio to server
            fetch('/send-audio', { // Unified endpoint
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': csrfToken // Include CSRF token here
                    }
                })
                .then(response => response.json())
                .then(data => {
                    handleServerResponse(data);
                })
                .catch(error => {
                    console.error('Error sending audio to server:', error);
                    updateStatus('idle');
                    togglePulse(false); // Hide pulse animation
                    toggleUIState(false);
                });
        }

        // Event listener for text form submission
        chatForm.addEventListener('submit', function(e) {
            e.preventDefault();
            if (isSubmitting) {
                console.log('Submission already in progress');
                return; // Prevent multiple submissions
            }

            const text = chatInput.value.trim();
            if (!text) return;

            console.log('Submitting text input:', text);
            isSubmitting = true;
            toggleUIState(true);

            // Prepare data to send
            const formData = new FormData();
            formData.append('message', text);

            // Get CSRF token
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            // Stop VAD to prevent voice submission during text submission
            if (myvad && myvad.stop) {
                myvad.stop();
                console.log('VAD: Stopped during text submission');
            }

            // Send text to server
            fetch('/send-audio', { // Unified endpoint
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': csrfToken // Include CSRF token here
                    }
                })
                .then(response => response.json())
                .then(data => {
                    handleServerResponse(data);
                })
                .catch(error => {
                    console.error('Error sending message to server:', error);
                    updateStatus('idle');
                    toggleUIState(false);
                });
        });

        // Handle server response for both text and voice
        // Handle server response for both text and voice
        function handleServerResponse(data) {
            console.log('Server Response:', data);

            // Cek apakah ada error
            if (data.error) {
                console.error('Server Error:', data.error);
                updateStatus('error');
                toggleUIState(false);
                isSubmitting = false;
                return;
            }

            // Ekstrak response_text dan response_audio_base64
            const responseText = data.response_text;
            const responseAudioBase64 = data.response_audio_base64;
            const questionText = data.question_text;

            // Coba parsing responseText sebagai JSON
            let jsonResponse = null;
            try {
                jsonResponse = JSON.parse(responseText);
            } catch (e) {
                // responseText bukan JSON, lanjutkan sebagai teks biasa
                console.log('Response text bukan JSON.');
            }

            if (jsonResponse && jsonResponse.action === "open_link" && jsonResponse.url) {
                console.log('Aksi terdeteksi: open_link');

                // Tutup modal jika sudah terbuka
                closeModal();

                // Buka link dalam modal
                openModal(jsonResponse.url);

                // Tidak perlu menampilkan pesan teks atau memutar audio
            } else {
                // Respons biasa
                if (questionText) {
                    document.getElementById('chat-input').value = questionText;
                }

                // Tampilkan teks respons dalam riwayat chat
                if (responseText) {
                    appendChatMessage('Agent', responseText);
                }

                // Putar audio respons jika ada
                if (responseAudioBase64) {
                    playAudioResponse(responseAudioBase64);
                }
            }

            // Reset status dan UI
            updateStatus('idle');
            togglePulse(false); // Sembunyikan animasi pulse
            toggleUIState(false);
            isSubmitting = false;

            console.log('Submission completed, restarting VAD');

            // Restart VAD jika diperlukan
            if (myvad && !myvad.isRunning) {
                myvad.start();
                isRecording = true;
                console.log('VAD: Restarted recording');
            }
        }

        // Fungsi untuk membuka modal dengan URL tertentu
        function openModal(url) {
            const modal = document.getElementById('modal');
            const modalIframe = document.getElementById('modal-iframe');

            // Set URL iframe
            modalIframe.src = url;

            // Tampilkan modal
            modal.classList.remove('hidden');
            console.log('Modal dibuka dengan URL:', url);
        }

        // Fungsi untuk menutup modal
        function closeModal() {
            const modal = document.getElementById('modal');
            const modalIframe = document.getElementById('modal-iframe');

            if (!modal.classList.contains('hidden')) {
                modal.classList.add('hidden');
                modalIframe.src = ''; // Bersihkan src iframe
                console.log('Modal ditutup.');
            }
        }

        // Function to play audio response from Base64
        function playAudioResponse(base64Audio) {
            console.log('Playing audio response');
            // Decode Base64 to binary
            const binaryString = window.atob(base64Audio);
            const len = binaryString.length;
            const bytes = new Uint8Array(len);
            for (let i = 0; i < len; i++) {
                bytes[i] = binaryString.charCodeAt(i);
            }

            // Create a Blob from the bytes
            const blob = new Blob([bytes], {
                type: 'audio/mpeg'
            });

            // Create a URL for the Blob
            const audioUrl = URL.createObjectURL(blob);
            console.log('Audio URL:', audioUrl);

            // Play the audio
            const audioPlayer = document.getElementById('responseAudio');
            audioPlayer.src = audioUrl;
            audioPlayer.play()
                .then(() => {
                    console.log('Audio playback started');
                })
                .catch(error => {
                    console.error('Audio playback failed:', error);
                });

            // Play video when the audio starts
            agentVideo.play();
            agentVideo.loop = true;

            // When audio ends, reset the video to 0 second and pause it
            audioPlayer.onended = () => {
                console.log('Audio playback ended');
                agentVideo.pause();
                agentVideo.currentTime = 0; // Reset video to 0 seconds
            };
        }

        // Function to append chat messages to history
        function appendChatMessage(sender, message) {
            const chatHistory = document.getElementById('chat-history');
            const messageDiv = document.createElement('div');
            messageDiv.textContent = `${sender}: ${message}`;
            chatHistory.appendChild(messageDiv);
            chatHistory.scrollTop = chatHistory.scrollHeight;
            console.log('Appended message:', `${sender}: ${message}`);
        }

        // Function to update status text
        function updateStatus(status) {
            if (status === 'loading') {
                statusElement.textContent = 'Loading speech detection...';
            } else if (status === 'error') {
                statusElement.textContent = 'Failed to load speech detection.';
            } else if (status === 'talking') {
                statusElement.textContent = ''; // Hide text during talking
            } else {
                statusElement.textContent = 'Start talking to chat.';
            }
            console.log('Status updated to:', status);
        }

        // Function to toggle pulse animation
        function togglePulse(show) {
            const pulseContainer = document.getElementById('pulse-container');
            if (show) {
                console.log('Pulse Animation: Show');
                pulseContainer.classList.remove('hidden');
            } else {
                console.log('Pulse Animation: Hide');
                pulseContainer.classList.add('hidden');
            }
        }

        // Function to toggle Lottie animation visibility
        function toggleLottie(show) {
            const lottieContainer = document.getElementById('lottie-container');
            if (show) {
                console.log('Lottie Animation: Show and Play');
                lottieContainer.classList.remove('hidden');
                lottieAnimation.play(); // Start playing the Lottie animation
            } else {
                console.log('Lottie Animation: Hide and Stop');
                lottieAnimation.stop(); // Stop the Lottie animation
                lottieContainer.classList.add('hidden');
            }
        }

        // Function to toggle UI state during submission
        function toggleUIState(isSubmittingNow) {
            if (isSubmittingNow) {
                console.log('UI State: Submitting');
                // Disable input and button
                chatInput.disabled = true;
                submitButton.disabled = true;
                chatInput.classList.add('disabled');
                submitButton.classList.add('disabled');

                // Show loading spinner
                submitSpinner.classList.remove('hidden');
                textSubmitSpinner.classList.remove('hidden');

                // Stop VAD to prevent voice submission
                if (myvad && myvad.stop) {
                    myvad.stop();
                    console.log('VAD: Stopped during submission');
                }
            } else {
                console.log('UI State: Idle');
                // Enable input and button
                chatInput.disabled = false;
                submitButton.disabled = false;
                chatInput.classList.remove('disabled');
                submitButton.classList.remove('disabled');

                // Hide loading spinner
                submitSpinner.classList.add('hidden');
                textSubmitSpinner.classList.add('hidden');

                // Restart VAD if necessary
                if (myvad && myvad.start && !isRecording) {
                    myvad.start();
                    isRecording = true;
                    console.log('VAD: Restarted after submission');
                }
            }
        }
    </script>
@endsection
