function audioBufferToWav(sampleRate, channelBuffers) {
    const totalSamples = channelBuffers[0].length * channelBuffers.length;
    const buffer = new ArrayBuffer(44 + totalSamples * 2);
    const view = new DataView(buffer);

    const writeString = (view, offset, string) => {
        for (let i = 0; i < string.length; i++) {
            view.setUint8(offset + i, string.charCodeAt(i));
        }
    };

    /* RIFF header */
    writeString(view, 0, "RIFF");
    view.setUint32(4, 36 + totalSamples * 2, true);
    writeString(view, 8, "WAVE");
    writeString(view, 12, "fmt ");
    view.setUint32(16, 16, true);
    view.setUint16(20, 1, true);
    view.setUint16(22, channelBuffers.length, true);
    view.setUint32(24, sampleRate, true);
    view.setUint32(28, sampleRate * channelBuffers.length * 2, true);
    view.setUint16(32, channelBuffers.length * 2, true);
    view.setUint16(34, 16, true);
    writeString(view, 36, "data");
    view.setUint32(40, totalSamples * 2, true);

    let offset = 44;
    for (let i = 0; i < channelBuffers[0].length; i++) {
        for (let channel = 0; channel < channelBuffers.length; channel++) {
            const s = Math.max(-1, Math.min(1, channelBuffers[channel][i]));
            view.setInt16(offset, s < 0 ? s * 0x8000 : s * 0x7fff, true);
            offset += 2;
        }
    }

    return buffer;
}