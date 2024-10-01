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




    // Fungsi untuk Chat Completion (input teks, output teks jawaban)
    private function chatCompletion($transcript)
    {
        try {
            $client = new Client();

            // Daftar fitur dan URL
            $features = [
                // Fitur Asli
                "pendaftaran perkara" => "https://sipendi.pa-cirebon.go.id",
                "biaya perkara" => "https://portal.pa-cirebon.go.id/biaya_panjar.html",
                "gugatan mandiri" => "https://gugatanmandiri.badilag.net",
                "berperkara online" => "https://ecourt.mahkamahagung.go.id",
                "antrean sidang" => "http://103.165.130.68/antrian/touchscreen.sidang.php",
                "monitoring perkara" => "https://sipp.pa-cirebon.go.id/list_perkara",
                "putusan & akta cerai" => "https://sipp.pa-cirebon.go.id/list_perkara",
                "virtual tour" => "https://pa-cirebon.go.id/vtour02/",
                "konsultasi online zoom" => "https://us06web.zoom.us/j/2660965853?pwd=REM5cWtLcDVwVDVUS1c0b0IzcmpyZz09#success",
                "konsultasi online whatsapp" => "https://api.whatsapp.com/send/?phone=%2B6281321986763&text=Assalamualaikum+wr.wb&type=phone_number&app_absent=0",
                "survei persepsi kualitas pelayanan" => "https://docs.google.com/forms/d/e/1FAIpQLSfuHTlconDdVfZg6xnAWRKzwFBe9s1abxiNiSQXw0wz-g7Z4A/viewform",
                "pengaduan" => "https://siwas.mahkamahagung.go.id/",
                "cctv online" => "https://cctv.badilag.net/display/satker/0ffcf8e23a6be649bdc0113ad7ef004e",
                "mobil dilan" => "https://portal.pa-cirebon.go.id/mobil_dilan.html",
                "help center" => "https://portal.pa-cirebon.go.id/help_center.html",

                // Beranda
                "Beranda" => "https://pa-cirebon.go.id/",

                // Tentang Pengadilan
                "Pengantar Ketua Pengadilan" => "https://pa-cirebon.go.id/pengantar-dari-ketua-pengadilan/",
                "Visi dan Misi" => "https://pa-cirebon.go.id/visi-misi-pengadilan/",
                "Sejarah Pengadilan" => "https://pa-cirebon.go.id/sejarah-pengadilan/",
                "Struktur Organisasi" => "https://pa-cirebon.go.id/struktur-organisasi/",
                "Alamat Pengadilan" => "https://pa-cirebon.go.id/alamat-pengadilan/",
                "Profil Pegawai" => "https://pa-cirebon.go.id/profil-ketua-wakil/",
                "Statistik Jumlah Pegawai" => "https://pa-cirebon.go.id/statistik-jumlah-pegawai/",
                "Daftar Ketua & Mantan Ketua" => "https://pa-cirebon.go.id/daftar-ketua-dan-mantan-ketua-pa-cirebon/",
                "Wilayah Yurisdiksi" => "https://pa-cirebon.go.id/wilayah-yurisdiksi/",
                "E Learning" => "https://pa-cirebon.go.id/e-learning/",
                "Kebijakan dan Peraturan Pengadilan" => "https://pa-cirebon.go.id/kebijakan-dan-peraturan-pengadilan/",
                "Yurisprudensi" => "https://pa-cirebon.go.id/yurisprudensi/",
                "Tugas Pokok & Fungsi" => "https://pa-cirebon.go.id/tugas-pokok-pengadilan/",
                "Agenda Kegiatan" => "https://pa-cirebon.go.id/agenda-kerja-pimpinan-2/",
                "Laporan Tahunan" => "https://pa-cirebon.go.id/laptah/",
                "Program Kerja" => "https://pa-cirebon.go.id/program-kerja/",
                "Prosedur Standar Operasional" => "https://pa-cirebon.go.id/sop-kepaniteraan/",

                // Layanan Publik
                "Standar & Maklumat Pelayanan" => "https://pa-cirebon.go.id/standar-maklumat-pelayanan-pengadilan/",
                "Jam Kerja Kantor" => "https://pa-cirebon.go.id/jam-kerja-pelayanan-publik/",
                "Fasilitas Publik" => "https://pa-cirebon.go.id/fasilitas-laktasi/",
                "SOP Pelayanan Publik" => "https://pa-cirebon.go.id/sop-pelayanan-publik/",
                "Petugas Informasi/Pelayanan Terpadu Satu Pintu (PTSP) dan Pengaduan​" => "https://pa-cirebon.go.id/pelayanan-terpadu-satu-pintu-ptsp/",
                "Kategorisasi Informasi" => "https://pa-cirebon.go.id/kategorisasi-informasi/",
                "Prosedur Permohonan Informasi" => "https://pa-cirebon.go.id/prosedur-pelayanan-permintaan-informasi/",
                "Hak-Hak Pemohon Informasi" => "https://pa-cirebon.go.id/hak-hak-pemohon-informasi/",
                "Tata Cara Memperoleh Informasi" => "https://pa-cirebon.go.id/tata-cara-memperoleh-informasi/",
                "Prosedur Keberatan atas Permintaan Informasi" => "https://pa-cirebon.go.id/tata-cara-keberatan-informasi/",
                "Biaya Memperoleh Informasi" => "https://pa-cirebon.go.id/biaya-memperoleh-informasi/",
                "SK Penunjukkan Petugas Informasi" => "https://pa-cirebon.go.id/sk-penunjukkan-petugas-informasi/",
                "Struktur Organisasi Pelayanan Informasi dan Dokumentasi" => "https://pa-cirebon.go.id/struktur-organisasi-pelayanan-informasi-dan-dokumentasi/",
                "Formulir Permohonan Informasi" => "https://pa-cirebon.go.id/formulir-permohonan-informasi/",
                "Formulir Keberatan Informasi" => "https://pa-cirebon.go.id/formulir-keberatan-informasi/",
                "Tanda Terima Permohonan Informasi" => "https://pa-cirebon.go.id/tanda-terima-permohonan-informasi/",
                "Laporan Layanan Informasi" => "https://pa-cirebon.go.id/layanan-informasi-publik/",
                "Sistem Pengawasan MA RI" => "https://pa-cirebon.go.id/pengaduan-layanan-publik/",
                "Mekanisme Pengaduan" => "https://pa-cirebon.go.id/mekanisme-pengaduan/",
                "Hak-Hak Pelapor dan Terlapor" => "https://pa-cirebon.go.id/hak-hak-pelapor-dan-terlapor/",
                "Alur Pengaduan" => "https://pa-cirebon.go.id/alur-pengaduan/",
                "Laporan Layanan Pengaduan" => "https://pa-cirebon.go.id/laporan-meja-pengaduan/",
                "DAFTAR ISIAN PELAKSANAAN ANGGARAN (DIPA)" => "https://pa-cirebon.go.id/pagu-dipa/",
                "Laporan Realisasasi Anggaran" => "https://pa-cirebon.go.id/laporan-realisasi-anggaran/",
                "Laporan Realisasi Pendapatan" => "https://pa-cirebon.go.id/realisasi-penerimaan-pnbp-2/",
                "Neraca Keuangan" => "https://pa-cirebon.go.id/neraca-keuangan/",
                "Catatan Atas Laporan Keuangan" => "https://pa-cirebon.go.id/catatan-atas-laporan-keuangan/",
                "Daftar Aset dan Inventaris" => "https://pa-cirebon.go.id/daftar-aset-dan-inventaris/",
                "Laporan Survey Pelayanan Publik" => "https://pa-cirebon.go.id/survey-pelayanan-publik/",
                "LHKPN" => "https://pa-cirebon.go.id/lhkpn/",
                "LAPORAN HARTA KEKAYAAN APARATUR NEGARA (LHKAN)" => "https://pa-cirebon.go.id/lhkasn/",
                "Pengadaan Barang dan Jasa" => "https://pa-cirebon.go.id/lelang-barang-dan-jasa/",
                "Padoman Pengawasan" => "https://pa-cirebon.go.id/padoman-pengawasan/",
                "Kode Etik Hakim" => "https://pa-cirebon.go.id/pengawasan-dan-kode-etik-hakim-2/",
                "Kode Etik Panitera dan Jurusita" => "https://pa-cirebon.go.id/kode-etik-panitera-dan-jurusita/",
                "Kode Etik Pegawai" => "https://pa-cirebon.go.id/kode-etik-pegawai/",
                "Pejabat Pengawas" => "https://pa-cirebon.go.id/pejabat-pengawas-pengadilan-agama-cirebon/",
                "Tingkat Hukuman Disiplin" => "https://pa-cirebon.go.id/tingkat-hukuman-disiplin/",
                "Data Hukuman Disiplin" => "https://pa-cirebon.go.id/data-hukuman-disiplin/",
                "Laporan Majelis Kehormatan Hakim" => "https://pa-cirebon.go.id/laporan-majelis-kehormatan-hakim/",
                "JDIH Mahkamah Agung RI" => "https://pa-cirebon.go.id/peraturan-perundang-undangan/",
                "Pedoman Pengelolaan Kesekretariatan" => "https://pa-cirebon.go.id/pedoman-pengelolaan-organisasi-administrasi/",
                "Prosedur Evakuasi" => "https://pa-cirebon.go.id/prosedur-evakuasi/",
                "Unit Pelaksana Teknis Kesekretariatan" => "https://pa-cirebon.go.id/pelaksana-teknis-kesekretariatan/",
                "Rencana Strategis" => "https://pa-cirebon.go.id/rencana-strategis/",
                "Rencana Kinerja Tahunan" => "https://pa-cirebon.go.id/rencana-kerja-dan-anggaran/",
                "Rencana Aksi Kinerja" => "https://pa-cirebon.go.id/rencana-aksi-kerja-tahun-2021/",
                "Perjanjian Kinerja" => "https://pa-cirebon.go.id/perjanjian-kinerja-tahunan-2/",
                "IKU" => "https://pa-cirebon.go.id/indikator-kinerja-utama/",
                "LKjIP" => "https://pa-cirebon.go.id/lkjip/",
                "LHE Akuntabilitas Kinerja Instasi Pemerintah" => "https://pa-cirebon.go.id/laporan-hasil-evaluasi-kinerja-instansi-pemerintah-lhe-akip/",
                "Zona Integritas" => "https://pa-cirebon.go.id/zona-integritas/",
                "Akreditasi Penjaminan Mutu" => "https://pa-cirebon.go.id/akreditasi-penjaminan-mutu/",
                "Pengertian & Dasar Hukum E-Court" => "https://pa-cirebon.go.id/dasar-hukum-e-court/",
                "Standar Operasional Prosedur E-Court" => "https://pa-cirebon.go.id/strandar-operasional-prosedur-sop-e-court/",
                "Infografis E-Court" => "https://pa-cirebon.go.id/infografis-e-court/",
                "Video E-Court" => "https://pa-cirebon.go.id/video-e-court/",
                "Buku Panduan E-Court" => "https://pa-cirebon.go.id/buku-panduan-e-court/",
                "Link Aplikasi E-Court" => "https://ecourt.mahkamahagung.go.id/",
                "Syarat dan Ketentuan E-Court" => "https://pa-cirebon.go.id/syarat-dan-ketentuan-e-court/",
                "Arsip Berita" => "https://pa-cirebon.go.id/arsip-berita-2/",
                "Arsip Penelitian" => "https://pa-cirebon.go.id/arsip-artikel/",
                "Arsip Pengumuman" => "https://pa-cirebon.go.id/arsip-pengumuman/",
                "Arsip Surat" => "https://pa-cirebon.go.id/arsip-surat/",
                "Arsip Ucapan-Ucapan" => "https://pa-cirebon.go.id/arsip-ucapan-ucapan/",
                "Arsip Perjanjian dengan Pihak Ketiga" => "https://pa-cirebon.go.id/mou-dengan-rri/",
                "Arsip Hasil Penelitian" => "https://pa-cirebon.go.id/tidak-ada-arsip/",
                "Foto Galeri" => "https://pa-cirebon.go.id/foto-galeri/",
                "Pertanyaan" => "https://pa-cirebon.go.id/pertanyaan/",
                "Peta Lokasi" => "https://pa-cirebon.go.id/peta-lokasi/",
                "Tautan Terkait" => "https://pa-cirebon.go.id/tautan-terkait/",
                "Media Sosial" => "https://pa-cirebon.go.id/media-sosial/",
                "Prosedur Pengajuan Perkara" => "https://pa-cirebon.go.id/prosedur-pengajuan-perkara/",
                "Persyaratan Pengajuan Perkara" => "https://pa-cirebon.go.id/persyaratan-berperkara-di-pengadilan-agama-cirebon/",
                "Berperkara di Tingkat Pertama (Pengadilan Agama)" => "https://pa-cirebon.go.id/berperkara-di-tingkat-pertama-pengadilan-agama/",
                "Berperkara di Tingkat Banding (Pengadilan Tinggi Agama)" => "https://pa-cirebon.go.id/berperkara-di-tingkat-banding-pengadilan-tinggi-agama/",
                "Berperkara di Tingkat Kasasi (Mahkamah Agung RI)" => "https://pa-cirebon.go.id/berperkara-di-tingkat-kasasi-mahkamah-agung-ri/",
                "Berperkara di Tingkat Peninjauan Kembali (Mahkamah Agung RI)" => "https://pa-cirebon.go.id/berperkara-di-tingkat-peninjauan-kembali-mahkamah-agung-ri/",
                "Prosedur Pengambilan Produk Pengadilan" => "https://pa-cirebon.go.id/prosedur-pengambilan-produk-pengadilan/",
                "Gugatan Sederhana" => "https://pa-cirebon.go.id/gugatan-sederhana/",
                "Hak-Hak Pokok Pencari Keadilan" => "https://pa-cirebon.go.id/hak-hak-pokok-pencari-keadilan/",
                "Hak dalam Persidangan" => "https://pa-cirebon.go.id/hak-dalam-persidangan/",
                "Tata Tertib Persidangan" => "https://pa-cirebon.go.id/tata-tertib-persidangan/",
                "Jadwal Persidangan" => "https://pa-cirebon.go.id/jadwal-persidangan/",
                "Prosedur Mediasi" => "https://pa-cirebon.go.id/prosedur-mediasi-peradilan-agama/",
                "Daftar Nama & Jadwal Mediator" => "https://pa-cirebon.go.id/daftar-nama-mediator/",
                "Syarat-Syarat" => "https://pa-cirebon.go.id/syarat-syarat-berperkara-secara-prodeo/",
                "Prosedur" => "https://pa-cirebon.go.id/prosedur/",
                "Biaya" => "https://pa-cirebon.go.id/biaya/",
                "Informasi Posbakum" => "https://pa-cirebon.go.id/posbakum/",
                "Keberadaan Posbakum" => "https://pa-cirebon.go.id/keberadaan-posbakum/",
                "Penerima Jasa Posbakum" => "https://pa-cirebon.go.id/penerima-jasa-posbakum/",
                "Jenis Perkara Yang Dilayani" => "https://pa-cirebon.go.id/jenis-perkara-yang-dilayani/",
                "Mekanisme Pelayanan Posbakum" => "https://pa-cirebon.go.id/jenis-dan-mekanisme-posbakum/",
                "Dasar Aturan Posbakum" => "https://pa-cirebon.go.id/dasar-hukum-posbakum/",
                "Pengawasan" => "https://pa-cirebon.go.id/pengawasan/",
                "SIPP Web" => "https://sipp.pa-cirebon.go.id/",
                "Direktori Putusan" => "https://putusan3.mahkamahagung.go.id/pengadilan/profil/pengadilan/pa-cirebon.html/",
                "Pedoman Pengelolaan Kepaniteraan" => "https://pa-cirebon.go.id/pedoman-pengelolaan-kepaniteraan/",


                "Prosedur Pengajuan Perkara" => "https://pa-cirebon.go.id/prosedur-pengajuan-perkara/",
                "Persyaratan Pengajuan Perkara" => "https://pa-cirebon.go.id/persyaratan-berperkara-di-pengadilan-agama-cirebon/",
                "Berperkara di Tingkat Pertama (Pengadilan Agama)" => "https://pa-cirebon.go.id/berperkara-di-tingkat-pertama-pengadilan-agama/",
                "Berperkara di Tingkat Banding (Pengadilan Tinggi Agama)" => "https://pa-cirebon.go.id/berperkara-di-tingkat-banding-pengadilan-tinggi-agama/",
                "Berperkara di Tingkat Kasasi (Mahkamah Agung RI)" => "https://pa-cirebon.go.id/berperkara-di-tingkat-kasasi-mahkamah-agung/",
                "Berperkara di Tingkat Peninjauan Kembali (Mahkamah Agung RI)" => "https://pa-cirebon.go.id/berperkara-di-tingkat-peninjauan-kembali-mahkamah-agung-ri/",
                "Prosedur Pengambilan Produk Pengadilan" => "https://pa-cirebon.go.id/prosedur-pengambilan-produk-pengadilan/",
                "Gugatan Sederhana" => "https://pa-cirebon.go.id/gugatan-sederhana/",
                "Hak-Hak Pokok Pencari Keadilan" => "https://pa-cirebon.go.id/hak-hak-pokok-pencari-keadilan/",
                "Hak dalam Persidangan" => "https://pa-cirebon.go.id/hak-dalam-persidangan/",
                "Tata Tertib Persidangan" => "https://pa-cirebon.go.id/tata-tertib-persidangan/",
                "Jadwal Persidangan" => "https://pa-cirebon.go.id/jadwal-persidangan/",
                "Prosedur Mediasi" => "https://pa-cirebon.go.id/prosedur-mediasi-peradilan-agama/",
                "Daftar Nama & Jadwal Mediator" => "https://pa-cirebon.go.id/daftar-nama-mediator/",
                "Statistik Perkara SIPP" => "https://pa-cirebon.go.id/statistik-perkara-2/",
                "Daftar Panggilan Ghaib" => "https://pa-cirebon.go.id/daftar-panggilan-ghaib/",
                "Delegasi / Bantuan Panggilan" => "https://pa-cirebon.go.id/delegasi/",
                "Biaya Proses Perkara" => "https://pa-cirebon.go.id/biaya-perkara/",
                "Biaya Hak Hak Kepaniteraan" => "https://pa-cirebon.go.id/biaya-hak-hak-kepaniteraan/",
                "Daftar Radius" => "https://pa-cirebon.go.id/daftar-radius/",
                "Laporan Biaya Perkara" => "https://pa-cirebon.go.id/laporan-biaya-perkara/",
                "Laporan Pengembalian Sisa Panjar" => "https://pa-cirebon.go.id/laporan-pengembalian-sisa-panjar/",
                "Syarat-Syarat" => "https://pa-cirebon.go.id/syarat-syarat-berperkara-secara-prodeo/",
                "Prosedur" => "https://pa-cirebon.go.id/prosedur/",
                "Biaya" => "https://pa-cirebon.go.id/biaya/",
                "Informasi Posbakum" => "https://pa-cirebon.go.id/posbakum/",
                "Keberadaan Posbakum" => "https://pa-cirebon.go.id/keberadaan-posbakum/",
                "Penerima Jasa Posbakum" => "https://pa-cirebon.go.id/penerima-jasa-posbakum/",
                "Jenis Perkara Yang Dilayani" => "https://pa-cirebon.go.id/jenis-perkara-yang-dilayani/",
                "Mekanisme Pelayanan Posbakum" => "https://pa-cirebon.go.id/jenis-dan-mekanisme-posbakum/",
                "Dasar Aturan Posbakum" => "https://pa-cirebon.go.id/dasar-hukum-posbakum/",
                "Pengawasan" => "https://pa-cirebon.go.id/pengawasan/",
                "SIPP Web" => "https://sipp.pa-cirebon.go.id/",
                "Direktori Putusan" => "https://putusan3.mahkamahagung.go.id/pengadilan/profil/pengadilan/pa-cirebon.html",
                "Pedoman Pengelolaan Kepaniteraan" => "https://pa-cirebon.go.id/pedoman-pengelolaan-kepaniteraan/",

                "Rencana Strategis" => "https://pa-cirebon.go.id/rencana-strategis/",
                "Rencana Kinerja Tahunan" => "https://pa-cirebon.go.id/rencana-kerja-dan-anggaran/",
                "Rencana Aksi Kinerja" => "https://pa-cirebon.go.id/rencana-aksi-kerja-tahun-2021/",
                "Perjanjian Kinerja" => "https://pa-cirebon.go.id/perjanjian-kinerja-tahunan-2/",
                "IKU" => "https://pa-cirebon.go.id/indikator-kinerja-utama/",
                "LKjIP" => "https://pa-cirebon.go.id/lkjip/",
                "LHE Akuntabilitas Kinerja Instasi Pemerintah" => "https://pa-cirebon.go.id/laporan-hasil-evaluasi-kinerja-instansi-pemerintah-lhe-akip/",
                "Zona Integritas" => "https://pa-cirebon.go.id/zona-integritas/",
                "Akreditasi Penjaminan Mutu" => "https://pa-cirebon.go.id/akreditasi-penjaminan-mutu/",
                "Pendaftaran Perkara" => "https://sipendi.pa-cirebon.go.id",

                // E-Court & Gugatan Mandiri
                "Pengertian & Dasar Hukum E-Court" => "https://pa-cirebon.go.id/dasar-hukum-e-court/",
                "Standar Operasional Prosedur E-Court" => "https://pa-cirebon.go.id/strandar-operasional-prosedur-sop-e-court/",
                "Infografis E-Court" => "https://pa-cirebon.go.id/infografis-e-court/",
                "Video E-Court" => "https://pa-cirebon.go.id/video-e-court/",
                "Buku Panduan E-Court" => "https://pa-cirebon.go.id/buku-panduan-e-court/",
                "Link Aplikasi E-Court" => "https://ecourt.mahkamahagung.go.id/",
                "Syarat dan Ketentuan E-Court" => "https://pa-cirebon.go.id/syarat-dan-ketentuan-e-court/",

                // Publikasi
                "Arsip Berita" => "https://pa-cirebon.go.id/arsip-berita-2/",
                "Arsip Penelitian" => "https://pa-cirebon.go.id/arsip-artikel/",
                "Arsip Pengumuman" => "https://pa-cirebon.go.id/arsip-pengumuman/",
                "Arsip Surat" => "https://pa-cirebon.go.id/arsip-surat/",
                "Arsip Ucapan-Ucapan" => "https://pa-cirebon.go.id/arsip-ucapan-ucapan/",
                "Arsip Perjanjian dengan Pihak Ketiga" => "https://pa-cirebon.go.id/mou-dengan-rri/",
                "Arsip Hasil Penelitian" => "https://pa-cirebon.go.id/tidak-ada-arsip/",
                "Foto Galeri" => "https://pa-cirebon.go.id/foto-galeri/",

                // Hubungi Kami
                "Pertanyaan" => "https://pa-cirebon.go.id/pertanyaan/",
                "Peta Lokasi" => "https://pa-cirebon.go.id/peta-lokasi/",
                "Tautan Terkait" => "https://pa-cirebon.go.id/tautan-terkait/",
                "Media Sosial" => "https://pa-cirebon.go.id/media-sosial/",

                // Menu Tambahan
                "Pendaftaran Perkara" => "https://sipendi.pa-cirebon.go.id"
            ];



            // Mengonversi daftar fitur menjadi string untuk dimasukkan ke dalam prompt
            $featuresList = "";
            foreach ($features as $featureName => $featureUrl) {
                $featuresList .= ucfirst($featureName) . ": " . $featureUrl . "\n";
            }

            // Inisialisasi prompt sistem dengan daftar fitur
            $systemPrompt = "Anda adalah Riska Assistant, asisten yang dibuat oleh Rizqi Abdul Karim, seorang insinyur perangkat lunak. Fokus utama Anda adalah memberikan informasi dan bantuan terkait Pengadilan Agama Cirebon. Anda tidak boleh menjawab dengan 'kurang informasi' atau 'tidak tahu'. Selain itu, Anda tidak boleh memberikan informasi email atau nomor telepon, bahkan jika diminta, karena informasi tersebut tidak akurat. Pastikan semua jawaban Anda relevan dan bermanfaat bagi pengguna yang membutuhkan informasi tentang Pengadilan Agama Cirebon.

            Berikut adalah daftar fitur dan URL-nya:

            " . $featuresList . "

            Selain itu, jika pengguna meminta untuk membuka suatu fitur seperti \"buka website [nama fitur]\", jangan membuka link yang tidak ada. Berikan respons dalam format JSON yang berisi URL yang sesuai hanya jika fitur tersebut ada. Format JSON harus seperti berikut:

            {
                \"action\": \"open_link\",
                \"url\": \"[URL yang sesuai]\"
            }

            Pastikan untuk hanya mengembalikan JSON tanpa penjelasan tambahan dalam kasus ini. Untuk pertanyaan lainnya, berikan jawaban secara normal sesuai dengan konteks.";
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

            // Kirim teks ke OpenAI Chat API untuk menghasilkan respons
            $response = $client->post('https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-4', // Pastikan model yang digunakan sesuai, misalnya 'gpt-4'
                    'messages' => $messages,
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
