<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    public function processVoiceToResponse(Request $request)
    {
        try {
            // Initialize variables
            $voiceBuffer = null;
            $responseText = null;

            // Check if the request contains an audio file
            if ($request->hasFile('audio')) {
                // Handle audio input

                // Retrieve the uploaded audio file
                $audioFile = $request->file('audio');

                // 1. Process Speech-to-Text (from audio buffer to text)
                $transcript = $this->speechToText($audioFile);

                // 2. Process Chat Completion (from text to OpenAI answer)
                $completionText = $this->chatCompletion($transcript);

                // 3. Process Text-to-Speech (from answer to voice buffer)
                $voiceBuffer = $this->textToSpeech($completionText);

                $responseText = $completionText;
            }
            // Check if the request contains a text message
            elseif ($request->has('message')) {
                // Handle text input

                // Retrieve the text message
                $message = $request->input('message');

                // 1. Process Chat Completion (from text to OpenAI answer)
                $completionText = $this->chatCompletion($message);

                // 2. Process Text-to-Speech (from answer to voice buffer)
                $voiceBuffer = $this->textToSpeech($completionText);

                $responseText = $completionText;
            }
            else {
                // No valid input provided
                return response()->json(['error' => 'No input provided'], 400);
            }

            // Ensure that voiceBuffer has been generated
            if (!$voiceBuffer) {
                return response()->json(['error' => 'Failed to generate audio response'], 500);
            }

            // Encode the audio buffer to Base64
            $voiceBase64 = base64_encode($voiceBuffer);

            // Return the response text and audio data
            return response()->json([
                'response_text' => $responseText,
                'response_audio_base64' => $voiceBase64
            ], 200);
            
        } catch (\Exception $e) {
            // Log the error for debugging purposes
            Log::error('Error in processVoiceToResponse: ' . $e->getMessage());

            // Return a generic error message
            return response()->json(['error' => 'Internal Server Error'], 500);
        }
    }
    

    private function speechToText($audioBuffer)
    {
        try {
            $filePath = $audioBuffer->store('public/uploads');
            // Maksimum ukuran file yang didukung oleh OpenAI API (25 MB)
            $maxFileSize = 25 * 1024 * 1024; // 25 MB
    
           
    
            $fileMimeType = $audioBuffer->getMimeType();
            $fileSize = $audioBuffer->getSize();
    
            // Validasi ukuran file
            if ($fileSize > $maxFileSize) {
                throw new \Exception('File size exceeds the 25 MB limit. Current file size: ' . $fileSize . ' bytes.');
            }
    
            
    
            Log::info('File size: ' . $fileSize);
            Log::info('File mime type: ' . $fileMimeType);
    
            $client = new Client();
    
            // Kirim buffer ke Whisper API untuk transkripsi
            $response = $client->post('https://api.openai.com/v1/audio/transcriptions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
                ],
                'multipart' => [
                    [
                        'name'     => 'file',
                        'contents' => fopen($audioBuffer->getPathname(), 'r'), // Pastikan file path benar
                        'filename' => $audioBuffer->getClientOriginalName(),    // Pastikan filename diatur
                    ],
                    [
                        'name'     => 'model',
                        'contents' => 'whisper-1', // Model Whisper yang digunakan
                    ]
                ]
            ]);
    
            $result = json_decode($response->getBody()->getContents(), true);
    
            // Kembalikan teks hasil transkripsi, atau error jika gagal
            return $result['text'] ?? 'Error: Could not transcribe audio';
    
        } catch (\Exception $e) {
            // Catat error di log dan lempar exception
            Log::error('Error in speechToText: ' . $e->getMessage());
            throw new \Exception('Failed to transcribe audio');
        }
    }
    



    // Fungsi untuk Chat Completion (input teks, output teks jawaban)
    private function chatCompletion($transcript)
    {
        try {
            $client = new Client();

            // Kirim teks ke OpenAI Chat API untuk menghasilkan respons
            $response = $client->post('https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-4o-mini', // Gunakan model GPT-4 atau yang sesuai
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are a helpful assistant.',
                        ],
                        [
                            'role' => 'user',
                            'content' => $transcript,
                        ]
                    ]
                ]
            ]);

            $result = json_decode($response->getBody()->getContents(), true);
            return $result['choices'][0]['message']['content'] ?? 'Error: Could not generate response';
        } catch (\Exception $e) {
            Log::error('Error in chatCompletion: ' . $e->getMessage());
            throw new \Exception('Failed to generate response from ChatGPT');
        }
    }

    // Fungsi untuk Text-to-Speech (input teks, output buffer audio)
    private function textToSpeech($responseText)
    {
        try {
            $client = new Client();

            // Kirim teks ke OpenAI Text-to-Speech API untuk menghasilkan audio
            $response = $client->post('https://api.openai.com/v1/audio/speech', [
                'headers' => [
                    'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model' => 'tts-1', // Gunakan model TTS dari OpenAI
                    'input' => $responseText,
                    'voice' => 'alloy', // Suara yang digunakan
                ]
            ]);

            // Kembalikan buffer audio dari respons
            return $response->getBody()->getContents();
        } catch (\Exception $e) {
            Log::error('Error in textToSpeech: ' . $e->getMessage());
            throw new \Exception('Failed to generate audio from text');
        }
    }
}
