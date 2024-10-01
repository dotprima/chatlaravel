<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

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
                $transcript = $request->input('message');

                // 1. Process Chat Completion (from text to OpenAI answer)
                $completionText = $this->chatCompletion($transcript);



                // 2. Process Text-to-Speech (from answer to voice buffer)
                $voiceBuffer = $this->textToSpeech($completionText);

                $responseText = $completionText;
            } else {
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
                'question_text' => $transcript,
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


    private function chat($transcript)
    {
        $client = new Client();
        // Kirim teks ke OpenAI Chat API untuk mendeteksi apakah ada perintah membuka link
        return $client->post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => 'gpt-4',
                'messages' => $transcript,
            ]
        ]);
    }


    private function chatCompletion($transcript)
    {
        try {
            // Siapkan prompt untuk mendeteksi apakah perintah pengguna adalah untuk membuka link
            $systemPrompt = "Anda adalah Riska Assistant, sebuah asisten virtual yang dibuat oleh Rizqi Abdul Karim, seorang insinyur perangkat lunak. Fokus utama Anda adalah memberikan informasi dan bantuan terkait Pengadilan Agama Cirebon, termasuk layanan yang tersedia di dalamnya.
    
            Tugas Anda adalah mendeteksi apakah pengguna memberikan perintah untuk membuka sebuah fitur atau halaman web. Jika iya, Anda perlu mengekstrak topik-topik yang relevan dari perintah tersebut dan mengembalikan hasilnya dalam format JSON.
            
            Berikut adalah format JSON yang harus dikembalikan jika pengguna meminta untuk membuka link:
            
            {
                \"topics\": [\"topik1\", \"topik2\", \"topikN\"]
            }
            
            Jika pengguna tidak memberikan perintah untuk membuka link, Anda harus mengembalikan 'null'. 
            
            Catatan Penting:
            - Jangan memberikan informasi yang tidak relevan.
            - Jangan memberikan informasi tentang nomor telepon atau email, meskipun diminta.
            - Pastikan respons Anda selalu tepat dan relevan dengan konteks Pengadilan Agama Cirebon dan fitur-fitur yang terkait dengannya.";

            // Siapkan pesan untuk API
            $messages = [
                [
                    'role' => 'system',
                    'content' => $systemPrompt
                ],
                [
                    'role' => 'user',
                    'content' => $transcript
                ]
            ];

            // Memanggil fungsi chat untuk mendapatkan hasil dari OpenAI
            $response = $this->chat($messages);

            // Pastikan respons dari API tidak kosong
            if (!$response || !$response->getBody()) {
                Log::error('Empty response from OpenAI');
                return 'Error: Empty response from OpenAI';
            }

            $result = json_decode($response->getBody()->getContents(), true);

            // Ambil respons dari ChatGPT
            $topicsResponse = $result['choices'][0]['message']['content'] ?? null;

            $featuresList = "";

            if (trim($topicsResponse) != 'null') {
                // Jika respons adalah JSON, lanjutkan memproses topik
                $decodedTopics = json_decode($topicsResponse, true);

                if (isset($decodedTopics['topics']) && count($decodedTopics['topics']) > 0) {
                    // Ambil semua topik dari respons
                    $topics = array_map('strtolower', array_map('trim', $decodedTopics['topics']));

                    // Query ke database untuk mencari fitur yang sesuai dengan semua topik
                    $query = DB::table('link');
                    foreach ($topics as $topic) {
                        $query->orWhere('name', 'LIKE', '%' . $topic . '%');
                    }

                    $featuresFromDB = $query->get();

                    // Daftar fitur dan URL yang diambil dari database
                    if (!$featuresFromDB->isEmpty()) {
                        foreach ($featuresFromDB as $feature) {
                            $featuresList .= ucfirst($feature->name) . ": " . $feature->link . "\n";
                        }
                    } else {
                        $featuresList = "Fitur tidak ditemukan.";
                    }
                } else {
                    Log::info('No topics found in response');
                    $featuresList = "Fitur tidak ditemukan.";
                }
            } else {
                Log::info('User did not request to open a link');
                $featuresList = "Fitur tidak ditemukan.";
            }

            // Inisialisasi prompt sistem dengan daftar fitur
            $finalPrompt = "Anda adalah Riska Assistant, sebuah asisten virtual yang diciptakan oleh Rizqi Abdul Karim, seorang insinyur perangkat lunak. Anda bertugas memberikan informasi yang akurat dan berguna terkait Pengadilan Agama Cirebon. Berikut adalah daftar fitur dan layanan yang tersedia:
    
            " . $featuresList . "
    
             Jika pengguna meminta untuk membuka sebuah fitur, pastikan Anda hanya memberikan link yang sesuai dengan fitur yang ada. Format yang harus dikembalikan adalah:
    
             {
                 \"action\": \"open_link\",
                 \"url\": \"[URL yang sesuai]\"
             }
    
             Pastikan untuk mengembalikan format JSON tanpa penjelasan tambahan. Jika tidak ada fitur yang sesuai, nyatakan bahwa fitur tidak ditemukan.
             Catatan Penting:
                - Jangan memberikan informasi yang tidak relevan.
                - Jangan memberikan informasi tentang nomor telepon atau email, meskipun diminta.
                - Pastikan respons Anda selalu tepat dan relevan dengan konteks Pengadilan Agama Cirebon dan fitur-fitur yang terkait dengannya
             ";

            // Siapkan pesan untuk API dengan prompt final
            $messages = [
                [
                    'role' => 'system',
                    'content' => $finalPrompt
                ],
                [
                    'role' => 'user',
                    'content' => $transcript
                ]
            ];

            // Memanggil kembali API Chat dengan prompt final
            $response = $this->chat($messages);

            // Pastikan respons dari API tidak kosong
            if (!$response || !$response->getBody()) {
                Log::error('Empty response from OpenAI during second request');
                return 'Error: Empty response from OpenAI during second request';
            }

            $result = json_decode($response->getBody()->getContents(), true);

            // Kembalikan hasil respons terakhir
            return $result['choices'][0]['message']['content'] ?? 'Error: Could not generate response';
        } catch (\Exception $e) {
            Log::error('Error in chatCompletion: ' . $e->getMessage());
            return 'Failed to generate response from ChatGPT';
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
