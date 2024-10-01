<?php

namespace App\Helpers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Exception;

class TextToSpeechHelper
{
    /**
     * Validate input types.
     *
     * @param string $text
     * @param string $lang
     * @param bool $slow
     * @param string $host
     * @throws Exception
     */
    private static function assertInputTypes(string $text, string $lang, bool $slow, string $host): void
    {
        if (empty($text)) {
            throw new Exception('Text cannot be empty.');
        }

        if (empty($lang)) {
            throw new Exception('Language code cannot be empty.');
        }

        if (filter_var($host, FILTER_VALIDATE_URL) === false) {
            throw new Exception('Invalid host URL.');
        }
    }

    /**
     * Split the text into manageable chunks based on sentence boundaries.
     *
     * @param string $text
     * @return array
     */
    private static function splitLongText(string $text): array
    {
        // Split text into sentences using regex
        // This regex splits on ., !, ?, followed by a space or end of string
        $sentences = preg_split('/(?<=[.?!])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        // Further split sentences that are still too long
        $maxLength = 200;
        $chunks = [];

        foreach ($sentences as $sentence) {
            if (strlen($sentence) <= $maxLength) {
                $chunks[] = $sentence;
            } else {
                // Split long sentences into smaller chunks
                $parts = str_split($sentence, $maxLength);
                foreach ($parts as $part) {
                    $chunks[] = $part;
                }
            }
        }

        return $chunks;
    }

    /**
     * Get Google TTS audio as a base64-encoded string.
     *
     * @param string $text Length should be less than 200 characters
     * @param array $options
     * @return string Base64-encoded audio
     * @throws Exception
     */
    public static function getAudioBase64(string $text, array $options = []): string
    {
        $lang = $options['lang'] ?? 'en';
        $slow = $options['slow'] ?? false;
        $host = $options['host'] ?? 'https://translate.google.com';
        $timeout = $options['timeout'] ?? 10; // Timeout in seconds

        self::assertInputTypes($text, $lang, $slow, $host);

        if (strlen($text) > 200) {
            throw new Exception('Text length should be less than 200 characters.');
        }

        $client = new Client();

        try {
            $response = $client->post($host . '/_/TranslateWebserverUi/data/batchexecute', [
                'timeout' => $timeout,
                'form_params' => [
                    'f.req' => json_encode([
                        [['jQ1olc', json_encode([$text, $lang, $slow, null]), null, 'generic']]
                    ])
                ]
            ]);
        } catch (GuzzleException $e) {
            throw new Exception('HTTP request failed: ' . $e->getMessage());
        }

        $body = (string) $response->getBody();

        // Remove the leading characters that are not part of the JSON
        $jsonStart = strpos($body, '[');
        if ($jsonStart === false) {
            throw new Exception('Invalid response format.');
        }

        $jsonString = substr($body, $jsonStart);

        // Decode the JSON response
        $decoded = json_decode($jsonString, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('JSON decode error: ' . json_last_error_msg());
        }

        // Navigate through the decoded array to extract the base64 audio
        try {
            // The exact path may vary; adjust based on actual response structure
            $result = $decoded[0][2][0][0][1];
            if (empty($result)) {
                throw new Exception('Empty audio result.');
            }
        } catch (\Exception $e) {
            throw new Exception('Failed to parse audio from response.');
        }

        return $result;
    }

    /**
     * Split the long text into multiple short texts and generate audio base64 list.
     *
     * @param string $text
     * @param array $options
     * @return array List of short texts and their corresponding base64-encoded audio
     * @throws Exception
     */
    public static function getAllAudioBase64(string $text, array $options = []): array
    {
        $lang = $options['lang'] ?? 'en';
        $slow = $options['slow'] ?? false;
        $host = $options['host'] ?? 'https://translate.google.com';
        $timeout = $options['timeout'] ?? 10; // Timeout in seconds

        self::assertInputTypes($text, $lang, $slow, $host);

        // Split the text into manageable chunks
        $shortTextList = self::splitLongText($text);
        $base64List = [];

        foreach ($shortTextList as $shortText) {
            try {
                $base64 = self::getAudioBase64($shortText, [
                    'lang' => $lang,
                    'slow' => $slow,
                    'host' => $host,
                    'timeout' => $timeout
                ]);
                $base64List[] = ['shortText' => $shortText, 'base64' => $base64];
            } catch (Exception $e) {
                // Log the error and continue with the next chunk
                // Alternatively, you can choose to rethrow the exception
                // For this example, we'll rethrow
                throw new Exception('Failed to get audio for short text: "' . $shortText . '". Error: ' . $e->getMessage());
            }
        }

        return $base64List;
    }
}
