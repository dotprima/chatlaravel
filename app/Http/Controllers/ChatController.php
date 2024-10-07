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
        $voice = $request->channel;

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

                // Decode the JSON result into an array
                $completionArray = json_decode($completionText['text'], true);

                // Check if 'action' and 'url' exist and are not null
                if (isset($completionArray['action']) && isset($completionArray['url'])) {
                    $voiceBuffer = 'voice';

                    $responseText = $completionText;
                } else {
                    // 2. Process Text-to-Speech (from answer to voice buffer)
                    $voiceBuffer = $this->textToSpeech($completionText['text'], $voice);

                    $responseText = $completionText;
                }
            }
            // Check if the request contains a text message
            elseif ($request->has('message')) {
                // Handle text input

                // Retrieve the text message
                $transcript = $request->input('message');

                // 1. Process Chat Completion (from text to OpenAI answer)
                $completionText = $this->chatCompletion($transcript);

                // Decode the JSON result into an array
                $completionArray = json_decode($completionText['text'], true);

                // Check if 'action' and 'url' exist and are not null
                if (isset($completionArray['action']) && isset($completionArray['url'])) {
                    $voiceBuffer = 'voice';

                    $responseText = $completionText;
                } else {
                    // 2. Process Text-to-Speech (from answer to voice buffer)
                    $voiceBuffer = $this->textToSpeech($completionText['text'], $voice);

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

            $answer_action = $completionArray['answer'] ?? null;
            $deskripsi = $responseText['description'] ?? null;

            if ($answer_action) {
                $answer_action = $this->textToSpeech($answer_action, $voice);
            }

            if ($deskripsi) {
                $deskripsi = $this->textToSpeech($deskripsi, $voice);
            }


            // Return the response text and audio data
            return response()->json([
                'description_voice' => $deskripsi ?? null,
                'question_text' => $transcript,
                'answer_action_voice' => $answer_action ?? null,
                'response_text' => $responseText['text'] ?? null,
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


    private function chat($transcript, $max_tokens = 500, $temperature = 0.7)
    {
        $client = new Client();
        $model = env('OPENAI_CHAT_MODEL');
        // Kirim teks ke OpenAI Chat API untuk mendeteksi apakah ada perintah membuka link
        return $client->post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $model,
                'messages' => $transcript,
                'max_tokens' => $max_tokens,
                'temperature' => $temperature,
            ]
        ]);
    }


    private function chatCompletion($transcript)
    {
        try {
            // Siapkan prompt untuk mendeteksi apakah perintah pengguna adalah untuk membuka link
            $systemPrompt = "Anda adalah Riska Assistant, sebuah asisten virtual yang dibuat oleh Rizqi Abdul Karim, seorang insinyur perangkat lunak. Fokus utama Anda adalah memberikan informasi dan bantuan terkait Pengadilan Agama Cirebon, termasuk layanan yang tersedia di dalamnya.
    
            Tugas Anda adalah mendeteksi apakah pengguna memberikan perintah untuk membuka sebuah fitur atau halaman web. Jika iya, Anda perlu mengekstrak topik-topik yang relevan dari perintah tersebut dan mengembalikan hasilnya dalam format JSON per kata.
            
            Berikut adalah format JSON per kata yang harus dikembalikan jika pengguna meminta untuk membuka link:
            
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
            $response = $this->chat($messages, 2000, 0.1);

            // Pastikan respons dari API tidak kosong
            if (!$response || !$response->getBody()) {
                Log::error('Empty response from OpenAI');
                return 'Error: Empty response from OpenAI';
            }

            $result = json_decode($response->getBody()->getContents(), true);

            // Ambil respons dari ChatGPT
            $topicsResponse = $result['choices'][0]['message']['content'] ?? null;

            $featuresList = "";
            $search = "";
            if (trim($topicsResponse) != 'null') {
                // Jika respons adalah JSON, lanjutkan memproses topik
                $decodedTopics = json_decode($topicsResponse, true);
            
                if (isset($decodedTopics['topics']) && count($decodedTopics['topics']) > 0) {
                    // Ambil semua topik dari respons, lalu trim dan lowercase
                    $topics = array_map('strtolower', array_map('trim', $decodedTopics['topics']));
            
                    // Inisialisasi variabel untuk menyimpan daftar fitur
                    $featuresList = "";
            
                    // Bangun ekspresi relevansi untuk mengurutkan berdasarkan jumlah kesamaan topik
                    $relevanceExpression = "";
                    foreach ($topics as $topic) {
                        // Pastikan untuk mengamankan input untuk mencegah SQL Injection
                        $safeTopic = addslashes($topic);
                        $relevanceExpression .= " + (LOWER(name) LIKE '%{$safeTopic}%')";
                    }
                    // Hilangkan ' + ' di awal string
                    $relevanceExpression = ltrim($relevanceExpression, ' + ');
            
                    // Query ke database dengan menghitung relevansi
                    $featuresFromDB = DB::table('link')
                        ->select('link.*')
                        ->selectRaw("({$relevanceExpression}) as relevance")
                        ->where(function ($query) use ($topics) {
                            foreach ($topics as $topic) {
                                $query->orWhereRaw("LOWER(name) LIKE ?", ['%' . strtolower($topic) . '%']);
                            }
                        })
                        ->orderByDesc('relevance') // Urutkan berdasarkan relevansi secara menurun
                        ->get();
            
                    // Daftar fitur dan URL yang diambil dari database
                    if (!$featuresFromDB->isEmpty()) {
                        foreach ($featuresFromDB as $feature) {
                            $featuresList .= ucfirst($feature->name) . ": " . $feature->link . "\n";
                        }

                        $first = $featuresFromDB->first();
                        $deskripsi = $first->description;
            
                        $search = "{$featuresList}
                        Jika pengguna meminta untuk membuka sebuah fitur, pastikan Anda hanya memberikan link yang sesuai dengan fitur yang ada. Format yang harus dikembalikan adalah:
                        {
                            \"action\": \"open_link\",
                            \"url\": \"[URL yang sesuai]\",
                            \"answer\": \"[jawaban kamu , contoh baik saya akan membuka ...]\"
                        }
                        Pastikan untuk mengembalikan format JSON tanpa penjelasan tambahan. Jika tidak ada fitur yang sesuai, nyatakan bahwa fitur tidak ditemukan.";
                    } else {
                        $featuresList = "";
                    }
                    
                } else {
                    Log::info('No topics found in response');
                    $featuresList = "";
                }
            } else {
                Log::info('User did not request to open a link');
                $featuresList = "";
            }
            

            $finalPrompt = "Anda adalah Riska Assistant, sebuah asisten virtual yang diciptakan oleh Rizqi Abdul Karim, seorang insinyur perangkat lunak yang berfokus pada pengembangan sistem untuk pelayanan Pengadilan Agama Cirebon. Selain memberikan informasi tentang Pengadilan Agama Cirebon, Anda juga dapat memberikan informasi umum yang relevan sesuai permintaan, selama tetap mematuhi batasan yang ada.";

            // Jika $search kosong, tambahkan instruksi khusus untuk text-to-speech
            if ($search === "") {
                $finalPrompt .= "\n\nTanggapi dengan cara yang mudah diucapkan oleh perangkat text-to-speech.";
            } else {
                $finalPrompt .= $search;
            }

            $finalPrompt .= "jika pengguna meminta untuk menutup halaman , cukup berikan saja 
                        {
                            \"action\": \"close_link\",
                            \"answer\": \"[jawaban kamu , contoh baik saya akan membuka ...]\"
                        }";
            $finalPrompt .= "\n\nCatatan Penting:\n- Jangan memberikan informasi tentang nomor telepon atau email, meskipun diminta.";

            Log::info('topicsResponse: ' . $topicsResponse);
            Log::info('Search: ' . $search);
            Log::info('Final prompt: ' . $finalPrompt);

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

            // Pastikan 'choices', 'message', dan 'content' ada
            if (isset($result['choices'][0]['message']['content'])) {
                // Jika 'content' adalah string, ubah menjadi array sebelum menambahkan deskripsi
                if (is_string($result['choices'][0]['message']['content'])) {
                    $result['choices'][0]['message']['content'] = [
                        'text' => $result['choices'][0]['message']['content'], // Simpan konten asli di 'text'
                    ];
                }
            
                // Tambahkan deskripsi ke dalam array 'content'
                $result['choices'][0]['message']['content']['description'] = $deskripsi ?? null;
            }
            
            // Kembalikan hasil respons terakhir
            return $result['choices'][0]['message']['content'] ?? 'Error: Could not generate response';

        } catch (\Exception $e) {
            Log::error('Error in chatCompletion: ' . $e->getMessage());
            return 'Failed to generate response from ChatGPT';
        }
    }





    // Fungsi untuk Text-to-Speech (input teks, output buffer audio)


    /**
     * Converts text to speech using either Google TTS or OpenAI TTS.
     *
     * @param string $responseText The text to be converted to audio.
     * @param string $voice The voice service to use ('google_tts' or other for OpenAI).
     * @return string The base64-encoded audio.
     * @throws \Exception If audio generation fails.
     */

    private function textToSpeech(string $responseText, string $voice = 'google_tts'): string
    {
        try {
            if ($voice === 'google_tts') {
                $ttsHelper  = new TextToSpeechHelper();
                // Menggunakan helper TextToSpeechHelper untuk membuat TTS dan menggabungkan audio
                $combinedBase64 = $ttsHelper->getCombinedAudioBase64($responseText, [
                    'lang' => 'id', // Bahasa Indonesia
                    'slow' => false,
                    'host' => 'https://translate.google.com',
                ]);


                if (!empty($combinedBase64)) {
                    return $combinedBase64;
                } else {
                    throw new \Exception('Empty audio content received from Google TTS');
                }
            } else {
                // Gunakan OpenAI Text-to-Speech jika voice bukan google_tts
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
                    ],
                    'timeout' => 10, // Timeout dalam detik
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
