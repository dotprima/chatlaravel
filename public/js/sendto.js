function sendAudioToServer(sampleRate, channelBuffers) {
    // Convert audio buffer to WAV format
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
    fetch('/send-audio', {
        method: 'POST',
        body: formData,
        headers: {
            'X-CSRF-TOKEN': csrfToken // Include CSRF token here
        }
    })
        .then(response => response.blob())
        .then(audioBlob => {
            const audioUrl = URL.createObjectURL(audioBlob);
            playAudioResponse(audioUrl);
            updateStatus('idle');
            togglePulse(false); // Hide pulse animation
        })
        .catch(error => {
            console.error('Error sending audio to server:', error);
            updateStatus('idle');
            togglePulse(false); // Hide pulse animation
        });
}