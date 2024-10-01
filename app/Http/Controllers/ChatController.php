<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Helpers\TextToSpeechHelper;

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

                $jsonEncoded = json_encode($completionText);

                // Memeriksa apakah encoding berhasil
                if ($jsonEncoded === false) {
                  
                    // 2. Process Text-to-Speech (from answer to voice buffer)
                    $voiceBuffer = $this->textToSpeech($completionText);

                    $responseText = $completionText;
                } else {

                    $voiceBuffer = 'voice';

                    $responseText = $completionText;
                }
            } else {
                // No valid input provided
                return response()->json(['error' => 'No input provided'], 400);
            }

            // Ensure that voiceBuffer has been generated
            if (!$voiceBuffer) {
                return response()->json(['error' => 'Failed to generate audio response'], 500);
            }

            // Encode the audio buffer to Base64
            $voiceBase64 = $voiceBuffer;

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
            
            Jika pengguna tidak memberikan perintah untuk membuka link, Anda harus mengembalikan 'null'.";

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

    private function textToSpeech($responseText, $voice = 'responsivevoice')
    {
        try {
            if ($voice === 'responsivevoice') {
                // Konfigurasi untuk ResponsiveVoice TTS
                $apiUrl = 'https://texttospeech.responsivevoice.org/v1/text:synthesize';
                $apiKey = 'mPtwTKWZ'; // Pastikan untuk menyimpan API key dengan aman

                // Parameter yang akan dikirim ke API
                $params = [
                    'text' => $responseText,
                    'lang' => 'id', // Bahasa Indonesia
                    'engine' => 'g1',
                    'name' => '', // Anda bisa mengisi jika diperlukan
                    'pitch' => 0.5,
                    'rate' => 0.5,
                    'volume' => 1,
                    'key' => $apiKey,
                    'gender' => 'female',
                ];

                // Inisialisasi klien Guzzle
                $client = new Client();

                // Mengirim permintaan GET ke API dengan parameter query
                $response = $client->get($apiUrl, [
                    'query' => $params,
                    'timeout' => 30, // Timeout dalam detik
                ]);

                // Memeriksa apakah respons berhasil
                if ($response->getStatusCode() === 200) {
                    // Mendapatkan isi respons sebagai string biner (audio)
                    $audioContent = $response->getBody()->getContents();

                    if (!empty($audioContent)) {
                        // Encode audio ke base64
                        return base64_encode($audioContent);
                    } else {
                        throw new \Exception('Empty audio content received from ResponsiveVoice API');
                    }
                } else {
                    throw new \Exception('ResponsiveVoice API responded with status code ' . $response->getStatusCode());
                }
            } else {
                // Gunakan OpenAI Text-to-Speech jika voice bukan responsivevoice
                $client = new Client();

                $response = $client->post('https://api.openai.com/v1/audio/speech', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
                        'Content-Type'  => 'application/json',
                    ],
                    'json' => [
                        'model' => 'tts-1',
                        'input' => $responseText,
                        'voice' => 'alloy',
                    ]
                ]);

                // Memeriksa apakah respons berhasil
                if ($response->getStatusCode() === 200) {
                    $voiceBuffer = $response->getBody()->getContents();
                    return base64_encode($voiceBuffer);
                } else {
                    throw new \Exception('OpenAI API responded with status code ' . $response->getStatusCode());
                }
            }
        } catch (\Exception $e) {
            Log::error('Error in textToSpeech: ' . $e->getMessage());
            throw new \Exception('Failed to generate audio from text');
        }
    }
}
