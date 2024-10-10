document.addEventListener('DOMContentLoaded', function () {
    // Initialize Select2
    $('#channel-select').select2({
        placeholder: "Select a channel",
        minimumResultsForSearch: Infinity // Hide the search box
    }).on('change', function () {
        // Optional: Handle channel change if needed
        console.log('Selected channel:', $(this).val());
    });
});

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

// Elements for split view
const iframeContainer = document.getElementById('iframe-container');
const splitIframe = document.getElementById('split-iframe');

// Load Lottie animation on page load
window.addEventListener('load', function () {
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

    // Ensure Lottie container is hidden initially
    lottieContainer.classList.add('hidden');

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
            redemptionFrames: 30,
            onSpeechEnd: (audio) => {
                console.log('VAD Event: Speech ended');
                if (isSubmitting) {
                    console.log('VAD Event: Submission in progress, ignoring speech end');
                    return; // Prevent if already submitting
                }

                // Handle when speech ends
                const sampleRate = 16000; // Fixed sample rate
                const channelBuffers = [audio]; // Wrap audio as channelBuffers
                togglePulse(false); // Hide pulse animation
                toggleLottie(false); // Hide Lottie animation when voice is detected
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

    // Get the selected channel value
    const channelSelect = document.getElementById('channel-select');
    const selectedChannel = channelSelect.value || 'chatgpt'; // Default to 'chatgpt' if not selected
    formData.append('channel', selectedChannel);

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
            toggleLottie(false); // Ensure Lottie is hidden
            toggleUIState(false);
            isSubmitting = false; // Reset flag
        });
}

// Event listener for text form submission
chatForm.addEventListener('submit', function (e) {
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

    // Get the selected channel value
    const channelSelect = document.getElementById('channel-select');
    const selectedChannel = channelSelect.value || 'chatgpt'; // Default to 'chatgpt' if not selected
    formData.append('channel', selectedChannel);

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
            isSubmitting = false; // Reset flag
        });
});

// Function to play multiple audio responses one after another
function playMultipleAudioResponses(base64AudioArray) {
    let currentAudioIndex = 0; // Track the current audio being played
    const audioPlayer = document.getElementById('responseAudio'); // Audio player element

    // Function to handle the end of the audio
    function handleAudioEnd() {
        currentAudioIndex++; // Move to the next audio
        if (currentAudioIndex < base64AudioArray.length) {
            playNextAudio(); // Play the next audio if available
        } else {
            console.log('All audio responses have been played');
            // Call any final function after all audios are played
            handleFinalAudioEnd();
        }
    }

    // Function to play the next audio
    function playNextAudio() {
        const base64Audio = base64AudioArray[currentAudioIndex];
        console.log(`Playing audio ${currentAudioIndex + 1} of ${base64AudioArray.length}`);

        // Decode Base64 to binary
        const binaryString = window.atob(base64Audio);
        const len = binaryString.length;
        const bytes = new Uint8Array(len);
        for (let i = 0; i < len; i++) {
            bytes[i] = binaryString.charCodeAt(i);
        }

        // Create Blob from bytes
        const blob = new Blob([bytes], {
            type: 'audio/mpeg'
        });

        // Create URL for Blob
        const audioUrl = URL.createObjectURL(blob);
        console.log('Audio URL:', audioUrl);

        // Set the audio source
        audioPlayer.src = audioUrl;

        // Play audio

        if (audioPlayer) {
            audioPlayer.play()
                .then(() => {
                    console.log('Audio playback started');
                    // Mainkan video saat audio mulai
                    agentVideo.play();
                    agentVideo.loop = true;
                })
                .catch(error => {
                    console.error('Audio playback failed:', error);
                    updateStatus('idle');
                    togglePulse(false); // Hide pulse animation
                    toggleLottie(false);
                    toggleUIState(false);
                    isSubmitting = false;
                });
        }

        // Remove any previous 'ended' event listener and add a new one
        audioPlayer.removeEventListener('ended', handleAudioEnd);
        audioPlayer.addEventListener('ended', handleAudioEnd);
    }

    // Function to handle final state after all audio has played
    function handleFinalAudioEnd() {
        console.log('Audio playback ended');
        agentVideo.pause();
        agentVideo.currentTime = 0; // Reset video to second 0

        // Setelah audio berakhir, reset status
        updateStatus('idle');
        togglePulse(false); // Hide pulse animation
        toggleLottie(false); // Ensure Lottie is hidden
        toggleUIState(false);
        isSubmitting = false;

        console.log('Submission completed, restarting VAD');

        // Restart VAD jika diperlukan
        if (myvad && !myvad.isRunning) {
            myvad.start();
            isRecording = true;
            agentVideo.loop = false;
            console.log('VAD: Restarted recording');
        }
    }

    // Start playing the first audio
    playNextAudio();
}

// Handle server response for both text and voice
function handleServerResponse(data) {
    console.log('Server Response:', data);

    // Check for error
    if (data.error) {
        console.error('Server Error:', data.error);
        updateStatus('error');
        togglePulse(false); // Ensure pulse is hidden
        toggleLottie(false); // Ensure Lottie is hidden
        toggleUIState(false);
        isSubmitting = false;
        return;
    }

    // Extract response_text and response_audio_base64
    const responseText = data.response_text;
    const responseAudioBase64 = data.response_audio_base64;
    const questionText = data.question_text;
    const description_voice = data.description_voice;
    const answer_action_voice = data.answer_action_voice;

    // Try parsing responseText as JSON
    let jsonResponse = null;
    try {
        jsonResponse = JSON.parse(responseText);
    } catch (e) {
        // responseText is not JSON, proceed as plain text
        console.log('Response text is not JSON.');
    }

    if (jsonResponse && jsonResponse.action === "open_link" && jsonResponse.url) {

        // Example usage:
        if (answer_action_voice || description_voice) {
            const audioQueue = [];
            if (answer_action_voice) {
                audioQueue.push(answer_action_voice); // Add first audio to queue
            }
            if (description_voice) {
                audioQueue.push(description_voice); // Add second audio to queue
            }

            // Play audios one after another
            playMultipleAudioResponses(audioQueue);
        }

        if (jsonResponse.url === 'https://gugatanmandiri.badilag.net' || jsonResponse.url ===
            'https://ecourt.mahkamahagung.go.id' || jsonResponse.url === 'https://siwas.mahkamahagung.go.id/' || jsonResponse.url === 'https://cctv.badilag.net/display/satker/0ffcf8e23a6be649bdc0113ad7ef004e') {
            window.open(jsonResponse.url, '_blank'); // Membuka tab baru
        } else {
            // Close existing iframe if open
            closeIframe();

            // Open the new iframe
            openIframe(jsonResponse.url);
        }

        console.log('Detected action: open_link');



        updateStatus('idle');
        togglePulse(false); // Hide pulse animation
        toggleLottie(false); // Ensure Lottie is hidden
        toggleUIState(false);
        isSubmitting = false;

        // No need to display text message or play audio
    }
    if (jsonResponse && jsonResponse.action === "close_link") {
        document.getElementById('close-iframe').click();
        // Example usage:
        if (answer_action_voice || description_voice) {
            const audioQueue = [];
            if (answer_action_voice) {
                audioQueue.push(answer_action_voice); // Add first audio to queue
            }
            if (description_voice) {
                audioQueue.push(description_voice); // Add second audio to queue
            }

            // Play audios one after another
            playMultipleAudioResponses(audioQueue);
        }



        updateStatus('idle');
        togglePulse(false); // Hide pulse animation
        toggleLottie(false); // Ensure Lottie is hidden
        toggleUIState(false);
        isSubmitting = false;

    } else {
        // Regular response
        if (questionText) {
            document.getElementById('chat-input').value = questionText;
        }

        // Display response text in chat history
        if (responseText) {
            appendChatMessage('Agent', responseText);
        }

        // Play response audio if available
        if (responseAudioBase64) {
            playAudioResponse(responseAudioBase64);
        } else {
            // Jika tidak ada audio, pastikan status di-reset
            updateStatus('idle');
            togglePulse(false); // Hide pulse animation
            toggleLottie(false); // Ensure Lottie is hidden
            toggleUIState(false);
            isSubmitting = false;
        }
    }

    console.log('Submission completed, waiting for audio to finish');
}

// Function to open iframe with URL
function openIframe(url) {
    splitIframe.src = url;
    iframeContainer.classList.add('active');
    console.log('Iframe opened with URL:', url);
}

// Function to close iframe
function closeIframe() {
    splitIframe.src = '';
    iframeContainer.classList.remove('active');
    console.log('Iframe closed.');
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

    // Create Blob from bytes
    const blob = new Blob([bytes], {
        type: 'audio/mpeg'
    });

    // Create URL for Blob
    const audioUrl = URL.createObjectURL(blob);
    console.log('Audio URL:', audioUrl);

    // Play audio
    const audioPlayer = document.getElementById('responseAudio');

    // Remove any existing 'ended' event listeners to prevent multiple triggers
    audioPlayer.removeEventListener('ended', handleAudioEnd);

    // Define the handler function
    function handleAudioEnd() {
        console.log('Audio playback ended');
        agentVideo.pause();
        agentVideo.currentTime = 0; // Reset video to second 0

        // Setelah audio berakhir, reset status
        updateStatus('idle');
        togglePulse(false); // Hide pulse animation
        toggleLottie(false); // Ensure Lottie is hidden
        toggleUIState(false);
        isSubmitting = false;

        console.log('Submission completed, restarting VAD');

        // Restart VAD jika diperlukan
        if (myvad && !myvad.isRunning) {
            myvad.start();
            isRecording = true;
            agentVideo.loop = false;
            console.log('VAD: Restarted recording');
        }

        // Remove the event listener after it has been handled
        audioPlayer.removeEventListener('ended', handleAudioEnd);
    }

    // Attach the event listener
    audioPlayer.addEventListener('ended', handleAudioEnd);

    // Set the audio source and play
    audioPlayer.src = audioUrl;

    audioPlayer.play()
        .then(() => {
            console.log('Audio playback started');
            // Mainkan video saat audio mulai
            agentVideo.play();
            agentVideo.loop = true;
        })
        .catch(error => {
            console.error('Audio playback failed:', error);
            updateStatus('idle');
            togglePulse(false); // Hide pulse animation
            toggleLottie(false);
            toggleUIState(false);
            isSubmitting = false;
        });
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

        // Restart VAD jika diperlukan
        if (myvad && myvad.start && !isRecording) {
            myvad.start();
            isRecording = true;
            console.log('VAD: Restarted after submission');
        }
    }
}

// Event listeners for top buttons to open iframe
document.getElementById('open-portal').addEventListener('click', () => {
    openIframe('https://portal.pa-cirebon.go.id/');
});

document.getElementById('open-pa-cirebon').addEventListener('click', () => {
    openIframe('https://pa-cirebon.go.id/');
});

// Event listener for close iframe button
document.getElementById('close-iframe').addEventListener('click', () => {
    closeIframe();
});